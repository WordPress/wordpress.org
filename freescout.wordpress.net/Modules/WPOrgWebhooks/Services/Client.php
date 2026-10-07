<?php
/**
 * Signed HTTP client for the WordPress.org helpdesk endpoints.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Services;

use RuntimeException;

/**
 * Posts JSON payloads to api.wordpress.org, signed with a shared secret.
 *
 * The receiving side verifies the signature header against the raw request body.
 */
final class Client {

	/**
	 * Header carrying the hex-encoded HMAC-SHA256 of the request body.
	 *
	 * @var string
	 */
	public const SIGNATURE_HEADER = 'X-FreeScout-Signature';

	/**
	 * Statuses the web server sends when it couldn't hand the request to PHP.
	 *
	 * @var int[]
	 */
	private const NOT_DELIVERED_STATUSES = array( 502, 503 );

	/**
	 * Base URL of the endpoints, with a trailing slash.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Shared signing secret.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Constructor.
	 *
	 * @param string $base_url Base URL of the endpoints.
	 * @param string $secret   Shared signing secret.
	 * @param int    $timeout  Request timeout in seconds.
	 */
	public function __construct( string $base_url, string $secret, int $timeout = 10 ) {
		$this->base_url = rtrim( $base_url, '/' ) . '/';
		$this->secret   = $secret;
		$this->timeout  = $timeout;
	}

	/**
	 * Creates a client from the module configuration.
	 *
	 * @param int $timeout Request timeout in seconds.
	 * @return self
	 */
	public static function from_config( int $timeout = 10 ): self {
		return new self(
			(string) config( 'wporgwebhooks.api_url' ),
			(string) config( 'wporgwebhooks.secret' ),
			$timeout
		);
	}

	/**
	 * Whether a signing secret is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->secret;
	}

	/**
	 * Computes the signature for a request body.
	 *
	 * @param string $body   Raw request body.
	 * @param string $secret Shared signing secret.
	 * @return string Hex-encoded HMAC-SHA256.
	 */
	public static function sign( string $body, string $secret ): string {
		return hash_hmac( 'sha256', $body, $secret );
	}

	/**
	 * Encodes a payload, stamped with the time, endpoint, and a nonce, so it's good there, once.
	 *
	 * @param array  $payload  Request payload.
	 * @param string $endpoint Endpoint path relative to the base URL.
	 * @return string JSON body.
	 */
	public static function encode( array $payload, string $endpoint ): string {
		$payload['sent_at']  = time();
		$payload['endpoint'] = ltrim( $endpoint, '/' );
		$payload['nonce']    = bin2hex( random_bytes( 16 ) );

		return (string) json_encode( $payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Posts a signed payload to an endpoint.
	 *
	 * Only a 2xx response counts as delivered, whatever its body. Redirects aren't followed, since they'd turn the
	 * request into a GET.
	 *
	 * @param string $endpoint Endpoint path relative to the base URL.
	 * @param array  $payload  Request payload.
	 * @return void
	 *
	 * @throws NotDeliveredException If api.wordpress.org certainly didn't get the request: it couldn't be reached, or
	 *                               its web server answered for it.
	 * @throws RuntimeException      If the client is not configured, or the request failed in a way that may have
	 *                               reached webhook.php, like timing out after it was sent.
	 */
	public function post( string $endpoint, array $payload ): void {
		if ( ! $this->is_configured() ) {
			throw new RuntimeException( 'WPORG_API_SECRET is not configured.' );
		}

		$body = self::encode( $payload, $endpoint );

		try {
			// Whatever APP_CURL_SSL_VERIFYPEER says.
			$http     = new \GuzzleHttp\Client(
				\Helper::setGuzzleDefaultOptions(
					array(
						'timeout'         => $this->timeout,
						'allow_redirects' => false,
						'verify'          => true,
					)
				)
			);
			$response = $http->post(
				$this->base_url . ltrim( $endpoint, '/' ),
				array(
					'headers' => array(
						'Content-Type'         => 'application/json',
						'Accept'               => 'application/json',
						self::SIGNATURE_HEADER => self::sign( $body, $this->secret ),
					),
					'body'    => $body,
				)
			);
		} catch ( \GuzzleHttp\Exception\ConnectException $e ) {
			throw new NotDeliveredException( 'Could not connect for ' . $endpoint . ': ' . $e->getMessage(), 0, $e );
		} catch ( \GuzzleHttp\Exception\BadResponseException $e ) {
			$response = $e->getResponse();
		} catch ( \Throwable $e ) {
			throw new RuntimeException( 'Request to ' . $endpoint . ' failed: ' . $e->getMessage(), 0, $e );
		}

		$status = $response->getStatusCode();
		if ( in_array( $status, self::NOT_DELIVERED_STATUSES, true ) ) {
			throw new NotDeliveredException( 'Request to ' . $endpoint . ' got a ' . $status . ' response.' );
		}

		if ( $status < 200 || $status > 299 ) {
			throw new RuntimeException( 'Request to ' . $endpoint . ' got a ' . $status . ' response.' );
		}
	}
}
