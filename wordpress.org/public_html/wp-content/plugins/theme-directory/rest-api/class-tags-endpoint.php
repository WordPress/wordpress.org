<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;

class Tags_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => array( $this, 'tags' ),
			'permission_callback' => '__return_true',
		);

		register_rest_route( 'themes/1.0', 'tags', $args );
		register_rest_route( 'themes/1.1', 'tags', $args );
		register_rest_route( 'themes/1.2', 'tags', $args );
	}

	/**
	 * Endpoint to handle tags API calls.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 */
	function tags( $request ) {
		$api = wporg_themes_query_api(
			'hot_tags',
			$request->get_params(),
			'api_object'
		);

		return $api->get_result( 'raw' );
	}

}
new Tags_Endpoint();
