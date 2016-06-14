<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;

/**
 * WordPress.org is many different systems operating with one anothers data.
 * This endpoint offers internal w.org services a way to update stat data from
 * these other systems from outside WordPress while triggering all WordPress actions
 * and filters.
 *
 * This API is not designed for public usage, an API to fetch these statistics will also be
 * made available.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Internal_Stats extends Base {

	function __construct() {
		register_rest_route( 'plugins/v1', '/update-stats', array(
			'methods'  => \WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'bulk_update_stats' ),
			'permission_callback' => array( $this, 'permission_check_internal_api_bearer' ),
		) );
	}

	/**
	 * Endpoint to update a whitelisted set of postmeta fields for a bunch of plugin slugs.
	 *
	 * Data is in the format of
	 * plugins: {
	 *    plugin-slug: {
	 *      active_installs: 1000
	 *    },
	 *    plugin-slug-2: {
	 *       active_instals: 1000000
	 *    }
	 * }
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool true
	 */
	function bulk_update_stats( $request ) {
		$data = $request['plugins'];

		foreach ( $data as $plugin_slug => $stats ) {
			$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
			if ( ! $plugin ) {
				continue;
			}

			foreach ( $stats as $stat_name => $value ) {
				if ( 'active_installs' == $stat_name ) {
					// Store an unsanitized private version of the active_installs stat for consistent ordering.
					update_post_meta( $plugin->ID, '_active_installs', wp_slash( $value ) );

					$value = $this->sanitize_active_installs( $value );
				} elseif ( 'usage' == $stat_name ) {
					$value = $this->sanitize_usage_numbers( $value, $plugin );
				} elseif ( 'support_threads' == $stat_name || 'support_threads_resolved' == $stat_name ) {
					$value = (int) $value;
				} else {
					continue; // Unknown key
				}

				update_post_meta( $plugin->ID, $stat_name, wp_slash( $value ) );
			}
		}

		return true;
	}

	/**
	 * Sanitizes the Active Install count number to a rounded display value.
	 *
	 * @param int $active_installs The raw active install number.
	 * @return int The sanitized version for display.
	 */
	protected function sanitize_active_installs( $active_installs ) {
		if ( $active_installs > 1000000 ) {
			// 1 million +;
			return 1000000;
		} elseif ( $active_installs > 100000 ) {
			$round = 100000;
		} elseif ( $active_installs > 10000 ) {
			$round = 10000;
		} elseif ( $active_installs > 1000 ) {
			$round = 1000;
		} elseif ( $active_installs > 100 ) {
			$round = 100;
		} else {
			// Rounded to ten, else 0
			$round = 10;
		}

		return floor( $active_installs / $round ) * $round;
	}

	/**
	 * Sanitizes the usage figures for a plugin.
	 *
	 * Versions higher than the latest branch will be excluded.
	 * Versions which have a usage below 5% will be combined into 'other'
	 * unless it's the latest branch, or if there's only one branch which is less than 5%.
	 *
	 * @param array    $usage  An array of the branch usage numbers.
	 * @param \WP_Post $plugin The plugin's WP_Post instance.
	 * @return array An array containing the percentages for the given plugin.
	 */
	protected function sanitize_usage_numbers( $usage, $plugin ) {
		$latest_version = get_post_meta( $plugin->ID, 'version', true );
		$latest_branch = implode( '.', array_slice( explode('.', $latest_version ), 0, 2 ) );

		// Exclude any version strings higher than the latest plugin version (ie. 99.9)
		foreach ( $usage as $version => $count ) {
			if ( version_compare( $version, $latest_version, '>' ) || 0 === strlen( $version ) ) {
				unset( $usage[ $version ] );
			}
		}

		// The percentage at which we combine versions into an "other" group.
		// Note: The latest branch will NOT fold into this.
		$percent_cut_off = 5;

		// Calculate the percentage of each version branch
		$total = array_sum( $usage );
		$others = array();
		foreach ( $usage as $version => $count ) {
			$percent = round( $count / $total * 100, 2 );

			if ( $percent < $percent_cut_off && $version != $latest_branch ) {
				$others[ $version ] = $count;
				unset( $usage[ $version ] );
				continue;
			}
			$usage[ $version ] = $percent;
		}

		// If there was only one version < $percent_cut_off then display it as-is
		if ( count( $others ) == 1 ) {
			$version = array_keys( $others );
			$version = array_shift( $version );
			$usage[ $version ] = round( $others[ $version ] / $total * 100, 2 );
		// Else we'll add an 'others' version.
		} elseif ( count( $others ) > 1 ) {
			$usage['other'] = round( array_sum( $others ) / $total * 100, 2 );
		}

		return $usage;
	}

}
