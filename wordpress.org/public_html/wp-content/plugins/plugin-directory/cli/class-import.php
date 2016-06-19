<?php
namespace WordPressdotorg\Plugin_Directory\CLI;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Readme\Parser;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use Exception;

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
	var $readme_fields = array(
		'tested',
		'requires',
		'donate_link',
		'upgrade_notice',
		'contributors',
		'screenshots'
	);

	// Plugin headers that are stored in plugin meta
	var $plugin_headers = array(
		// Header    => meta_key
		'Name'       => 'header_name',
		'PluginURI'  => 'header_plugin_uri',
		'Version'    => 'version',
		'Author'     => 'header_author',
		'AuthorURI'  => 'header_author_uri',
		'TextDomain' => 'header_textdomain'
	);

	/**
	 * Process an import for a Plugin into the Plugin Directory.
	 *
	 * @throws \Exception
	 *
	 * @param string $plugin_slug      The slug of the plugin to import.
	 * @param array  $svn_changed_tags A list of tags/trunk which the SVN change touched. Optional.
	 */
	public function import_from_svn( $plugin_slug, $svn_changed_tags = array( 'trunk' ) ) {
		global $wpdb;

		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $plugin ) {
// TODO			throw new Exception( "Unknown Plugin" );
		}

		$data = $this->export_and_parse_plugin( $plugin_slug );

	// TODO: During development while the bbPress variant is still running, we'll pull details from it and allow importing of any plugin.
		$topic = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PLUGINS_TABLE_PREFIX . 'topics WHERE topic_slug = %s', $plugin_slug ) );

		if ( ! $plugin && ! $topic ) {
			throw new Exception( "Unknown Plugin" );
		}

		// TODO: During development we're just going to import status from bbPress.
		$status = 'publish';
		if ( 2 == $topic->topic_open ) {
			$status = 'approved';
		} elseif ( 2 == $topic->forum_id ) {
			$status = 'pending';
		} elseif ( 4 == $topic->forum_id || 'rejected-' == substr( $topic->topic_slug, 0, 9 ) ) {
			$status = 'rejected';
		} elseif ( 1 == $topic->forum_id && 0 == $topic->topic_open ) {
			$status = 'closed';
		} elseif ( 3 == $topic->topic_open ) {
			$status = 'disabled';
		}

		if ( ! $plugin ) {
			$author_ip = $wpdb->get_var( $wpdb->prepare( 'SELECT poster_ip FROM ' . PLUGINS_TABLE_PREFIX . 'posts WHERE topic_id = %s', $topic->topic_id ) );

			$plugin = Plugin_Directory::create_plugin_post( array(
				'post_name' => $plugin_slug,
				'post_status' => $status,
				'post_author' => $topic->topic_poster,
				'override_modified_date' => true,
				'post_date_gmt' => $topic->topic_start_time,
				'post_date' => $topic->topic_start_time,
				'post_modified' => $topic->topic_time,
				'post_modified_gmt' => $topic->topic_time,
				'meta_input' => array(
					'_author_ip' => $author_ip,
					'_publish'   => $topic->approved,
				),
			) );
		}

		$readme = $data['readme'];
		$assets = $data['assets'];
		$headers = $data['plugin_headers'];
		$stable_tag = $data['stable_tag'];
		$tagged_versions = $data['tagged_versions'];

		$content = '';
		if ( $readme->sections ) {
			foreach ( $readme->sections as $section => $section_content ) {
				$content .= "\n\n<!--section={$section}-->\n{$section_content}";
			}
		} elseif ( !empty( $headers->Description ) ) {
			$content = "<!--section=description-->\n{$headers->Description}";
		}

		// Fallback to the plugin title if the readme didn't contain it.
		$plugin->post_title   = trim( $readme->name ) ?: strip_tags( $headers->Name ) ?: $plugin->post_title;
		$plugin->post_content = trim( $content ) ?: $plugin->post_content;
		$plugin->post_excerpt = trim( $readme->short_description ) ?: $headers->Description ?: $plugin->post_excerpt;

		$plugin->post_date     = $topic->topic_start_time;
		$plugin->post_date_gmt = $topic->topic_start_time;
		$plugin->override_modified_date = true;
		$plugin->post_modified     = $topic->topic_time;
		$plugin->post_modified_gmt = $topic->topic_time;

		$plugin->post_status = $status;
		if ( ! $plugin->post_title ) {
			$plugin->post_title = $topic->topic_title;
		}

		// Bump last updated if the version has changed.
		if ( !isset( $headers->Version ) || $headers->Version != get_post_meta( $plugin->ID, 'version', true ) ) {
			$plugin->post_modified = $plugin->post_modified_gmt = current_time( 'mysql' );
		}

		add_filter( 'wp_insert_post_data', array( $this, 'filter_wp_insert_post_data' ), 10, 2 );
		wp_update_post( $plugin );
		remove_filter( 'wp_insert_post_data', array( $this, 'filter_wp_insert_post_data' ) );

		// Set categories if there aren't any yet. wp-admin takes precedent.
		if ( ! wp_get_post_terms( $plugin->ID, 'plugin_category', array( 'fields' => 'ids' ) ) ) {
			wp_set_post_terms( $plugin->ID, Tag_To_Category::map( $readme->tags ), 'plugin_category' );
		}

		if ( in_array( 'adopt-me', $readme->tags ) ) {
			wp_set_post_terms( $plugin->ID, array( 74 /* Term ID for adopt-me */ ), 'plugin_section' );
		}

		foreach ( $this->readme_fields as $readme_field ) {
			// Don't change the tested version if a newer version was specified through wp-admin
			if ( 'tested' == $readme_field && version_compare( get_post_meta( $plugin->ID, 'tested', true ), $readme->$readme_field, '>' ) ) {
				continue;
			}

			update_post_meta( $plugin->ID, $readme_field, wp_slash( $readme->$readme_field ) );
		}

		foreach ( $this->plugin_headers as $plugin_header => $meta_field ) {
			update_post_meta( $plugin->ID, $meta_field, ( isset( $headers->$plugin_header ) ? wp_slash( $headers->$plugin_header ) : '' ) );
		}

		update_post_meta( $plugin->ID, 'tagged_versions',    wp_slash( $tagged_versions ) );
		update_post_meta( $plugin->ID, 'sections',           wp_slash( array_keys( $readme->sections ) ) );
		update_post_meta( $plugin->ID, 'assets_screenshots', wp_slash( $assets['screenshot'] ) );
		update_post_meta( $plugin->ID, 'assets_icons',       wp_slash( $assets['icon'] ) );
		update_post_meta( $plugin->ID, 'assets_banners',     wp_slash( $assets['banner'] ) );

		// Calculate the 'plugin color' from the average color of the banner if provided. This is used for fallback icons.
		$banner_average_color = '';
		if ( $first_banner = reset( $assets['banner'] ) ) {
			// The Banners are not stored locally, which is why a URL is used here
			$banner_average_color = Tools::get_image_average_color( Template::get_asset_url( $plugin_slug, $first_banner ) );
		}
		update_post_meta( $plugin->ID, 'assets_banners_color', wp_slash( $banner_average_color ) );

		// Give committers a role on this site.
		foreach ( Tools::get_plugin_committers( $plugin_slug ) as $committer ) {
			$user = get_user_by( 'login', $committer );

			if ( $user && ! user_can( $user, 'plugin_dashboard_access' ) ) {
				$user->add_role( 'plugin_committer' );
			}
		}

		$current_stable_tag = get_post_meta( $plugin->ID, 'stable_tag', true );

		$this->rebuild_invalidate_zips( $plugin_slug, $stable_tag, $current_stable_tag, $svn_changed_tags );

		// Finally, set the new version live.
		update_post_meta( $plugin->ID, 'stable_tag', $stable_tag );

		// Update Jetpack Search
		\Jetpack::$instance->sync->register_post( $plugin->ID );
	}

	/**
	 * Rebuild and Invalidate plugin ZIPs on all web nodes using the REST API Endpoints.
	 *
	 * @param string $plugin_slug        The plugin slug.
	 * @param string $stable_tag         The new stable tag.
	 * @param string $current_stable_tag The new stable tag.
	 * @param array  $svn_changed_tags   The list of SVN tags modified since last import.
	 */
	protected function rebuild_invalidate_zips( $plugin_slug, $stable_tag, $current_stable_tag, $svn_changed_tags ) {
		global $wporg_webs;
		$invalidate_zips = $rebuild_zips = array();

		foreach ( $svn_changed_tags as $tag ) {
			if ( 'trunk' == $tag ) {
				if ( 'trunk' == $stable_tag ) {
					// Trunk is stable, so we'll need to rebuild the zip
					$rebuild_zips[] = "{$plugin_slug}.zip";
				} else {
					// Trunk isn't stable, so we'll just remove it so it's rebuilt on demand
					$invalidate_zips[] = "{$plugin_slug}.zip";
				}
				continue;
			}
			if ( $tag == $stable_tag || $tag == $current_stable_tag ) {
				$rebuild_zips[] = "{$plugin_slug}.{$tag}.zip";
			} else {
				$invalidate_zips[] = "{$plugin_slug}.{$tag}.zip";
			}
		}
		if ( $stable_tag != $current_stable_tag ) {
			// plugin is updated, ensure that everything is rebuilt.
			if ( ! in_array( $stable_tag, $svn_changed_tags ) ) {
				$rebuild_zips[] = "{$plugin_slug}" . ( 'trunk' == $tag ? '' : ".{$stable_tag}" ) . '.zip';
			}
		}

		if ( empty( $wporg_webs ) || ( empty( $invalidate_zips ) && empty( $rebuild_zips ) ) ) {
			return;
		}

		$urls = array();
		foreach ( $wporg_webs as $node ) {
			$urls[] = preg_replace( '!^https?://wordpress.org/!', "http://$node/", site_url( '/wp-json/plugins/v1/zip-management' ) );
		}
		$headers = array(
			'User-Agent' => 'WordPress.org Plugin Directory',
			'Host' => 'WordPress.org',
			'Authorization' => 'BEARER ' . PLUGIN_API_INTERNAL_BEARER_TOKEN,
		);
		$body = array(
			'plugins' => array(
				$plugin_slug => array(
					'invalidate' => $invalidate_zips,
					'rebuild' => $rebuild_zips,
				)
			)
		);

		$results = array();
		foreach ( $urls as $url ) {
			$results[ $url ] = wp_remote_post( $url, array(
				'body' => $body,
				'headers' => $headers,
				'sslverify' => false
			) );
		}

		// TODO Do something with $results to verify all servers said the rebuilt zip was correct or something.
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

		// Find the trunk readme file, list remotely to avoid checking out the entire directory.
		$trunk_files = SVN::ls( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk" );
		if ( ! $trunk_files ) {
			throw new Exception( 'Plugin has no files in trunk.' );
		}

		// A plugin historically doesn't have to have a readme.
		$trunk_readme_files = preg_grep( '!^readme.(txt|md)$!i', $trunk_files );
		if ( $trunk_readme_files ) {
			$trunk_readme_file = reset( $trunk_readme_files );
			foreach ( $trunk_readme_files as $f ) {
				if ( '.txt' == strtolower( substr( $f, -4 ) ) ) {
					$trunk_readme_file = $f;
					break;
				}
			}

			$trunk_readme_file = self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk/{$trunk_readme_file}";
			$trunk_readme = new Parser( $trunk_readme_file );

			$stable_tag = $trunk_readme->stable_tag;
		} else {
			$stable_tag = 'trunk';
		}

		$exported = false;
		if ( $stable_tag && 'trunk' != $stable_tag ) {
			$svn_export = SVN::export(
				self::PLUGIN_SVN_BASE . "/{$plugin_slug}/tags/{$stable_tag}",
				$tmp_dir . '/export',
				array(
					'ignore-externals',
					'depth' => 'files'
				)
			);
			// Handle tags which we store as 0.blah but are in /tags/.blah
			if ( ! $svn_export['result'] && '0.' == substr( $stable_tag, 0, 2 ) ) {
				$_stable_tag = substr( $stable_tag, 1 );
				$svn_export = SVN::export(
					self::PLUGIN_SVN_BASE . "/{$plugin_slug}/tags/{$_stable_tag}",
					$tmp_dir . '/export',
					array(
						'ignore-externals',
						'depth' => 'files'
					)
				);
			}
			if ( $svn_export['result'] && false !== $this->find_readme_file( $tmp_dir . '/export' ) ) {
				$exported = true;
			} else {
				// Clear out any files that exist in the export.
				Filesystem::rmdir( $tmp_dir . '/export' );
			}
		}
		if ( ! $exported ) {
			$stable_tag = 'trunk';
			// Either stable_tag = trunk, or the stable_tag tag didn't exist.
			$svn_export = SVN::export(
				self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk",
				$tmp_dir . '/export',
				array(
					'ignore-externals',
					'depth' => 'files' // Only export the root files, we don't need the rest to read the plugin headers/screenshots
				)
			);
			if ( ! $svn_export['result'] || empty( $svn_export['revision'] ) ) {
				throw new Exception( 'Could not create SVN export: ' . implode( ' ', reset( $svn_export['errors'] ) ) );
			}
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
		$assets = array( 'screenshot' => array(), 'banner' => array(), 'icon' => array() );
		$svn_assets_folder = SVN::ls( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/assets/", true /* verbose */ );
		if ( $svn_assets_folder ) { // /assets/ may not exist.
			foreach ( $svn_assets_folder as $asset ) {
				// screenshot-0(-rtl).(png|jpg|jpeg|gif)  ||  icon.svg
				if ( ! preg_match( '!^(?P<type>screenshot|banner|icon)(-(?P<resolution>[\dx]+)(-rtl)?\.(png|jpg|jpeg|gif)|\.svg)$!i', $asset['filename'], $m ) ) {
					continue;
				}
				$type = $m['type'];
				$filename = $asset['filename'];
				$revision = $asset['revision'];
				$location = 'assets';
				$resolution = isset( $m['resolution'] ) ? $m['resolution'] : false;
				$assets[ $type ][ $asset['filename'] ] = compact( 'filename', 'revision', 'resolution', 'location' );
			}
		}

		$tagged_versions = SVN::ls( "https://plugins.svn.wordpress.org/{$plugin_slug}/tags/" ) ?: array();
		$tagged_versions = array_map( function( $item ) {
			return rtrim( $item, '/' );
		}, $tagged_versions );

		// Find screenshots in the stable plugin folder (but don't overwrite /assets/)
		foreach ( Filesystem::list_files( "$tmp_dir/export/", false /* non-recursive */, '!^screenshot-\d+\.(jpeg|jpg|png|gif)$!' ) as $plugin_screenshot ) {
			$filename = basename( $plugin_screenshot );
			$screenshot_id = substr( $filename, strpos( $filename, '-' ) + 1 );
			$screenshot_id = substr( $screenshot_id, 0, strpos( $screenshot_id, '.' ) );

			if ( isset( $assets['screenshot'][ $filename ]  ) ) {
				// Skip it, it exists within /assets/ already
				continue;
			}

			$assets['screenshot'][ $filename ] = array(
				'filename' => $filename,
				'revision' => $svn_export['revision'],
				'resolution' => $screenshot_id,
				'location' => 'plugin',
			);
		}

		return compact( 'readme', 'stable_tag', 'tmp_dir', 'plugin_headers', 'assets', 'tagged_versions' );
	}

	/**
	 * Filters `wp_insert_post()` to allow a custom modified date to be specified.
	 *
	 * @param array $data    The data to be inserted into the database.
	 * @param array $postarr The raw data passed to `wp_insert_post()`.
	 *
	 * @return array The data to insert into the database.
	 */
	public function filter_wp_insert_post_data( $data, $postarr ) {
		if ( !empty( $postarr['override_modified_date'] ) ) {
			$data['post_modified']     = $postarr['post_modified'];
			$data['post_modified_gmt'] = $postarr['post_modified_gmt'];
		}
		return $data;
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
	protected function find_readme_file( $directory ) {
		$files = Filesystem::list_files( $directory, false /* non-recursive */, '!^readme\.(txt|md)$!i' );

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
	protected function find_plugin_headers( $directory ) {
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
}
