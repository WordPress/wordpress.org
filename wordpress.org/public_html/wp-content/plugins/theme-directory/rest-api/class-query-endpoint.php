<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_Error;
use WP_REST_Response;

class Query_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => array( $this, 'query' ),
			'permission_callback' => '__return_true',
		);

		register_rest_route( 'themes/1.0', 'query', $args );
		register_rest_route( 'themes/1.1', 'query', $args );
		register_rest_route( 'themes/1.2', 'query', $args );
	}

	/**
	 * Endpoint to handle query API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function query( $request ) {
		$api = wporg_themes_query_api(
			'query_themes',
			$request->get_params(),
			'api_object'
		);

		$response = new WP_REST_Response( $api->get_result( 'raw' ) );

		if ( ! empty( $api->bad_input ) ) {
			$response->set_status( 400 );
		}

		return $response;
	}

}
new Query_Endpoint();
