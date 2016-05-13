<?php
namespace WordPressdotorg\Plugin_Directory\CLI;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Readme_Parser;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Tools\SVN;

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
		'stable_tag',
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
	 * @param string $plugin_slug The slug of the plugin to import.
	 */
	public function import( $plugin_slug ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $plugin ) {
// TODO			throw new \Exception( "Unknown Plugin" );
		}

		$data = $this->export_and_parse_plugin( $plugin_slug );

		if ( ! $plugin ) {
			global $wpdb;
			// TODO: During development while the bbPress variant is still running, we'll pull details from it and allow importing of any plugin.
			$author = $wpdb->get_var( $wpdb->prepare( 'SELECT topic_poster FROM ' . PLUGINS_TABLE_PREFIX . 'topics WHERE topic_slug = %s', $plugin_slug ) );
			if ( ! $author ) {
				throw new \Exception( "Unknown Plugin" );
			}
			$plugin = Plugin_Directory::create_plugin_post( array(
				'slug' => $plugin_slug,
				'status' => 'publish', // If we're importing it on the CLI, and it didn't exist, assume it's published
				'author' => $author
			) );
		}

		$readme = $data['readme'];
		$assets = $data['assets'];
		$headers = $data['plugin_headers'];
		$tagged_versions = $data['tagged_versions'];

		$content = '';
		foreach ( $readme->sections as $section => $section_content ) {
			$content .= "\n\n<!--section={$section}-->\n{$section_content}";
		}

		// Fallback to the plugin title if the readme didn't contain it.
		$plugin->post_title   = trim( $readme->name ) ?: strip_tags( $headers->Name );
		$plugin->post_content = trim( $content );
		$plugin->post_excerpt = trim( $readme->short_description );

		// Bump last updated if the version has changed.
		if ( $headers->Version != get_post_meta( $plugin->ID, 'version', true ) ) {
			$plugin->post_modified = $plugin->post_modified_gmt = current_time( 'mysql' );
		}

		wp_update_post( $plugin );

		foreach ( $this->readme_fields as $readme_field ) {
			// Don't change the tested version if a newer version was specified through wp-admin
			if ( 'tested' == $readme_field && version_compare( get_post_meta( $plugin->ID, 'tested', true ), $readme->$readme_field, '>' ) ) {
				continue;
			}
			update_post_meta( $plugin->ID, $readme_field, wp_slash( $readme->$readme_field ) );
		}
		foreach ( $this->plugin_headers as $plugin_header => $meta_field ) {
			update_post_meta( $plugin->ID, $meta_field, wp_slash( $headers->$plugin_header ) );
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
			// @todo: Enable.
			continue;
			$user = get_user_by( 'slug', $committer );

			if ( ! user_can( $user, 'plugin_dashboard_access' ) ) {
				$user->add_role( 'plugin_committer' );
			}
		}
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
	 *   'readme', 'trunk_readme', 'tmp_dir', 'plugin_headers', 'assets'
	 * }
	 */
	protected function export_and_parse_plugin( $plugin_slug ) {
		$tmp_dir = Filesystem::temp_directory( "process-{$plugin_slug}" );

		$svn_export = SVN::export( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/trunk", $tmp_dir . '/trunk', array( 'ignore-externals' ) );
		if ( ! $svn_export['result'] || empty( $svn_export['revision'] ) ) {
			throw new \Exception( "Could not create SVN export." . implode( ' ', reset( $svn_export['errors'] ) ) );
		}
		$trunk_revision = $svn_export['revision'];

		$trunk_readme = $this->find_readme_file( $tmp_dir . '/trunk' );
		if ( ! $trunk_readme ) {
			throw new \Exception( "Could not locate a trunk readme" );
		}
		$trunk_readme = new Readme_Parser( $trunk_readme );

		if ( $trunk_readme->stable_tag == 'trunk' || empty( $trunk_readme->stable_tag ) ) {
			$readme = $trunk_readme;
			$stable = 'trunk';
			$stable_revision = $trunk_revision;
		} else {
			// There's a chance that the stable_tag will not actually exist, we have to fallback to trunk in those cases and avoid exiting here.
			$stable_tag = preg_replace( '![^a-z0-9-_.]!i', '', $trunk_readme->stable_tag );
			$svn_export = SVN::export( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/tags/{$stable_tag}",  $tmp_dir . '/stable', array( 'ignore-externals' ) );

			$stable_readme = $svn_export['result'] ? $this->find_readme_file( $tmp_dir . '/stable' ) : false;
			if ( $stable_readme ) {
				$readme = $stable_readme = new Readme_Parser( $stable_readme );
				$stable = 'stable';
				$stable_revision = $svn_export['revision'];
			} else {
				// Trunk is stable!
				$readme = $trunk_readme;
				$stable = 'trunk';
				$stable_revision = $trunk_revision;
			}
		}

		$plugin_headers = $this->find_plugin_headers( "$tmp_dir/$stable" );

		// Now we look in the /assets/ folder for banners, screenshots, and icons.
		$assets = array( 'screenshot' => array(), 'banner' => array(), 'icon' => array() );
		$svn_assets_folder = SVN::ls( self::PLUGIN_SVN_BASE . "/{$plugin_slug}/assets/", true /* verbose */ );
		if ( $svn_assets_folder ) { // /assets/ may not exist.
			foreach ( $svn_assets_folder as $asset ) {
				// screenshot-0.(png|jpg|jpeg|gif)  ||  icon.svg
				if ( ! preg_match( '!^(?P<type>screenshot|banner|icon)(-(?P<resolution>[\dx]+)\.(png|jpg|jpeg|gif)|\.svg)$!i', $asset['filename'], $m ) ) {
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

		$tagged_versions = SVN::ls( "https://plugins.svn.wordpress.org/{$plugin_slug}/tags/" );
		$tagged_versions = array_map( function( $item ) {
			return rtrim( $item, '/' );
		}, $tagged_versions );

		// Find screenshots in the stable plugin folder (but don't overwrite /assets/)
		foreach ( Filesystem::list_files( "$tmp_dir/$stable/", false /* non-recursive */, '!^screenshot-\d+\.(jpeg|jpg|png|gif)$!' ) as $plugin_screenshot ) {
			$filename = basename( $plugin_screenshot );
			$screenshot_id = substr( $filename, strpos( $filename, '-' ) + 1 );
			$screenshot_id = substr( $screenshot_id, 0, strpos( $screenshot_id, '.' ) );

			if ( isset( $assets['screenshot'][ $filename ]  ) ) {
				// Skip it, it exists within /assets/ already
				continue;
			}

			$assets['screenshot'][ $filename ] = array(
				'filename' => $filename,
				'revision' => $stable_revision,
				'resolution' => $screenshot_id,
				'location' => 'plugin',
			);
		}

		return compact( 'readme', 'trunk_readme', 'tmp_dir', 'plugin_headers', 'assets', 'tagged_versions' );
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

		foreach ( $files as $file ) {
			$data = get_plugin_data( $file, false, false );
			if ( array_filter( $data ) ) {
				return (object) $data;
			}
		}

		return false;
	}
}
