<?php
/**
 * Client for FreeScout's API.
 *
 * @package WordPressdotorg\Plugin_Directory\Clients
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Clients;

use WordPressdotorg\Plugin_Directory\Tools\Helpdesk;

/**
 * Talks to FreeScout's API, from its API & Webhooks module, with the key in `FREESCOUT_API_KEY`.
 *
 * What the plugin directory writes, it writes as the agent in `FREESCOUT_USER_ID`.
 */
class FreeScout {

	/**
	 * The HTTP timeout, in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 30;

	/**
	 * The API key.
	 *
	 * Either the module's global key, or the personal key (API & Webhooks » My API Keys) of the agent in
	 * `FREESCOUT_USER_ID`, who needs access to the Plugins mailbox.
	 *
	 * @return string Empty if none is configured.
	 */
	public static function api_key(): string {
		$api_key = defined( 'FREESCOUT_API_KEY' ) ? (string) FREESCOUT_API_KEY : '';

		/**
		 * Filters the key for FreeScout's API.
		 *
		 * @param string $api_key API key.
		 */
		return (string) apply_filters( 'wporg_plugins_freescout_api_key', $api_key );
	}

	/**
	 * Sends a request to the API.
	 *
	 * @param string   $path          Path, after `/api/`.
	 * @param array    $args          Query arguments for a GET request, the JSON body for others.
	 * @param string   $method        HTTP method.
	 * @param int|null $response_code Set to the response's HTTP status, 0 if there was none.
	 * @return array|null The decoded response, or the transport error under `error` if there was no response; null
	 *                    without a key, or if the response isn't JSON.
	 */
	public static function api( string $path, array $args = array(), string $method = 'GET', ?int &$response_code = null ): ?array {
		$response_code = 0;
		$api_key       = self::api_key();
		if ( '' === $api_key ) {
			return null;
		}

		$url  = Helpdesk::freescout_url() . 'api/' . ltrim( $path, '/' );
		$body = null;
		if ( 'GET' === $method ) {
			$url = $args ? add_query_arg( $args, $url ) : $url;
		} else {
			$body = wp_json_encode( $args );
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Accept'              => 'application/json',
					'Content-Type'        => 'application/json',
					'X-FreeScout-API-Key' => $api_key,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$data          = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * The ID of the FreeScout agent the plugin directory writes as.
	 *
	 * Configured rather than looked up, so writing needs no other request. The agent's WordPress.org account, connected
	 * through WPOrgSSO, should be the automation account that FreeScout's webhook on api.wordpress.org doesn't credit in
	 * contributor stats.
	 *
	 * @return int 0 if none is configured.
	 */
	public static function user_id(): int {
		$user_id = defined( 'FREESCOUT_USER_ID' ) ? (int) FREESCOUT_USER_ID : 0;

		/**
		 * Filters the ID of the FreeScout agent the plugin directory writes as.
		 *
		 * @param int $user_id Agent ID.
		 */
		return max( 0, (int) apply_filters( 'wporg_plugins_freescout_user_id', $user_id ) );
	}
}
