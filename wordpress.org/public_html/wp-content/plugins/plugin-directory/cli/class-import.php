<?php
namespace WordPressdotorg\Plugin_Directory\CLI;

use Exception;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Jobs\Tide_Sync;
use WordPressdotorg\Plugin_Directory\Block_JSON;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Email\Release_Confirmation as Release_Confirmation_Email;
use WordPressdotorg\Plugin_Directory\Readme\Parser;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\Block_e2e;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Zip\Builder;

/**
 * The functionality required to process a plugin import into the Directory.
 *
 * This will normally be called on the CLI in response to a plugin commit.
 *
 * @package WordPressdotorg\Plugin_Directory\CLI
 */
class Import {
	const PLUGIN_SVN_BASE = 'https://plugins.svn.wordpress.org';

	// Readme fields which get stored in plugin meta
	public $readme_fields = array(
		'tested',
		'donate_link',
		'license',
		'license_uri',
		'upgrade_notice',
		'screenshots',

		// These headers are stored as post meta, but are handled separately.
		// 'requires',
		// 'requires_php',
	);

	// Plugin headers that are stored in plugin meta
	public $plugin_headers = array(
		// Header    => meta_key
		'Name'       => 'header_name',
		'PluginURI'  => 'header_plugin_uri',
		'Author'     => 'header_author',
		'AuthorURI'  => 'header_author_uri',
		'TextDomain' => 'header_textdomain',

		// These headers are stored in these fields, but are handled separately.
		// 'Version'     => 'version',
		// 'RequiresWP'  => 'requires',
		// 'RequiresPHP' => 'requires_php',
	);

	/**
	 * Process an import for a Plugin into the Plugin Directory.
	 *
	 * @throws \Exception
	 *
	 * @param string $plugin_slug            The slug of the plugin to import.
	 * @param array  $svn_changed_tags       A list of tags/trunk which the SVN change touched. Optional.
	 * @param array  $svn_revision_triggered The SVN revision which this import has been triggered by.
	 */
	public function import_from_svn( $plugin_slug, $svn_changed_tags = array( 'trunk' ), $svn_revision_triggered = 0 ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $plugin ) {
			throw new Exception( 'Unknown Plugin' );
		}

		$data = $this->export_and_parse_plugin( $plugin_slug );

		$readme          = $data['readme'];
		$assets          = $data['assets'];
		$headers         = $data['plugin_headers'];
		$stable_tag      = $data['stable_tag'];
		$last_committer  = $data['last_committer'];
		$last_revision   = $data['last_revision'];
		$tagged_versions = $data['tagged_versions'];
		$last_modified   = $data['last_modified'];
		$blocks          = $data['blocks'];
		$block_files     = $data['block_files'];

		// Release confirmation
		if ( $plugin->release_confirmation ) {
			if ( 'trunk' === $stable_tag ) {
				throw new Exception( 'Plugin cannot be released from trunk due to release confirmation being enabled.' );
			}

			$release = Plugin_Directory::get_release( $plugin, $stable_tag );

			// This tag is unknown? Trigger email.
			if ( ! $release ) {
				Plugin_Directory::add_release(
					$plugin,
					[
						'tag'       => $stable_tag,
						'version'   => $headers->Version,
						'committer' => [ $last_committer ],
						'revision'  => [ $last_revision ]
					]
				);

				$email = new Release_Confirmation_Email(
					$plugin,
					Tools::get_plugin_committers( $plugin_slug ),
					[
						'release' => $releases[ $stable_tag ],
						'who'     => $last_committer,
						'readme'  => $readme,
						'headers' => $headers,
					]
				);
				$email->send();

				throw new Exception( 'Plugin release not confirmed; email triggered.' );
			}

			// Check that the tag is approved.
			if ( ! $release['confirmed'] ) {

				if ( ! in_array( $last_committer, $release['committer'], true ) ) {
					$release['committer'][] = $last_committer;
				}
				if ( ! in_array( $last_revision, $release['revision'], true ) ) {
					$release['revision'][] = $last_revision;
				}

				// Update with ^
				Plugin_Directory::add_release( $plugin, $release );

				throw new Exception( 'Plugin release not confirmed.' );
			}

			// At this point we can assume that the release was confirmed, and should be imported.
		}

		$content = '';
		if ( $readme->sections ) {
			foreach ( $readme->sections as $section => $section_content ) {
				$content .= "\n\n<!--section={$section}-->\n{$section_content}";
			}
		} elseif ( ! empty( $headers->Description ) ) {
			$content = "<!--section=description-->\n{$headers->Description}";
		}

		// Fallback to the plugin title if the readme didn't contain it.
		$plugin->post_title   = trim( $readme->name ) ?: strip_tags( $headers->Name ) ?: $plugin->post_title;
		$plugin->post_content = trim( $content ) ?: $plugin->post_content;
		$plugin->post_excerpt = trim( $readme->short_description ) ?: $headers->Description ?: $plugin->post_excerpt;

		/*
		 * Bump last updated if:
		 * - The version has changed.
		 * - The post_modified is empty, which is the case for many initial checkins.
		 * - A tag (or trunk) commit is made to the current stable. The build has changed, even if not new version.
		 */
		if (
			( ! isset( $headers->Version ) || $headers->Version != get_post_meta( $plugin->ID, 'version', true ) ) ||
			$plugin->post_modified == '0000-00-00 00:00:00' ||
			( $svn_changed_tags && in_array( ( $stable_tag ?: 'trunk' ), $svn_changed_tags, true ) )
		) {
			if ( $last_modified ) {
				$plugin->post_modified = $plugin->post_modified_gmt = $last_modified;
			} else {
				$plugin->post_modified = $plugin->post_modified_gmt = current_time( 'mysql' );
			}
		}

		// Plugins should move from 'approved' to 'publish' on first parse
		// `export_and_parse_plugin()` will throw an exception in the case where plugin files cannot be found,
		// so by this time the plugin should be live.
		if ( 'approved' === $plugin->post_status ) {
			$plugin->post_status = 'publish';

			// The post date should be set to when the plugin is first set live.
			$plugin->post_date = $plugin->post_date_gmt = current_time( 'mysql' );
		}

		wp_update_post( $plugin );

		// Set categories if there aren't any yet. wp-admin takes precedent.
		if ( ! wp_get_object_terms( $plugin->ID, 'plugin_category', array( 'fields' => 'ids' ) ) ) {
			wp_set_object_terms( $plugin->ID, Tag_To_Category::map( $readme->tags ), 'plugin_category' );
		}

		// Set tags from the readme
		wp_set_object_terms( $plugin->ID, $readme->tags, 'plugin_tags' );

		// Update the contributors list
		wp_set_object_terms( $plugin->ID, $readme->contributors, 'plugin_contributors' );

		// Update the committers list
		Tools::sync_plugin_committers_with_taxonomy( $plugin->post_name );

		if ( in_array( 'adopt-me', $readme->tags ) ) {
			wp_set_object_terms( $plugin->ID, 'adopt-me', 'plugin_section' );
		} else {
			wp_remove_object_terms( $plugin->ID, 'adopt-me', 'plugin_section' );
		}

		// Update the tested-up-to value
		$tested = $readme->tested;
		if ( function_exists( 'wporg_get_version_equivalents' ) ) {
			foreach ( wporg_get_version_equivalents() as $latest_compatible_version => $compatible_with ) {
				if ( in_array( $readme->tested, $compatible_with, true ) ) {
					$tested = $latest_compatible_version;
					break;
				}
			}
		}

		// Update all readme meta
		foreach ( $this->readme_fields as $readme_field ) {
			$value = ( 'tested' == $readme_field ) ? $tested : $readme->$readme_field;
			update_post_meta( $plugin->ID, $readme_field, wp_slash( $value ) );
		}

		// Store the plugin headers we need. Note that 'Version', 'RequiresWP', and 'RequiresPHP' are handled below.
		foreach ( $this->plugin_headers as $plugin_header => $meta_field ) {
			update_post_meta( $plugin->ID, $meta_field, ( isset( $headers->$plugin_header ) ? wp_slash( $headers->$plugin_header ) : '' ) );
		}

		// Update the Requires and Requires PHP fields, prefering those from the Plugin Headers.
		// Unfortunately the value within $headers is not always a well-formed value.
		$requires     = $readme->requires;
		$requires_php = $readme->requires_php;
		if ( $headers->RequiresWP && preg_match( '!^[\d.]{3,}$!', $headers->RequiresWP ) ) {
			$requires = $headers->RequiresWP;
		}
		if ( $headers->RequiresPHP && preg_match( '!^[\d.]{3,}$!', $headers->RequiresPHP ) ) {
			$requires_php = $headers->RequiresPHP;
		}

		update_post_meta( $plugin->ID, 'requires',           wp_slash( $requires ) );
		update_post_meta( $plugin->ID, 'requires_php',       wp_slash( $requires_php ) );
		update_post_meta( $plugin->ID, 'tagged_versions',    wp_slash( array_keys( $tagged_versions ) ) );
		update_post_meta( $plugin->ID, 'tags',               wp_slash( $tagged_versions ) );
		update_post_meta( $plugin->ID, 'sections',           wp_slash( array_keys( $readme->sections ) ) );
		update_post_meta( $plugin->ID, 'assets_screenshots', wp_slash( $assets['screenshot'] ) );
		update_post_meta( $plugin->ID, 'assets_icons',       wp_slash( $assets['icon'] ) );
		update_post_meta( $plugin->ID, 'assets_banners',     wp_slash( $assets['banner'] ) );
		update_post_meta( $plugin->ID, 'last_updated',       wp_slash( $plugin->post_modified_gmt ) );
		update_post_meta( $plugin->ID, 'plugin_status',      wp_slash( $plugin->post_status ) );

		// Calculate the 'plugin color' from the average color of the banner if provided. This is used for fallback icons.
		$banner_average_color = '';
		if ( $first_banner = reset( $assets['banner'] ) ) {
			// The Banners are not stored locally, which is why a URL is used here
			$banner_average_color = Tools::get_image_average_color( Template::get_asset_url( $plugin, $first_banner, false /* no CDN */ ) );
		}
		update_post_meta( $plugin->ID, 'assets_banners_color', wp_slash( $banner_average_color ) );

		// Store the block data, if known
		if ( count( $blocks ) ) {
			$changed = update_post_meta( $plugin->ID, 'all_blocks', $blocks );
			if ( $changed || count ( get_post_meta( $plugin->ID, 'block_name' ) ) !== count ( $blocks ) ) {
				delete_post_meta( $plugin->ID, 'block_name' );
				delete_post_meta( $plugin->ID, 'block_title' );
				foreach ( $blocks as $block ) {
					add_post_meta( $plugin->ID, 'block_name', $block->name, false );
					add_post_meta( $plugin->ID, 'block_title', ( $block->title ?: $plugin->post_title ), false );
				}
			}
		} else {
			delete_post_meta( $plugin->ID, 'all_blocks' );
			delete_post_meta( $plugin->ID, 'block_name' );
			delete_post_meta( $plugin->ID, 'block_title' );
		}

		// Only store block_files for plugins in the block directory
		if ( count( $block_files ) && has_term( 'block', 'plugin_section', $plugin->ID ) ) {
			update_post_meta( $plugin->ID, 'block_files', $block_files );
		} else {
			delete_post_meta( $plugin->ID, 'block_files' );
		}

		$current_stable_tag = get_post_meta( $plugin->ID, 'stable_tag', true ) ?: 'trunk';

		$this->rebuild_affected_zips( $plugin_slug, $stable_tag, $current_stable_tag, $svn_changed_tags, $svn_revision_triggered );

		// Finally, set the new version live.
		update_post_meta( $plugin->ID, 'stable_tag', wp_slash( $stable_tag ) );
		update_post_meta( $plugin->ID, 'version', wp_slash( $headers->Version ) );

		// Ensure that the API gets the updated data
		API_Update_Updater::update_single_plugin( $plugin->post_name );

		// Import Tide data
		Tide_Sync::sync_data( $plugin->post_name );

		// Run the Block Directory e2e tests if applicable.
		if ( has_term( 'block', 'plugin_section', $plugin->ID ) ) {
			Block_e2e::run( $plugin->post_name );
		}

		return true;
	}

	/**
	 * (Re)build plugin ZIPs affected by this commit.
	 *
	 * @param string $plugin_slug            The plugin slug.
	 * @param string $stable_tag             The new stable tag.
	 * @param string $current_stable_tag     The new stable tag.
	 * @param array  $svn_changed_tags       The list of SVN tags modified since last import.
	 * @param string $svn_revision_triggered The SVN revision which triggered the rebuild.
	 *
	 * @return bool
	 */
	protected function rebuild_affected_zips( $plugin_slug, $stable_tag, $current_stable_tag, $svn_changed_tags, $svn_revision_triggered = 0 ) {
		$versions_to_build = $svn_changed_tags;

		// Ensure that the stable zip is built/rebuilt if need be.
		if ( $stable_tag != $current_stable_tag && ! in_array( $stable_tag, $versions_to_build ) ) {
			$versions_to_build[] = $stable_tag;
		}

		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );

		// Don't rebuild release-confirmation-required tags.
		if ( $plugin->release_confirmation ) {
			foreach ( $versions_to_build as $i => $tag ) {
				$release = Plugin_Directory::get_release( $plugin, $tag );

				if ( ! $release || ( $release['zips_built'] && $release['confirmations_required'] ) ) {
					unset( $versions_to_build[ $i ] );
				} else {
					$release['zips_built'] = true;
					Plugin_Directory::add_release( $release );
				}

			}
		}

		if ( ! $versions_to_build ) {
			return false;
		}

		// Rebuild/Build $build_zips
		try {
			// This will rebuild the ZIP.
			$zip_builder = new Builder();
			$zip_builder->build(
				$plugin_slug,
				array_unique( $versions_to_build ),
				$svn_revision_triggered ?
					"{$plugin_slug}: ZIP build triggered by https://plugins.trac.wordpress.org/changeset/{$svn_revision_triggered}" :
					"{$plugin_slug}: ZIP build triggered by " . php_uname( 'n' ),
				$stable_tag
			);
		} catch ( Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Export a plugin and determine all the information about the current state of the plugin.
	 *
	 * - Creates a /trunk/ export of the plugin.
	 * - Creates a /stable/ export of the stable_tag if specified, falling back to /trunk/.
	 * - Handles readme.md & readme.txt prefering the latter.
	 * - Searches for Screenshots in /$stable/ and in /assets/ (listed remotely).
	 *
	 * @throws \Exception
	 *
	 * @param string $plugin_slug The slug of the plugin to parse.
	 *
	 * @return array {
	 *   'readme', 'stable_tag', 'plugin_headers', 'assets', 'tagged_versions'
	 * }
	 */
	protected function export_and_parse_plugin( $plugin_slug ) {
		$tmp_dir = Filesystem::temp_directory( "process-{$plugin_slug}" );

		// We assume the stable tag is trunk to start with.
		$stable_tag = 'trunk';

		// Find the trunk readme file, list remotely to avoid checking out the entire directory.
		$trunk_files = SVN::ls( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk" ) ?: array();

		// Find the list of tagged versions of the plugin.
		$tagged_versions    = [];
		$tagged_versions_raw = SVN::ls( "https://plugins.svn.wordpress.org/{$plugin_slug}/tags/", true ) ?: [];
		foreach ( $tagged_versions_raw as $entry ) {
			// Discard files
			if ( 'dir' !== $entry['kind'] ) {
				continue;
			}

			$tag = $entry['filename'];

			// Prefix the 0 for plugin versions like 0.1
			if ( '.' == substr( $tag, 0, 1 ) ) {
				$tag = "0{$tag}";
			}

			$tagged_versions[ $tag ] = [
				'tag'    => $entry['filename'],
				'author' => $entry['author'],
				'date'   => $entry['date'],
			];
		}

		// Not all plugins utilise `trunk`, some just tag versions.
		if ( ! $trunk_files ) {
			if ( ! $tagged_versions ) {
				throw new Exception( 'Plugin has no files in trunk, nor tags.' );
			}

			$stable_tag = array_reduce( array_keys( $tagged_versions ), function( $a, $b ) {
				return version_compare( $a, $b, '>' ) ? $a : $b;
			} );
		}

		// A plugin historically doesn't have to have a readme.
		$trunk_readme_files = preg_grep( '!^readme.(txt|md)$!i', $trunk_files );
		if ( $trunk_readme_files ) {
			$trunk_readme_file = reset( $trunk_readme_files );
			// Preference readme.txt over readme.md if both exist.
			foreach ( $trunk_readme_files as $f ) {
				if ( '.txt' == strtolower( substr( $f, -4 ) ) ) {
					$trunk_readme_file = $f;
					break;
				}
			}

			$trunk_readme_file = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk/{$trunk_readme_file}";
			$trunk_readme      = new Parser( $trunk_readme_file );

			$stable_tag = $trunk_readme->stable_tag;
		}

		$svn_info = false;
		if ( $stable_tag && 'trunk' != $stable_tag ) {
			$stable_url = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/tags/{$stable_tag}";
			$svn_info = SVN::info( $stable_url );

			if ( ! $svn_info['result'] && '0.' === substr( $stable_tag, 0, 2 ) ) {
				// Handle tags which we store as 0.blah but are in /tags/.blah
				$_stable_tag = substr( $stable_tag, 1 );
				$stable_url  = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/tags/{$_stable_tag}";
				$svn_info    = SVN::info( $stable_url );
			}

			// Verify that the tag has files, falling back to trunk if not.
			if ( ! SVN::ls( $stable_url ) ) {
				$svn_info = false;
			}
		}

		if ( ! $svn_info || ! $svn_info['result'] ) {
			$stable_tag = 'trunk';
			$stable_url = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk";
			$svn_info   = SVN::info( $stable_url );
		}

		if ( ! $svn_info['result'] ) {
			throw new Exception( 'Could not find stable SVN URL: ' . implode( ' ', reset( $svn_info['errors'] ) ) );
		}

		$last_modified = false;
		if ( preg_match( '/^([0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{1,2}:[0-9]{2}:[0-9]{2})/', $svn_info['result']['Last Changed Date'] ?? '', $m ) ) {
			$last_modified = $m[0];
		}

		$last_committer = $svn_info['result']['Last Changed Author'] ?? '';
		$last_revision  = $svn_info['result']['Last Changed Rev'] ?? 0;

		$svn_export = SVN::export(
			$stable_url,
			$tmp_dir . '/export',
			array(
				'ignore-externals',
			)
		);

		if ( ! $svn_export['result'] || empty( $svn_export['revision'] ) ) {
			// Catch the case where exporting a tag finds nothing, but there was nothing in trunk either.
			if ( ! $trunk_files ) {
				throw new Exception( 'Plugin has no files in trunk, nor tags.' );
			}

			throw new Exception( 'Could not create SVN export: ' . implode( ' ', reset( $svn_export['errors'] ) ) );
		}

		// The readme may not actually exist, but that's okay.
		$readme = $this->find_readme_file( $tmp_dir . '/export' );
		$readme = new Parser( $readme );

		// There must be valid plugin headers though.
		$plugin_headers = $this->find_plugin_headers( "$tmp_dir/export" );
		if ( ! $plugin_headers ) {
			throw new Exception( 'Could not find the plugin headers.' );
		}

		// Now we look in the /assets/ folder for banners, screenshots, and icons.
		$assets = array(
			'screenshot' => array(),
			'banner'     => array(),
			'icon'       => array(),
		);

		$asset_limits = array(
			'screenshot' => 10 * MB_IN_BYTES,
			'banner'     => 4 * MB_IN_BYTES,
			'icon'       => 1 * MB_IN_BYTES,
		);

		$svn_assets_folder = SVN::ls( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/assets/", true /* verbose */ );
		if ( $svn_assets_folder ) { // /assets/ may not exist.
			foreach ( $svn_assets_folder as $asset ) {
				// screenshot-0(-rtl)(-de_DE).(png|jpg|jpeg|gif)  ||  icon.svg
				if ( ! preg_match( '!^(?P<type>screenshot|banner|icon)(?:-(?P<resolution>[\dx]+)(-rtl)?(?:-(?P<locale>[a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?))?\.(png|jpg|jpeg|gif)|\.svg)$!i', $asset['filename'], $m ) ) {
					continue;
				}

				// Don't import oversize assets.
				if ( $asset['filesize'] > $asset_limits[ $m['type'] ] ) {
					continue;
				}

				$type       = $m['type'];
				$filename   = $asset['filename'];
				$revision   = $asset['revision'];
				$location   = 'assets';
				$resolution = isset( $m['resolution'] ) ? $m['resolution'] : false;
				$locale     = isset( $m['locale'] )     ? $m['locale']     : false;

				$assets[ $type ][ $asset['filename'] ] = compact( 'filename', 'revision', 'resolution', 'location', 'locale' );
			}
		}

		// Find screenshots in the stable plugin folder (but don't overwrite /assets/)
		foreach ( Filesystem::list_files( "$tmp_dir/export/", false /* non-recursive */, '!^screenshot-\d+\.(jpeg|jpg|png|gif)$!' ) as $plugin_screenshot ) {
			$filename      = basename( $plugin_screenshot );
			$screenshot_id = substr( $filename, strpos( $filename, '-' ) + 1 );
			$screenshot_id = substr( $screenshot_id, 0, strpos( $screenshot_id, '.' ) );

			if ( isset( $assets['screenshot'][ $filename ] ) ) {
				// Skip it, it exists within /assets/ already
				continue;
			}

			// Don't import oversize assets.
			if ( filesize( $plugin_screenshot ) > $asset_limits['screenshot'] ) {
				continue;
			}

			$assets['screenshot'][ $filename ] = array(
				'filename'   => $filename,
				'revision'   => $svn_export['revision'],
				'resolution' => $screenshot_id,
				'location'   => 'plugin',
			);
		}

		if ( 'trunk' === $stable_tag ) {
			$stable_path = $stable_tag;
		} else {
			$stable_path  = 'tags/';
			$stable_path .= $_stable_tag ?? $stable_tag;
		}

		// Find registered blocks and their files.
		$blocks = array();
		$block_files = array();
		$potential_block_directories = array( '.' );
		$base_dir = "$tmp_dir/export";

		$block_json_files = Filesystem::list_files( $base_dir, true, '!(?:^|/)block\.json$!i' );
		if ( ! empty( $block_json_files ) ) {
			foreach ( $block_json_files as $filename ) {
				$blocks_in_file = $this->find_blocks_in_file( $filename );
				$relative_filename = str_replace( "$base_dir/", '', $filename );
				$potential_block_directories[] = dirname( $relative_filename );
				foreach ( $blocks_in_file as $block ) {
					$blocks[ $block->name ] = $block;

					$extracted_files = $this->extract_file_paths_from_block_json( $block, dirname( $relative_filename ) );
					if ( ! empty( $extracted_files ) ) {
						$block_files = array_merge(
							$block_files,
							array_map(
								function( $file ) use ( $stable_path ) {
									return "/$stable_path/" . ltrim( $file, '\\' );
								},
								$extracted_files
							)
						);
					}
				}
			}
		} else {
			foreach ( Filesystem::list_files( $base_dir, true, '!\.(?:php|js|jsx)$!i' ) as $filename ) {
				$blocks_in_file = $this->find_blocks_in_file( $filename );
				if ( ! empty( $blocks_in_file ) ) {
					$relative_filename = str_replace( "$base_dir/", '', $filename );
					$potential_block_directories[] = dirname( $relative_filename );
					foreach ( $blocks_in_file as $block ) {
						$blocks[ $block->name ] = $block;
					}
				}
			}
		}

		foreach ( $blocks as $block_name => $block ) {
			if ( empty( $block->title ) ) {
				$blocks[ $block_name ]->title = $trunk_readme->name;
			}
		}

		// Only search for block files if none were found in a block.json.
		if ( empty( $block_files ) ) {
			$build_files = array();

			$build_files = self::find_possible_block_assets( $base_dir, $potential_block_directories );

			foreach ( $build_files as $file ) {
				$block_files[] = "/$stable_path/" . ltrim( str_replace( "$base_dir/", '', $file ), '/' );
			}
		}

		// Only allow js or css files
		$block_files = array_unique( array_filter( $block_files, function( $filename ) {
			return preg_match( '!\.(?:js|jsx|css)$!i', $filename );
		} ) );

		return compact( 'readme', 'stable_tag', 'last_modified', 'last_committer', 'last_revision', 'tmp_dir', 'plugin_headers', 'assets', 'tagged_versions', 'blocks', 'block_files' );
	}

	/**
	 * Find the plugin readme file.
	 *
	 * Looks for either a readme.txt or readme.md file, prioritizing readme.txt.
	 *
	 * @param string $directory The Directory to search for the readme in.
	 *
	 * @return string The plugin readme.txt or readme.md filename.
	 */
	static function find_readme_file( $directory ) {
		$files = Filesystem::list_files( $directory, false /* non-recursive */, '!(?:^|/)readme\.(txt|md)$!i' );

		// prioritize readme.txt
		foreach ( $files as $f ) {
			if ( '.txt' == strtolower( substr( $f, -4 ) ) ) {
				return $f;
			}
		}

		return reset( $files );
	}

	/**
	 * Find the plugin headers for the given directory.
	 *
	 * @param string $directory The directory of the plugin.
	 *
	 * @return object The plugin headers.
	 */
	static function find_plugin_headers( $directory ) {
		$files = Filesystem::list_files( $directory, false, '!\.php$!i' );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require ABSPATH . 'wp-admin/includes/plugin.php';
		}

		/*
		 * Sometimes plugins have multiple files which we detect as a plugin based on the headers.
		 * We'll return immediately if the file has a `Plugin Name:` header, otherwise
		 * simply return the last set of headers we come across.
		 */
		$possible_headers = false;
		foreach ( $files as $file ) {
			$data = get_plugin_data( $file, false, false );
			if ( array_filter( $data ) ) {
				if ( $data['Name'] ) {
					return (object) $data;
				} else {
					$possible_headers = (object) $data;
				}
			}
		}

		if ( $possible_headers ) {
			return $possible_headers;
		}

		return false;
	}

	/**
	 * Look for Gutenberg blocks registered within a single file.
	 *
	 * @param string $filename Pathname of the file.
	 *
	 * @return array An array of objects representing blocks, corresponding to the block.json format where possible.
	 */
	static function find_blocks_in_file( $filename ) {

		$ext = strtolower( pathinfo($filename, PATHINFO_EXTENSION) );

		$blocks = array();

		if ( 'js' === $ext || 'jsx' === $ext ) {
			// Parse a js-style registerBlockType() call.
			// Note that this only works with literal strings for the block name and title, and assumes that order.
			$contents = file_get_contents( $filename );
			if ( $contents && preg_match_all( "#registerBlockType[^{}]{0,500}[(]\s*[\"']([-\w]+/[-\w]+)[\"']\s*,\s*[{]\s*title\s*:[\s\w(]*[\"']([^\"']*)[\"']#ms", $contents, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$blocks[] = (object) [
						'name' => $match[1],
						'title' => $match[2],
					];
				}
			}
		}
		if ( 'php' === $ext ) {
			// Parse a php-style register_block_type() call.
			// Again this assumes literal strings, and only parses the name and title.
			$contents = file_get_contents( $filename );
			if ( $contents && preg_match_all( "#register_block_type\s*[(]\s*['\"]([-\w]+/[-\w]+)['\"]#ms", $contents, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$blocks[] = (object) [
						'name' => $match[1],
						'title' => null,
					];
				}
			}
		}
		if ( 'block.json' === basename( $filename ) ) {
			// A block.json file should have everything we want.
			$validator = new Block_JSON\Validator();
			$block     = Block_JSON\Parser::parse( array( 'file' => $filename ) );
			$result    = $validator->validate( $block );
			if ( ! is_wp_error( $block ) && is_wp_error( $result ) ) {
				// Only certain properties must be valid for our purposes here.
				$required_valid_props = array(
					'block.json',
					'block.json:editorScript',
					'block.json:editorStyle',
					'block.json:name',
					'block.json:script',
					'block.json:style',
				);
				$invalid_props = array_intersect( $required_valid_props, $result->get_error_data( 'error' ) );
				if ( empty( $invalid_props ) ) {
					$blocks[] = $block;
				}
			} elseif ( true === $result ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Get script and style file paths from an imported block.json.
	 *
	 * @param object $parsed_json
	 * @param string $block_json_path
	 *
	 * @return array
	 */
	static function extract_file_paths_from_block_json( $parsed_json, $block_json_path = '' ) {
		$files = array();

		$props = array( 'editorScript', 'script', 'editorStyle', 'style' );

		foreach ( $props as $prop ) {
			if ( isset( $parsed_json->$prop ) ) {
				$files[] = trailingslashit( $block_json_path ) . $parsed_json->$prop;
			}
		}

		return $files;
	}

	/**
	 * Find likely JS and CSS block asset files in a given directory.
	 *
	 * @param string $base_dir Base path in which to search.
	 * @param array $potential_block_directories Subdirectories likely to contain block assets, if known. Optional.
	 *
	 * @return array
	 */
	static function find_possible_block_assets( $base_dir, $potential_block_directories = null ) {
		if ( empty( $potential_block_directories ) || !is_array( $potential_block_directories ) ) {
			$potential_block_directories = array( '.' );
		}

		$build_files = array();

		foreach ( $potential_block_directories as $block_dir ) {
			// dirname() returns . when there is no directory separator present.
			if ( '.' === $block_dir ) {
				$block_dir = '';
			}

			// First look for a dedicated "build" or "dist" directory.
			foreach ( array( 'build', 'dist' ) as $dirname ) {
				if ( is_dir( "$base_dir/$block_dir/$dirname" ) ) {
					$build_files += Filesystem::list_files( "$base_dir/$block_dir/$dirname", true, '!\.(?:js|jsx|css)$!i' );
				}
			}

			// There must be at least on JS file, so if only css was found, keep looking.
			if ( empty( preg_grep( '!\.(?:js|jsx)$!i', $build_files ) ) ) {
				// Then check for files in the current directory with "build" or "min" in the filename.
				$build_files += Filesystem::list_files( "$base_dir/$block_dir", false, '![_\-\.]+(?:build|dist|min)[_\-\.]+!i' );
			}

			if ( empty( preg_grep( '!\.(?:js|jsx)$!i', $build_files ) ) ) {
				// Finally, just grab whatever js/css files there are in the current directory.
				$build_files += Filesystem::list_files( "$base_dir/$block_dir", false, '#(?<!webpack\.config)\.(?:js|jsx|css)$#i' );
			}
		}

		if ( empty( preg_grep( '!\.(?:js|jsx)$!i', $build_files ) ) ) {
			// Nothing in the potential block directories. Check if we somehow missed build/dist directories in the root.
			foreach ( array( 'build', 'dist' ) as $dirname ) {
				if ( is_dir( "$base_dir/$dirname" ) ) {
					$build_files += Filesystem::list_files( "$base_dir/$dirname", true, '!\.(?:js|jsx|css)$!i' );
				}
			}
		}

		if ( empty( preg_grep( '!\.(?:js|jsx)$!i', $build_files ) ) ) {
			// Still nothing. Take on last wild swing.
			$build_files += Filesystem::list_files( $base_dir, false, '!\.(?:js|jsx|css)$!i' );
		}

		return array_unique( $build_files );
	}
}
