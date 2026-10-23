<?php
/**
 * A stand-in for sources' APIs and attachment links.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Answers requests by host and path, or by host, path, and page, and records them.
 */
final class FakeSource {

	/**
	 * Address of the fake source the tests import from.
	 *
	 * @var string
	 */
	public const URL = 'https://scout.test';

	/**
	 * Host of that source.
	 *
	 * @var string
	 */
	public const HOST = 'scout.test';

	/**
	 * Answers by "host/path", or "host/path?page=N".
	 *
	 * @var Response[]
	 */
	private $answers = array();

	/**
	 * Requests received.
	 *
	 * @var RequestInterface[]
	 */
	public $requests = array();

	/**
	 * Sets the answer for an API path, or for one page of it.
	 *
	 * @param string   $path Path, like `api/conversations`.
	 * @param array    $body JSON body.
	 * @param int|null $page Page, or null for every page.
	 * @param string   $host Host of the source that answers.
	 * @return self
	 */
	public function json( string $path, array $body, ?int $page = null, string $host = self::HOST ): self {
		$this->answers[ $host . '/' . $path . ( null === $page ? '' : '?page=' . $page ) ] = new Response( 200, array( 'Content-Type' => 'application/json' ), (string) json_encode( $body ) );

		return $this;
	}

	/**
	 * Sets the answer for a file's URL.
	 *
	 * @param string $url     URL.
	 * @param string $content File content.
	 * @return self
	 */
	public function file( string $url, string $content ): self {
		$this->answers[ parse_url( $url, PHP_URL_HOST ) . parse_url( $url, PHP_URL_PATH ) ] = new Response( 200, array(), $content );

		return $this;
	}

	/**
	 * An HTTP client talking to this fake, for every source.
	 *
	 * @return ClientInterface
	 */
	public function http(): ClientInterface {
		return new Client(
			array(
				'handler'     => function ( RequestInterface $request ): FulfilledPromise {
					return new FulfilledPromise( $this->answer( $request ) );
				},
				'http_errors' => false,
			)
		);
	}

	/**
	 * Hosts requests went to.
	 *
	 * @return string[]
	 */
	public function hosts(): array {
		return array_values(
			array_unique(
				array_map(
					static function ( RequestInterface $request ): string {
						return $request->getUri()->getHost();
					},
					$this->requests
				)
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
		$key              = $request->getUri()->getHost() . $request->getUri()->getPath();

		parse_str( $request->getUri()->getQuery(), $query );
		if ( isset( $query['page'], $this->answers[ $key . '?page=' . $query['page'] ] ) ) {
			$key .= '?page=' . $query['page'];
		}

		return $this->answers[ $key ] ?? new Response( 404, array(), 'Not found: ' . $key );
	}
}
