<?php
namespace WordPressdotorg\Plugin_Directory\Clients;

/**
 * Simple HelpScout client.
 *
 * @package WordPressdotorg\Plugin_Directory\Clients
 */
class HelpScout {
	const API_BASE = 'https://api.helpscout.net';

	/**
	 * The HTTP timeout for the HelpScout API.
	 */
	const TIMEOUT = 30;

	public static function api( $url, $args = null, $method = 'GET', & $response_code = null ) {
		// Verify the configuration variables are available.
		if ( ! defined( 'HELPSCOUT_APP_ID' ) || ! defined( 'HELPSCOUT_APP_SECRET' ) ) {
			return false;
		}

		// Prepend API URL host-less URLs
		if ( ! str_starts_with( $url, self::API_BASE ) ) {
			$url = self::API_BASE . '/' . ltrim( $url, '/' );
		}

		if ( 'GET' === $method && $args ) {
			$url = add_query_arg( $args, $url );
		}

		$body    = null;
		$headers = [
			'Accept'        => 'application/json',
			'Authorization' => self::get_auth_string(),
		];
		// All editable requests must have a json content-type.
		// See https://developer.helpscout.com/mailbox-api/overview/content_type/
		if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$headers['Content-Type'] = 'application/json';
			$body                    = json_encode( $args );
		}

		$request = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => self::TIMEOUT,
				'body'    => $body,
			)
		);

		$response_code = wp_remote_retrieve_response_code( $request );

		return json_decode( wp_remote_retrieve_body( $request ) );
	}

	/**
	 * Fetch an Authorization token for accessing HelpScout Resources.
	 */
	protected static function get_auth_string() {
		$token = get_site_transient( __CLASS__ . 'get_auth_token' );
		if ( $token && is_array( $token ) && $token['exp'] > time() ) {
			return 'BEARER ' . $token['token'];
		}

		$request = wp_remote_post(
			self::API_BASE . '/v2/oauth2/token',
			array(
				'timeout' => self::TIMEOUT,
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => HELPSCOUT_APP_ID,
					'client_secret' => HELPSCOUT_APP_SECRET
				)
			)
		);

		$response = is_wp_error( $request ) ? false : json_decode( wp_remote_retrieve_body( $request ) );

		if ( ! $response || empty( $response->access_token ) ) {
			return false;
		}

		// Cache the token for 1 minute less than what it's valid for.
		$token  = $response->access_token;
		$expiry = $response->expires_in - MINUTE_IN_SECONDS;

		set_site_transient( __CLASS__ . 'get_auth_token', [ 'exp' => time() + $expiry, 'token' => $token ], $expiry );

		return 'BEARER ' . $token;
	}

}
