<?php

namespace WordPressdotorg\GlotPress\Customizations\REST_API;

use WP_Error;
use WP_Http;

class Base {

	/**
	 * Namespace of the API routes.
	 *
	 * @var string
	 */
	protected $namespace = 'translate/v1';

	/**
	 * Initialises each API endpoint we offer.
	 */
	public static function load_endpoints() {
		$jobs = new Endpoints\Jobs_Controller();
		$jobs->register_routes();
	}

	/**
	 * A permission check callback which validates the request with a Bearer token.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	public function permission_check_internal_api_bearer( $request ) {
		$authorization_header = $request->get_header( 'authorization' );
		$authorization_header = trim( str_ireplace( 'bearer', '', $authorization_header ) );

		if (
			! $authorization_header ||
			! defined( 'TRANSLATE_API_INTERNAL_BEARER_TOKEN' ) ||
			! hash_equals( TRANSLATE_API_INTERNAL_BEARER_TOKEN, $authorization_header )
		) {
			return false;
		}

		return true;
	}
}
