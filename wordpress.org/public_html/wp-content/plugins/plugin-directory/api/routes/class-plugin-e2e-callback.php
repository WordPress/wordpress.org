<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;

/**
 * WordPress.org is many different systems operating with one anothers data.
 * This endpoint offers internal w.org services a way to update end-to-end testing data
 * from another system outside of WordPress.
 *
 * This API is not designed for public usage.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_E2E_Callback extends Base {

	function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/e2e', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'save_data' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
			],
			'permission_callback' => array( $this, 'permission_check_internal_api_bearer' ),
		] );
	}

	/**
	 * Endpoint to save a set of postmeta fields for a plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool true
	 */
	function save_data( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		if ( ! $plugin ) {
			return;
		}

		// TODO: More sanitized/formatted data / transforming into other fields.

		// POST data.
		foreach ( $request->get_body_params() as $key => $val ) {
			update_post_meta( $plugin->ID, 'e2e_' . $key, wp_slash( $val ) );
		}

		return true;
	}
}