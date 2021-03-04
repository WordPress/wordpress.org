<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_Error;
use WP_REST_Response;

class Features_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => array( $this, 'features' ),
			'permission_callback' => '__return_true',
		);

		register_rest_route( 'themes/1.0', 'features', $args );
		register_rest_route( 'themes/1.1', 'features', $args );
		register_rest_route( 'themes/1.2', 'features', $args );
	}

	/**
	 * Endpoint to handle feature_list API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function features( $request ) {
		$api = wporg_themes_query_api(
			'feature_list',
			$request->get_params(),
			'api_object'
		);

		return $api->get_result( 'raw' );
	}

}
new Features_Endpoint();
