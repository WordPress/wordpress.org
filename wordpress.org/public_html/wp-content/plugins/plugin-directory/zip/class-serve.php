<?php
namespace WordPressdotorg\Plugin_Directory\Zip;
use Exception;

/**
 * Serves a Zip file.
 *
 * This class is designed to be used outside of WordPress.
 * That's why we use $wpdb and cache functions directly in some functions.
 *
 * @package WordPressdotorg\Plugin_Directory\Zip
 */
class Serve {

	public function __construct() {
		try {
			$request = $this->determine_request();

			$this->serve_zip( $request );

			if ( $request['args']['stats'] ) {
				$this->record_stats( $request );
			}

		} catch ( Exception $e )  {
			$this->error();
		}

	}

	/**
	 * Parse the Request URI to determine what we need to serve.
	 *
	 * @return array An array containing the vital details for the ZIP request.
	 */
	protected function determine_request() {
		$zip = basename( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

		$slug = false;
		$version = 'trunk';

		if ( ! preg_match( "!^(?P<slug>[a-z0-9-]+)(.(?P<version>.+))?.zip$!i", $zip, $m ) ) {
			throw new Exception( __METHOD__ . ": Invalid URL." );
		}

		$slug = strtolower( $m['slug'] );
		if ( isset( $m['version'] ) ) {
			$version = $m['version'];
		}

		if ( 'latest-stable' == $version ) {
			$version = $this->get_stable_tag( $slug );
		}

		$args = array(
			'stats' => true,
		);
		if ( isset( $_GET['stats'] ) ) {
			$args['stats'] = (bool) $_GET['stats'];
		} elseif ( isset( $_GET['nostats'] ) ) {
			$args['stats'] = !empty( $_GET['nostats'] );
		}

		return compact( 'zip', 'slug', 'version', 'args' );
	}

	/**
	 * Retrieve the stable_tag for a given plugin from Cache or the Database.
	 *
	 * @param string $plugin_slug The plugin slug
	 * @return string The stable_tag on success, Exception thrown on failure.
	 */
	protected function get_stable_tag( $plugin_slug ) {
		global $wpdb;

		$post_id = $this->get_post_id( $plugin_slug );

		// Determine the stable_tag
		$meta = wp_cache_get( $post_id, 'post_meta' );
		$version = false;
		if ( isset( $meta['stable_tag'][0] ) ) {
			$version = $meta['stable_tag'][0];
		}
		if ( ! $version ) {
			$version = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = 'stable_tag' LIMIT 1", $post_id ) );
		}
		if ( ! $version ) {
			throw new Exception( __METHOD__ . ": A version for $plugin_slug cannot be determined." );
		}

		return $version;
	}

	/**
	 * Retrieve the post_id for a Plugin slug.
	 *
	 * This function uses the Object Cache and $wpdb directly to avoid
	 * a dependency upon WordPress.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return int The post_id for the plugin. Exception thrown on failure.
	 */
	protected function get_post_id( $plugin_slug ) {
		global $wpdb;

		$post_id = wp_cache_get( $plugin_slug, 'plugin-slugs' );
		if ( false === $post_id ) {
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID from $wpdb->posts WHERE post_type = 'plugin' AND post_name = %s", $plugin_slug ) );
			wp_cache_add( $plugin_slug, $post_id, 'plugin-slugs' );
		}

		if ( ! $post_id ) {
			throw new Exception( __METHOD__ . ": A post_id for $plugin_slug cannot be determined." );
		}

		return $post_id;
	}

	/**
	 * Returns the files to use for the request.
	 *
	 * @param array $request The request object for the request.
	 * @return array An array containing the files to use for the request, 'zip' and 'md5'.
	 */
	protected function get_file( $request ) {
		if ( empty( $request['version'] ) || 'trunk' == $request['version'] ) {
			return "{$request['slug']}/{$request['slug']}.zip";
		} else {
			return "{$request['slug']}/{$request['slug']}.{$request['version']}.zip";
		}
	}

	/**
	 * Output a ZIP file with all headers.
	 *
	 * @param array $request The request array for the request.
	 */
	protected function serve_zip( $request ) {
		$zip = $this->get_file( $request );

		if ( defined( 'PLUGIN_ZIP_X_ACCEL_REDIRECT_LOCATION' ) ) {
			$zip_url = PLUGIN_ZIP_X_ACCEL_REDIRECT_LOCATION . $zip;

			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename=' . basename( $zip ) );
			header( "X-Accel-Redirect: $zip_url" );
		} else {
			header( 'Content-Type: text/plain' );
			echo "This is a request for $zip, this server isn't currently configured to serve zip files.\n";
		}

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

	}

	/**
	 * Record some download stats for Plugin Downloads.
	 *
	 * TODO: This should really be async log parsing..
	 *
	 * @param array $request The request array.
	 */
	protected function record_stats( $request ) {
		global $wpdb;

		$stats_dedup_log_table = PLUGINS_TABLE_PREFIX . 'downloads';
		$stats_download_table = PLUGINS_TABLE_PREFIX . 'download_counts';
		$stats_download_daily_table = PLUGINS_TABLE_PREFIX . 'stats';

		// Very basic de-duplication for downloads.
		$recent = $wpdb->get_var( $wpdb->prepare(
			"SELECT `client_ip`
			FROM {$stats_dedup_log_table}
			WHERE
				plugin_slug = %s AND
			 	client_ip = %s AND
			 	user_agent = %s AND
			 	stamp BETWEEN %s AND %s
			LIMIT 1",
			$request['slug'],
			$_SERVER['REMOTE_ADDR'],
			$_SERVER['HTTP_USER_AGENT'],
			gmdate( 'Y-m-d 00:00:00' ),
			gmdate( 'Y-m-d 23:59:59' )
		) );

		if ( $recent ) {
			return;
		}

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$stats_download_table} (`plugin_slug`, `downloads`) VALUES ( %s, 1 ) ON DUPLICATE KEY UPDATE `downloads` = `downloads` + 1",
			$request['slug']
		) );

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$stats_download_daily_table} (`plugin_slug`, `stamp`, `downloads`) VALUES ( %s, %s, 1 ) ON DUPLICATE KEY UPDATE `downloads` = `downloads` + 1",
			$request['slug'],
			gmdate( 'Y-m-d' )
		) );

		$wpdb->insert( $stats_dedup_log_table, array(
			'plugin_slug' => $request['slug'],
			'client_ip'   => $_SERVER['REMOTE_ADDR'],
			'user_agent'  => $_SERVER['HTTP_USER_AGENT'],
			'stamp'       => gmdate( 'Y-m-d H:i:s' )
		) );

	}

	/**
	 * Bail with a 404.
	 */
	protected function error() {
		$protocol = isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
		$protocol .= ' ';

		header( $protocol . '404 File not found' );
		die( '404 file not found' );
	}

}
