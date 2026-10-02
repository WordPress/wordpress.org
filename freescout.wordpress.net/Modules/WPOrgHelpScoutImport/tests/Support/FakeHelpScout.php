<?php
/**
 * A stand-in for HelpScout's API.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Psr\Http\Message\RequestInterface;

/**
 * Answers requests by method and path, and records them.
 */
final class FakeHelpScout {

	/**
	 * Answers by "METHOD path", each a list used in turn; the last one repeats.
	 *
	 * @var array
	 */
	private $answers = array();

	/**
	 * Requests received.
	 *
	 * @var RequestInterface[]
	 */
	public $requests = array();

	/**
	 * Seconds the client waited for the rate limit, each time.
	 *
	 * @var int[]
	 */
	public $slept = array();

	/**
	 * Constructor: answers token requests.
	 */
	public function __construct() {
		$this->on(
			'POST',
			'v2/oauth2/token',
			array(
				'access_token' => 'token-1',
				'expires_in'   => 172800,
			)
		);
	}

	/**
	 * Adds an answer.
	 *
	 * @param string         $method HTTP method.
	 * @param string         $path   Path, without the leading slash.
	 * @param array|Response $answer JSON body, or a whole response.
	 * @return self
	 */
	public function on( string $method, string $path, $answer ): self {
		$this->answers[ $method . ' ' . $path ][] = $answer instanceof Response ? $answer : self::json( $answer );

		return $this;
	}

	/**
	 * Replaces a path's answers.
	 *
	 * @param string         $method HTTP method.
	 * @param string         $path   Path.
	 * @param array|Response $answer JSON body, or a whole response.
	 * @return self
	 */
	public function only( string $method, string $path, $answer ): self {
		unset( $this->answers[ $method . ' ' . $path ] );

		return $this->on( $method, $path, $answer );
	}

	/**
	 * Replaces the answers for one mailbox's users; other requests get the path's answers.
	 *
	 * @param int            $mailbox HelpScout mailbox ID.
	 * @param array|Response $answer  JSON body, or a whole response.
	 * @return self
	 */
	public function only_mailbox_users( int $mailbox, $answer ): self {
		return $this->only( 'GET', 'v2/users?mailbox=' . $mailbox, $answer );
	}

	/**
	 * Replaces the answers for one page of a list; other pages get the path's answers.
	 *
	 * @param string         $path   Path.
	 * @param int            $page   Page number.
	 * @param array|Response $answer JSON body, or a whole response.
	 * @return self
	 */
	public function only_page( string $path, int $page, $answer ): self {
		return $this->only( 'GET', $path . '?page=' . $page, $answer );
	}

	/**
	 * A JSON response.
	 *
	 * @param array $body    Body.
	 * @param int   $status  HTTP status.
	 * @param array $headers Headers.
	 * @return Response
	 */
	public static function json( array $body, int $status = 200, array $headers = array() ): Response {
		return new Response( $status, $headers + array( 'Content-Type' => 'application/hal+json' ), (string) json_encode( $body ) );
	}

	/**
	 * A client talking to this fake.
	 *
	 * @param int $reserve Requests per minute left to others.
	 * @return HelpScout
	 */
	public function client( int $reserve = 0 ): HelpScout {
		$http = new Client(
			array(
				'handler'     => function ( RequestInterface $request ): FulfilledPromise {
					return new FulfilledPromise( $this->answer( $request ) );
				},
				'http_errors' => false,
			)
		);

		return new HelpScout(
			'app-id',
			'app-secret',
			'https://helpscout.test/',
			$reserve,
			$http,
			function ( int $seconds ): void {
				$this->slept[] = $seconds;
			}
		);
	}

	/**
	 * Requests to a path.
	 *
	 * @param string $path Path.
	 * @return RequestInterface[]
	 */
	public function requests_to( string $path ): array {
		return array_values(
			array_filter(
				$this->requests,
				static function ( RequestInterface $request ) use ( $path ): bool {
					return '/' . $path === $request->getUri()->getPath();
				}
			)
		);
	}

	/**
	 * Answers a request.
	 *
	 * @param RequestInterface $request Request.
	 * @return Response
	 */
	private function answer( RequestInterface $request ): Response {
		$this->requests[] = $request;
		$key              = $request->getMethod() . ' ' . ltrim( $request->getUri()->getPath(), '/' );

		parse_str( $request->getUri()->getQuery(), $query );
		foreach ( array( 'page', 'mailbox' ) as $param ) {
			if ( isset( $query[ $param ], $this->answers[ $key . '?' . $param . '=' . $query[ $param ] ] ) ) {
				$key .= '?' . $param . '=' . $query[ $param ];
				break;
			}
		}

		if ( empty( $this->answers[ $key ] ) ) {
			return self::json( array( 'error' => 'Not found: ' . $key ), 404 );
		}

		return count( $this->answers[ $key ] ) > 1 ? array_shift( $this->answers[ $key ] ) : $this->answers[ $key ][0];
	}
}
