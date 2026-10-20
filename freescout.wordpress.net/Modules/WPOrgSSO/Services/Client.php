<?php
/**
 * Signed HTTP client for the WordPress.org helpdesk endpoints.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Services;

use RuntimeException;

/**
 * Posts JSON payloads to api.wordpress.org, signed with a shared secret.
 *
 * The receiving side verifies the signature header against the raw request body. Each module has its own copy, so
 * any of them can be switched off without breaking the others.
 */
final class Client {

	/**
	 * Header carrying the hex-encoded HMAC-SHA256 of the request body.
	 *
	 * @var string
	 */
	public const SIGNATURE_HEADER = 'X-FreeScout-Signature';

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
	 * Guzzle handler, for tests to answer requests.
	 *
	 * @var callable|null
	 */
	private $handler;

	/**
	 * Constructor.
	 *
	 * @param string        $base_url Base URL of the endpoints.
	 * @param string        $secret   Shared signing secret.
	 * @param int           $timeout  Request timeout in seconds.
	 * @param callable|null $handler  Guzzle handler; the default sends real requests.
	 */
	public function __construct( string $base_url, string $secret, int $timeout = 10, ?callable $handler = null ) {
		$this->base_url = rtrim( $base_url, '/' ) . '/';
		$this->secret   = $secret;
		$this->timeout  = $timeout;
		$this->handler  = $handler;
	}

	/**
	 * Creates a client from the module configuration.
	 *
	 * Tests swap it by binding their own instance in the container.
	 *
	 * @return self
	 */
	public static function from_config(): self {
		if ( app()->bound( self::class ) ) {
			return app( self::class );
		}

		return new self(
			(string) config( 'wporgsso.api_url' ),
			(string) config( 'wporgsso.secret' )
		);
	}

	/**
	 * A copy of the client with another request timeout.
	 *
	 * @param int $timeout Request timeout in seconds.
	 * @return self
	 */
	public function with_timeout( int $timeout ): self {
		$client          = clone $this;
		$client->timeout = $timeout;

		return $client;
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

		// Email bodies are not guaranteed to be valid UTF-8.
		return (string) json_encode( $payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Posts a signed payload to an endpoint.
	 *
	 * @param string $endpoint Endpoint path relative to the base URL.
	 * @param array  $payload  Request payload.
	 * @param array  $headers  Additional request headers.
	 * @return array Decoded JSON response, empty if the response has no body.
	 *
	 * @throws RuntimeException If the client is not configured, the request fails, or the response is not JSON.
	 */
	public function post( string $endpoint, array $payload, array $headers = array() ): array {
		if ( ! $this->is_configured() ) {
			throw new RuntimeException( 'WPORG_API_SECRET is not configured.' );
		}

		$body = self::encode( $payload, $endpoint );

		try {
			$response = $this->http()->post(
				$this->base_url . ltrim( $endpoint, '/' ),
				array(
					'headers' => array_merge(
						$headers,
						array(
							'Content-Type'         => 'application/json',
							'Accept'               => 'application/json',
							self::SIGNATURE_HEADER => self::sign( $body, $this->secret ),
						)
					),
					'body'    => $body,
				)
			);
		} catch ( \Throwable $e ) {
			throw new RuntimeException( 'Request to ' . $endpoint . ' failed: ' . $e->getMessage(), 0, $e );
		}

		$raw = trim( (string) $response->getBody() );
		if ( '' === $raw ) {
			return array();
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'Invalid response from ' . $endpoint . '.' );
		}

		return $data;
	}

	/**
	 * Downloads a file, e.g. an avatar. Not signed: it's not one of the helpdesk endpoints.
	 *
	 * @param string $url File URL.
	 * @return array {
	 *     @type string $body         File contents.
	 *     @type string $content_type Its media type.
	 * }
	 *
	 * @throws RuntimeException If the download fails.
	 */
	public function download( string $url ): array {
		try {
			$response = $this->http()->get( $url, array( 'http_errors' => false ) );
		} catch ( \Throwable $e ) {
			throw new RuntimeException( 'Download of ' . $url . ' failed: ' . $e->getMessage(), 0, $e );
		}

		if ( 200 !== $response->getStatusCode() ) {
			throw new RuntimeException( 'Download of ' . $url . ' failed with HTTP ' . $response->getStatusCode() . '.' );
		}

		return array(
			'body'         => (string) $response->getBody(),
			'content_type' => $response->getHeaderLine( 'Content-Type' ),
		);
	}

	/**
	 * Creates the HTTP client.
	 *
	 * @return \GuzzleHttp\Client
	 */
	private function http(): \GuzzleHttp\Client {
		// Whatever APP_CURL_SSL_VERIFYPEER says.
		$options = array(
			'timeout' => $this->timeout,
			'verify'  => true,
		);
		if ( $this->handler ) {
			$options['handler'] = \GuzzleHttp\HandlerStack::create( $this->handler );
		}

		return new \GuzzleHttp\Client( \Helper::setGuzzleDefaultOptions( $options ) );
	}
}
