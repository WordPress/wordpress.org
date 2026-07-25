<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;

class Tags_Endpoint {

	function __construct() {
		$args = array(
			'callback'            => array( $this, 'tags' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'number' => array(
					'type' => 'integer',
				),
				'locale' => array(
					'type'              => 'string',
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => __NAMESPACE__ . '\sanitize_locale',
				),
			),
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
