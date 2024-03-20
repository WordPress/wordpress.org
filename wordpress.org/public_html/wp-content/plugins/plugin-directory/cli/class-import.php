<?php
namespace WordPressdotorg\Plugin_Directory\CLI;

use Exception;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Jobs\Tide_Sync;
use WordPressdotorg\Plugin_Directory\Block_JSON;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Email\Release_Confirmation as Release_Confirmation_Email;
use WordPressdotorg\Plugin_Directory\Readme\{ Parser as Readme_Parser, Validator as Readme_Validator };
use WordPressdotorg\Plugin_Directory\Standalone\Plugins_Info_API;
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
		// 'Version'         => 'version',
		// 'RequiresWP'      => 'requires',
		// 'RequiresPHP'     => 'requires_php',
		// 'RequiresPlugins' => 'requires_plugins'
	);

	/**
	 * List of warnings generated during the import process.
	 *
	 * @var array
	 */
	public $warnings = array();

	/**
	 * The last plugin imported.
	 *
	 * @var \WP_Post
	 */
	public $plugin;

	/**
	 * Process an import for a Plugin into the Plugin Directory.
	 *
	 * @throws \Exception
	 *
	 * @param string $plugin_slug            The slug of the plugin to import.
	 * @param array  $svn_changed_tags       A list of tags/trunk which the SVN change touched. Optional.
	 * @param array  $svn_revision_triggered The SVN revision which this import has been triggered by. Optional.
	 */
	public function import_from_svn( $plugin_slug, $svn_changed_tags = array( 'trunk' ), $svn_revision_triggered = 0 ) {
		// Reset properties.
		$this->warnings = [];

		$plugin = $this->plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $plugin ) {
			throw new Exception( 'Unknown Plugin' );
		}

		$data = $this->export_and_parse_plugin( $plugin_slug );

		$readme             = $data['readme'];
		$assets             = $data['assets'];
		$headers            = $data['plugin_headers'];
		$stable_tag         = $data['stable_tag'];
		$last_committer     = $data['last_committer'];
		$last_revision      = $data['last_revision'];
		$tagged_versions    = $data['tagged_versions'];
		$last_modified      = $data['last_modified'];
		$blocks             = $data['blocks'];
		$block_files        = $data['block_files'];
		$current_stable_tag = get_post_meta( $plugin->ID, 'stable_tag', true ) ?: 'trunk';
		$touches_stable_tag = (bool) array_intersect( [ $stable_tag, $current_stable_tag ], $svn_changed_tags );

		// If the readme generated any warnings, raise it to self::$import_warnings;
		if ( $readme->warnings ) {
			// Convert the warnings to a human readable format.
			$readme_warnings = Readme_Validator::instance()->validate_content( $readme->raw_contents );

			foreach ( [ 'errors', 'warnings' ] as $field ) {
				foreach ( $readme_warnings[ $field ] ?? [] as $warning ) {
					$this->warnings[] = "Readme: {$warning}";
				}
			}
		}

		// Validate various headers:

		/*
		 * Check to see if the plugin is using the `Update URI` header.
		 *
		 * Plugins on WordPress.org should NOT use this header, but we do accept some URI formats for it in the API,
		 * so those are allowed to pass here.
		 * Any documentation suggesting that a WordPress.org hosted plugin should use this header is incorrect.
		 */
		if ( $headers->UpdateURI ) {
			$update_uri_valid = preg_match( '!^(https?://)?(wordpress.org|w.org)/plugins?/(?P<slug>[^/]+)/?$!i', $headers->UpdateURI, $update_uri_matches );
			if ( ! $update_uri_valid || $update_uri_matches['slug'] !== $plugin_slug ) {
				$this->warnings['invalid_update_uri'] = 'Invalid Update URI header detected: ' . $headers->UpdateURI;

				throw new Exception( $this->warnings['invalid_update_uri'] );
			}
		}

		$_requires_plugins = array_filter( array_map( 'trim', explode( ',', $headers->RequiresPlugins ) ) );
		$requires_plugins     = [];
		$unmet_dependencies   = [];
		foreach ( $_requires_plugins as $requires_plugin_slug ) {
			$requires_plugin_post = Plugin_Directory::get_plugin_post( $requires_plugin_slug );

			// get_plugin_post() will resolve some edge-cases, but we only want exact slug-matches, anything else is wrong.
			if (
				$requires_plugin_post &&
				$requires_plugin_slug === $requires_plugin_post->post_name &&
				'publish' === $requires_plugin_post->post_status
			) {
				$requires_plugins[] = $requires_plugin_post->post_name;
			} else {
				$unmet_dependencies[] = $requires_plugin_slug;
			}
		}

		if ( $unmet_dependencies ) {
			$this->warnings['unmet_dependencies'] = 'Invalid plugin dependencies specified. The following dependencies could not be resolved: ' . implode( ', ', $requires_plugins_unmet );

			throw new Exception( $this->warnings['unmet_dependencies'] );
		}
		unset( $_requires_plugins, $unmet_dependencies );

		// Release confirmation
		if ( $plugin->release_confirmation ) {
			if ( 'trunk' === $stable_tag ) {
				throw new Exception( 'Plugin cannot be released from trunk due to release confirmation being enabled.' );
			}

			// Check to see if the commit has touched tags that don't have known confirmed releases.
			foreach ( $svn_changed_tags as $svn_changed_tag ) {
				if ( 'trunk' === $svn_changed_tag ) {
					continue;
				}

				$release = Plugin_Directory::get_release( $plugin, $svn_changed_tag );
				if ( ! $release ) {
					// Use the actual version for stable releases, otherwise fallback to the tag name, as we don't have the actual header data.
					$version = ( $svn_changed_tag === $stable_tag ) ? $headers->Version : $svn_changed_tag;

					Plugin_Directory::add_release(
						$plugin,
						[
							'tag'       => $svn_changed_tag,
							'version'   => $version,
							'committer' => [ $last_committer ],
							'revision'  => [ $last_revision ]
						]
					);

					$email = new Release_Confirmation_Email(
						$plugin,
						Tools::get_plugin_committers( $plugin_slug ),
						[
							'who'     => $last_committer,
							'readme'  => $readme,
							'headers' => $headers,
							'version' => $version,
						]
					);
					$email->send();

					echo "Plugin release {$svn_changed_tag} not confirmed; email triggered.\n";
				}
			}

			// Now check to see if the stable has been confirmed.
			$release = Plugin_Directory::get_release( $plugin, $stable_tag );
			if ( ! $release ) {
				throw new Exception( "Plugin release {$stable_tag} not found." );
			}

			/*
			 * If the stable release isn't confirmed, the next section will abort processing,
			 * but if this commit didn't touch a stable tag, but rather a confirmed release tag,
			 * then we need to build a new zip for that tag.
			 *
			 * This is required as ZIP building occurs at the end of the import process, yet with
			 * release confirmations the 
			 */
			if ( ! $release['confirmed'] && ! $touches_stable_tag ) {
				$zips_to_build = [];
				foreach ( $svn_changed_tags as $svn_changed_tag ) {
					// We're not concerned with trunk or stable tags.
					if ( 'trunk' === $svn_changed_tag || $svn_changed_tag === $stable_tag ) {
						continue;
					}

					$this_release = Plugin_Directory::get_release( $plugin, $svn_changed_tag );
					if ( $this_release['confirmed'] && ! $this_release['zips_built'] ) {
						$zips_to_build[] = $this_release['tag'];
					}
				}

				if ( $zips_to_build ) {
					// NOTE: $stable_tag not passed, as it's not yet stable and won't be.
					$this->rebuild_affected_zips( $plugin_slug, $current_stable_tag, $current_stable_tag, $zips_to_build, $svn_revision_triggered );
				}
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

				throw new Exception( "Plugin release {$stable_tag} not confirmed." );
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

		// Use the Readme name, as long as it's not the plugin slug.
		if (
			$readme->name &&
			$readme->name !== $plugin->post_name
		) {
			$plugin->post_title = $readme->name;
		} elseif ( $headers->Name ) {
			$plugin->post_title = strip_tags( $headers->Name );
		}

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

		// Keep a log of all plugin names used by the plugin over time.
		$plugin_names = get_post_meta( $plugin->ID, 'plugin_name_history', true ) ?: [];
		if ( ! isset( $plugin_names[ $headers->Name ] ) ) {
			// [ 'Plugin Name' => '1.2.3', 'Plugin New Name' => '4.5.6' ]
			$plugin_names[ $headers->Name ] = $headers->Version;
			update_post_meta( $plugin->ID, 'plugin_name_history', wp_slash( $plugin_names ) );
		}

		update_post_meta( $plugin->ID, 'requires_plugins',   wp_slash( $requires_plugins ) );
		update_post_meta( $plugin->ID, 'requires',           wp_slash( $requires ) );
		update_post_meta( $plugin->ID, 'requires_php',       wp_slash( $requires_php ) );
		update_post_meta( $plugin->ID, 'tagged_versions',    wp_slash( array_keys( $tagged_versions ) ) );
		update_post_meta( $plugin->ID, 'sections',           wp_slash( array_keys( $readme->sections ) ) );
		update_post_meta( $plugin->ID, 'assets_screenshots', wp_slash( $assets['screenshot'] ) );
		update_post_meta( $plugin->ID, 'assets_icons',       wp_slash( $assets['icon'] ) );
		update_post_meta( $plugin->ID, 'assets_banners',     wp_slash( $assets['banner'] ) );
		update_post_meta( $plugin->ID, 'last_updated',       wp_slash( $plugin->post_modified_gmt ) );

		// Calculate the 'plugin color' from the average color of the banner if provided. This is used for fallback icons.
		$banner_average_color = '';
		if ( $first_banner = reset( $assets['banner'] ) ) {
			// The Banners are not stored locally, which is why a URL is used here
			$banner_average_color = Tools::get_image_average_color( Template::get_asset_url( $plugin, $first_banner, false /* no CDN */ ) );
		}
		update_post_meta( $plugin->ID, 'assets_banners_color', wp_slash( $banner_average_color ) );

		// Store the content of blueprint files, if they're available and valid.
		if ( isset( $assets['blueprint'] ) && count( $assets['blueprint'] ) > 0 ) {
			update_post_meta( $plugin->ID, 'assets_blueprints', wp_slash( $assets['blueprint'] ) );
		} else {
			delete_post_meta( $plugin->ID, 'assets_blueprints' );
			// TODO: maybe if ( $touches_stable_tag )?
			add_post_meta( $plugin->ID, '_missing_blueprint_notice', 1, true );
		}

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

		$this->rebuild_affected_zips( $plugin_slug, $stable_tag, $current_stable_tag, $svn_changed_tags, $svn_revision_triggered );

		// Finally, set the new version live.
		update_post_meta( $plugin->ID, 'stable_tag', wp_slash( $stable_tag ) );
		update_post_meta( $plugin->ID, 'version',    wp_slash( $headers->Version ) );
		// Update the list of tags last, as it controls which ZIPs are present in the 'Previous versions' section and info API.
		update_post_meta( $plugin->ID, 'tags',       wp_slash( $tagged_versions ) );

		// Ensure that the API gets the updated data
		API_Update_Updater::update_single_plugin( $plugin->post_name );
		Plugins_Info_API::flush_plugin_information_cache( $plugin->post_name );

		// Import Tide data
		Tide_Sync::sync_data( $plugin->post_name );

		// Run the Block Directory e2e tests if applicable.
		if ( has_term( 'block', 'plugin_section', $plugin->ID ) ) {
			Block_e2e::run( $plugin->post_name );
		}

		/**
		 * Action that fires after a plugin is imported.
		 *
		 * @param WP_Post $plugin         The plugin updated.
		 * @param string  $stable_tag     The new stable tag for the plugin.
		 * @param string  $old_stable_tag The previous stable tag for the plugin.
		 * @param array   $changed_tags   The list of SVN tags/trunk affected to trigger the import.
		 * @param int     $svn_revision   The SVN revision that triggered the import.
		 * @param array   $warnings       The list of warnings generated during the import process.
		 */
		do_action( 'wporg_plugins_imported', $plugin, $stable_tag, $current_stable_tag, $svn_changed_tags, $svn_revision_triggered, $this->warnings );

		return true;
	}

	/**
	 * (Re)build plugin ZIPs affected by this commit.
	 *
	 * @param string $plugin_slug            The plugin slug.
	 * @param string $stable_tag             The new stable tag.
	 * @param string $current_stable_tag     The current stable tag.
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
				// Trunk should always be built, and will never be set as the stable tag when confirmations are enabled.
				if ( 'trunk' === $tag ) {
					continue;
				}

				$release = Plugin_Directory::get_release( $plugin, $tag );

				if (
					// If the release isn't known, skip.
					! $release ||
					// If the release isn't confirmed AND confirmations were required, skip.
					( ! $release['confirmed'] && $release['confirmations_required'] ) ||
					// If the release has had its ZIPs built, skip if it required confirmations.
					( $release['zips_built'] && $release['confirmations_required'] )
				) {
					unset( $versions_to_build[ $i ] );
				} else {
					$release['zips_built'] = true;
					Plugin_Directory::add_release( $plugin, $release );
				}
			}

			if ( $versions_to_build ) {
				echo "Building ZIPs for {$plugin_slug}: " . implode( ', ', $versions_to_build ) . "\n";
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
			$trunk_readme      = new Readme_Parser( $trunk_readme_file );

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
		$readme = new Readme_Parser( $readme );

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
			'blueprint'  => array(),
		);

		$asset_limits = array(
			'screenshot' => 10 * MB_IN_BYTES,
			'banner'     => 4 * MB_IN_BYTES,
			'icon'       => 1 * MB_IN_BYTES,
			'blueprint'  => 100 * KB_IN_BYTES,
		);

		$svn_blueprints_folder = null;
		$svn_assets_folder = SVN::ls( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/assets/", true /* verbose */ );
		if ( $svn_assets_folder ) { // /assets/ may not exist.
			foreach ( $svn_assets_folder as $asset ) {
				if ( 'blueprints' === $asset['filename'] ) {
					$svn_blueprints_folder = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/assets/blueprints/";
					continue;
				}

				// screenshot-0(-rtl)(-de_DE).(png|jpg|jpeg|gif) || banner-772x250.PNG || icon.svg
				if ( ! preg_match( '!^(?P<type>screenshot|banner|icon)(?:-(?P<resolution>\d+(?:\D\d+)?)(-rtl)?(?:-(?P<locale>[a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?))?\.(png|jpg|jpeg|gif)|\.svg)$!iu', $asset['filename'], $m ) ) {
					continue;
				}

				$type = strtolower( $m['type'] );

				// Don't import oversize assets.
				if ( $asset['filesize'] > $asset_limits[ $type ] ) {
					continue;
				}

				$filename   = $asset['filename'];
				$revision   = $asset['revision'];
				$location   = 'assets';
				$resolution = isset( $m['resolution'] ) ? $m['resolution'] : false;
				$locale     = isset( $m['locale'] )     ? $m['locale']     : false;

				// Ensure the resolution key is in the expected 123x123 format.
				// Resolution is also the screenshot number, in which case it's stringy numeric only.
				if ( $resolution && 'screenshot' === $type ) {
					$resolution = (string)( (int) $resolution );
				} else if ( $resolution ) {
					$resolution = preg_replace( '/[^0-9]/u', 'x', $resolution );
				}

				$assets[ $type ][ $asset['filename'] ] = compact( 'filename', 'revision', 'resolution', 'location', 'locale' );
			}
		}

		if ( $svn_blueprints_folder ) {
			$svn_export = SVN::export(
				$svn_blueprints_folder,
				$tmp_dir . '/blueprints',
				array(
					'ignore-externals',
				)
			);

			foreach ( Filesystem::list_files( "$tmp_dir/blueprints/", false /* non-recursive */, '!^blueprint[-\w]*\.json$!' ) as $plugin_blueprint ) {
				$filename = basename( $plugin_blueprint );

				// Don't import oversize blueprints
				if ( filesize( $plugin_blueprint ) > $asset_limits['blueprint'] ) {
					continue;
				}

				// Make sure the blueprint file is valid json and contains the essentials; also minimize whitespace etc.
				$contents = self::normalize_blueprint_json( file_get_contents( $plugin_blueprint ), $plugin_slug );
				if ( !$contents ) {
					continue;
				}

				$assets['blueprint'][ $filename ] = array(
					'filename'   => $filename,
					'revision'   => $svn_export['revision'],
					'resolution' => false,
					'location'   => 'assets',
					'locale'     => '',
					'contents'   => $contents
				);
			}

			// For the time being, limit the number of blueprints. Revise this when the case for multiple blueprints is more clear.
			if ( isset( $assets['blueprint'] ) && count ( $assets['blueprint'] ) > 10 ) {
				$assets['blueprint'] = array_slice( $assets['blueprint'], 0, 10, true );
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
						if ( isset( $blocks[ $block->name ] ) ) {
							$blocks[ $block->name ] = (object) array_merge( (array) $blocks[ $block->name ], array_filter( (array) $block ) );
						} else {
							$blocks[ $block->name ] = $block;
						}
					}
				}
			}
		}

		foreach ( $blocks as $block_name => $block ) {
			if ( empty( $block->title ) ) {
				$blocks[ $block_name ]->title = $readme->name;
			}
		}

		// Remove any core blocks from the block list.
		$blocks = array_filter(
			$blocks,
			function( $block_name ) {
				return 0 !== strpos( $block_name, 'core/' );
			},
			ARRAY_FILTER_USE_KEY
		);

		// Filter the blocks list so that the parent block is first.
		if ( count( $blocks ) > 1 ) {
			$children = array_filter(
				$blocks,
				function( $block ) {
					return isset( $block->parent ) && count( $block->parent );
				}
			);

			$parent = array_filter(
				$blocks,
				function( $block ) {
					return ! isset( $block->parent ) || ! count( $block->parent );
				}
			);

			$blocks = array_merge( $parent, $children );
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
	public static function find_readme_file( $directory ) {
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
	 * @param int    $max_depth The maximum depth to search for files. Default: current directory only.
	 *
	 * @return object The plugin headers.
	 */
	public static function find_plugin_headers( $directory, $max_depth = -1 ) {
		$files = Filesystem::list_files( $directory, ( $max_depth > 0 ), '!\.php$!i', $max_depth );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Add any additional headers required.
		add_filter( 'extra_plugin_headers', array( __CLASS__, 'add_extra_plugin_headers' ) );

		/*
		 * Sometimes plugins have multiple files which we detect as a plugin based on the headers.
		 * We'll break immediately if the file has a `Plugin Name:` header, otherwise
		 * simply return the last set of headers we come across.
		 */
		$headers = false;
		foreach ( $files as $file ) {
			$data = get_plugin_data( $file, false, false );
			if ( array_filter( $data ) ) {
				$data['PluginFile'] = $file;
				$headers            = $data;

				if ( $headers['Name'] ) {
					break;
				}
			}
		}

		remove_filter( 'extra_plugin_headers', array( __CLASS__, 'add_extra_plugin_headers' ) );

		if ( ! $headers ) {
			return false;
		}

		// The extra_plugin_headers filter doesn't let you set the key.
		foreach ( self::add_extra_plugin_headers( [] ) as $key => $header ) {
			if (
				$key != $header &&
				! isset( $headers[ $key ] ) &&
				isset( $headers[ $header ] )
			) {
				$headers[ $key ] = $headers[ $header ];
				unset( $headers[ $header ] );
			}
		}

		return (object) $headers;
	}

	/**
	 * Add support for additional plugin headers prior to WordPress supporting it.
	 *
	 * @param array $headers The headers to look for in plugins.
	 * @return array
	 */
	public static function add_extra_plugin_headers( $headers ) {
		// WordPress Plugin Dependencies - See https://meta.trac.wordpress.org/ticket/6921
		if ( ! isset( $headers['RequiresPlugins'] ) ) {
			$headers['RequiresPlugins'] = 'Requires Plugins';
		}

		return $headers;
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
					'block.json[editorScript]',
					'block.json[editorStyle]',
					'block.json[name]',
					'block.json[script]',
					'block.json[style]',
				);
				$error = $result->get_error_message();
				$is_json_valid = array_reduce(
					$required_valid_props,
					function( $is_valid, $prop ) use ( $error ) {
						$prop_field = substr( $prop, 11, -1 ); // 'name' in 'block.json[name]'
						return (
							$is_valid &&
							( false === strpos( $error, $prop ) ) &&
							// String in rest_validate_object_value_from_schema()
							( false === strpos( $error, "{$prop_field} is a required property of block.json." ) )
						);
					},
					true
				);
				if ( $is_json_valid ) {
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
				foreach ( (array) $parsed_json->$prop as $file ) {
					if ( str_starts_with( $file, 'file:' ) || str_contains( $file, '.' ) ) {
						$files[] = trailingslashit( $block_json_path ) . remove_block_asset_path_prefix( $file );
					} else {
						// script handle.. not handled.
					}
				}
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

	static function normalize_blueprint_json( $blueprint_file_contents, $plugin_slug ) {
		$decoded_file = json_decode( $blueprint_file_contents, true );

		$contents = false;
		if ( is_array( $decoded_file ) && JSON_ERROR_NONE === json_last_error() ) {

			$has_self_install_step = false;
			if ( isset( $decoded_file[ 'steps' ] ) ) {
				foreach ( $decoded_file[ 'steps' ] as &$step ) {
					// Normalize a "install plugin from url" to a install-by-slug.
					if (
						'installPlugin' === $step['step'] &&
						isset( $step['pluginZipFile']['url'] ) &&
						preg_match( '!^https?://downloads\.wordpress\.org/plugin/(?P<slug>[a-z0-9-_]+)(\.(?P<version>.+?))?\.zip($|[?])!i', $step['pluginZipFile']['url'], $m )
					) {
						$step[ 'pluginZipFile' ] = [
							'resource' => 'wordpress.org/plugins',
							'slug'     => $m['slug']
						];
					}

					// Normalise a "install theme from url" to a install-by-slug.
					if (
						'installTheme' === $step['step'] &&
						isset( $step['themeZipFile']['url'] ) &&
						preg_match( '!^https?://downloads\.wordpress\.org/theme/(?P<slug>[a-z0-9-_]+)(\.(?P<version>.+?))?\.zip($|[?])!i', $step['themeZipFile']['url'], $m )
					) {
						$step[ 'themeZipFile' ] = [
							'resource' => 'wordpress.org/themes',
							'slug'     => $m['slug']
						];
					}

					// Check if this is a "install this plugin" step.
					if (
						'installPlugin' === $step['step'] &&
						isset( $step['pluginZipFile']['slug'] ) &&
						$plugin_slug === $step['pluginZipFile']['slug']
					) {
						$has_self_install_step = true;

						if ( true != $step['options']['activate'] ) {
							$step[ 'options' ][ 'activate' ] = true;
						}
					}
				}
			}

			// Akismet is a special case because the plugin is bundled with WordPress.
			if ( ! $has_self_install_step && 'akismet' !== $plugin_slug ) {
				$decoded_file['steps'][] = array(
					'step' => 'installPlugin',
					'pluginZipFile' => array(
						'resource' => 'wordpress.org/plugins',
						'slug'     => $plugin_slug,
					),
					'options' => array(
						'activate' => true,
					)
				);
			}


			$contents = json_encode( $decoded_file ); // Re-encode to minimize whitespace
		}

		return $contents;
	}
}
