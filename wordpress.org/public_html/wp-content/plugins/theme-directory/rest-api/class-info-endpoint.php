<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_Error;
use WP_REST_Response;

class Info_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => array( $this, 'info' ),
			'permission_callback' => '__return_true',
		);

		register_rest_route( 'themes/1.0', 'info(/(?P<slug>[^/]+))?', $args );
		register_rest_route( 'themes/1.1', 'info(/(?P<slug>[^/]+))?', $args );
		register_rest_route( 'themes/1.2', 'info(/(?P<slug>[^/]+))?', $args );
	}

	/**
	 * Endpoint to handle theme_information API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function info( $request ) {
		$api = wporg_themes_query_api(
			'theme_information',
			$request->get_params(),
			'api_object'
		);

		$response = new WP_REST_Response( $api->get_result( 'raw' ) );

		if ( ! empty( $api->bad_input ) ) {
			$response->set_status( 400 );
		} elseif ( ! empty( $api->error ) && 'Theme not found' === $api->error ) {
			$response->set_status( 404 );
		}

		return $response;
	}

}
new Info_Endpoint();
