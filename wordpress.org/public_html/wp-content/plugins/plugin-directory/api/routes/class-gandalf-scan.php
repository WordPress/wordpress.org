<?php
/**
 * Gandalf scan callback REST route.
 *
 * @package WordPressdotorg_Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Callback endpoint for advisory Gandalf scans.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Gandalf_Scan extends Base {

	/**
	 * Registers the callback route.
	 */
	public function __construct() {
		register_rest_route(
			'plugins/v1',
			'/plugin/(?P<plugin_slug>[^/]+)/gandalf-scan',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scan_callback' ),
				'args'                => array(
					'plugin_slug' => array(
						'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
					),
				),
				'permission_callback' => array( $this, 'permission_check_gandalf_scan_bearer' ),
			)
		);
	}

	/**
	 * Permission check for Gandalf callbacks.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error True when authorized, or an error.
	 */
	public function permission_check_gandalf_scan_bearer( $request ) {
		return $this->permission_check_api_bearer( $request, 'WP_GANDALF_SCAN_SHARED_SECRET' );
	}

	/**
	 * Receive a Gandalf scan callback.
	 *
	 * Trusts the bearer-authenticated sender for payload shape; the dispatcher
	 * correlates scan_id and release identifiers against the pending record.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array|\WP_Error Callback response, or an error.
	 */
	public function scan_callback( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$error = new \WP_Error(
				'invalid_gandalf_scan_callback',
				__( 'Invalid Gandalf scan callback.', 'wporg-plugins' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);

			Plugin_Scan_Gandalf::record_invalid_callback( $plugin, $error );
			return $error;
		}

		$result = Plugin_Scan_Gandalf::handle_callback( $plugin, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
		);
	}
}
