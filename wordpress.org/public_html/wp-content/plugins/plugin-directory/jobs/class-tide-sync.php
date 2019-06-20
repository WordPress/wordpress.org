<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use Exception;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Import tide data into the Plugin Directory.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Tide_Sync {

	public static function sync_data( $plugin_slug ) {
		wp_schedule_single_event(
			time() + 60,
			"tide_sync:{$plugin_slug}",
			array(
				array( 'plugin' => $plugin_slug, 'date' => time() )
			)
		);
	}

	public static function requeue( $plugin_data, $when = 300 ) {
		// Don't requeue things forever.
		if ( ! $plugin_data['date'] || $plugin_data['date'] + DAY_IN_SECONDS < time() ) {
			return false;
		}

		$when_to_run = time() + $when;
		// Incremental back-off.
		$when_to_run += abs( time() - $plugin_data['date'] ) * 2;

		wp_schedule_single_event(
			$when_to_run,
			'tide_sync:' . $plugin_data['plugin'],
			array(
				$plugin_data
			)
		);

		return true;
	}

	/**
	 * The cron trigger for the import job.
	 */
	public static function cron_trigger( $plugin_data ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin_data['plugin'] );
		if ( ! $plugin ) {
			return false;
		}
		$plugin_slug = $plugin->post_name;

		$data = self::fetch_data( $plugin_slug, $plugin->version );
		if ( $data->content === "<p>pending</p>" ) {
			// Not yet available, Check back later.
			return self::requeue( $plugin_data );
		}

		/*
		 * Store the data from Tide for PHP compatibility.
		 */
		if ( empty( $data->reports->phpcs_phpcompatibility->compatible_versions ) ) {
			// Data unavailable..
			return false;
		}

		$compatible_php_versions = $data->reports->phpcs_phpcompatibility->compatible_versions;

		update_post_meta( $plugin->ID, '_tide_compatible_php', $compatible_php_versions );
		update_post_meta( $plugin->ID, '_tide_requires_php', min( $compatible_php_versions ) );

		return true;
	}

	/**
	 * Fetch Tide API for plugin data
	 */
	public static function fetch_data( $plugin_slug, $version ) {
		$url_endpoint = "https://wptide.org/api/tide/v1/audit/wporg/plugin/{$plugin_slug}/{$version}?_ts=" . time();

		$request = wp_safe_remote_get( $url_endpoint );
		if ( ! $request || is_wp_error( $request ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $request ) );

		return $data;
	}
}
