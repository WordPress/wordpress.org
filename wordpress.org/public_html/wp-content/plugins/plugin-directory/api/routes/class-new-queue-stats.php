<?php
/**
 * REST API route for the new plugin queue statistics.
 *
 * @package WordPressdotorg_Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\API\Base;

/**
 * An API endpoint to expose the number of plugins awaiting initial review.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class New_Queue_Stats extends Base {

	/**
	 * Registers the route.
	 */
	public function __construct() {
		register_rest_route(
			'plugins/v1',
			'/stats/new-queue-count',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_new_queue_count' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Gets the number of plugins awaiting initial review.
	 *
	 * @param \WP_REST_Request $request The REST API request.
	 * @return array The queue count.
	 */
	public function get_new_queue_count( $request ) {
		$counts = wp_count_posts( 'plugin' );

		return array(
			'queue_count' => (int) ( $counts->new ?? 0 ),
		);
	}
}
