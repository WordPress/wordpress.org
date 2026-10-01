<?php
/**
 * Client for the Akismet API.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Services;

use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Asks Akismet whether a message is spam, and tells it when agents disagree.
 *
 * @see https://akismet.com/developers/detailed-docs/
 */
final class Akismet {

	/**
	 * Verdict for a message Akismet considers spam.
	 *
	 * @var string
	 */
	public const SPAM = 'spam';

	/**
	 * Verdict for a message Akismet considers legitimate.
	 *
	 * @var string
	 */
	public const HAM = 'ham';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Base URL of the API, with a trailing slash.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * HTTP client.
	 *
	 * @var ClientInterface
	 */
	private $http;

	/**
	 * Constructor.
	 *
	 * @param string          $key     API key.
	 * @param string          $api_url Base URL of the API.
	 * @param ClientInterface $http    HTTP client.
	 */
	public function __construct( string $key, string $api_url, ClientInterface $http ) {
		$this->key     = $key;
		$this->api_url = rtrim( $api_url, '/' ) . '/';
		$this->http    = $http;
	}

	/**
	 * Creates a client from the module configuration.
	 *
	 * @return self
	 */
	public static function from_config(): self {
		return new self(
			(string) config( 'wporgakismet.key' ),
			(string) config( 'wporgakismet.api_url' ),
			new \GuzzleHttp\Client(
				\Helper::setGuzzleDefaultOptions(
					array(
						// Mail fetching waits for the answer.
						'timeout'         => 5,
						'connect_timeout' => 3,
						'allow_redirects' => false,
						'headers'         => array( 'User-Agent' => 'FreeScout/' . config( 'app.version' ) . ' | WPOrgAkismet/1.0.0' ),
					)
				)
			)
		);
	}

	/**
	 * Whether an API key is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->key;
	}

	/**
	 * Asks Akismet whether a message is spam.
	 *
	 * @param array $message Message fields, as built by Message::fields().
	 * @return string self::SPAM or self::HAM.
	 *
	 * @throws RuntimeException If Akismet couldn't be asked, or didn't give a verdict.
	 */
	public function check( array $message ): string {
		$answer = $this->post( 'comment-check', $message );

		if ( 'true' === $answer ) {
			return self::SPAM;
		}

		if ( 'false' === $answer ) {
			return self::HAM;
		}

		throw new RuntimeException( 'Akismet gave no verdict: ' . $answer );
	}

	/**
	 * Tells Akismet that it got a message wrong, so it learns from agents.
	 *
	 * @param string $verdict What the message really is: self::SPAM or self::HAM.
	 * @param array  $message Message fields, as built by Message::fields() when it was checked.
	 * @return void
	 *
	 * @throws RuntimeException If Akismet couldn't be told.
	 */
	public function submit( string $verdict, array $message ): void {
		$this->post( self::SPAM === $verdict ? 'submit-spam' : 'submit-ham', $message );
	}

	/**
	 * Posts to an API method.
	 *
	 * @param string $method  API method.
	 * @param array  $message Message fields.
	 * @return string Response body.
	 *
	 * @throws RuntimeException If the request failed, or Akismet reported an error.
	 */
	private function post( string $method, array $message ): string {
		if ( ! $this->is_configured() ) {
			throw new RuntimeException( 'WPORG_AKISMET_KEY is not configured.' );
		}

		try {
			$response = $this->http->request(
				'POST',
				$this->api_url . $method,
				array( 'form_params' => array( 'api_key' => $this->key ) + $message )
			);
		} catch ( \Throwable $e ) {
			throw new RuntimeException( 'Akismet ' . $method . ' failed: ' . $e->getMessage(), 0, $e );
		}

		$help = $response->getHeaderLine( 'X-akismet-debug-help' );
		if ( '' !== $help ) {
			throw new RuntimeException( 'Akismet ' . $method . ' failed: ' . $help );
		}

		return trim( (string) $response->getBody() );
	}
}
