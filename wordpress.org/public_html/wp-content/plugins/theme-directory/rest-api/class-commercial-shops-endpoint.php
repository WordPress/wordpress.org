<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;

class Commercial_Shops_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => array( $this, 'shops' ),
			'permission_callback' => '__return_true',
		);

		register_rest_route( 'themes/1.0', 'commercial-shops', $args );
		register_rest_route( 'themes/1.1', 'commercial-shops', $args );
		register_rest_route( 'themes/1.2', 'commercial-shops', $args );
	}

	/**
	 * Endpoint to handle get_commercial_shops API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function shops( $request ) {
		$api = wporg_themes_query_api(
			'get_commercial_shops',
			$request->get_params(),
			'api_object'
		);

		return $api->get_result( 'raw' );
	}

}
new Commercial_Shops_Endpoint();
