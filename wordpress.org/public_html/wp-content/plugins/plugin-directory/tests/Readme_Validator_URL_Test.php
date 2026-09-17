<?php
/**
 * Tests for Validator::validate_url().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Readme\Validator;

/**
 * Covers which readme URLs the validator is willing to fetch.
 *
 * The validator prints the fetched body back to an anonymous caller, both from the
 * `[readme-validator]` shortcode and from the `validate-readme` ability, so the set of
 * destinations it will connect to is the boundary that keeps it from being a proxy.
 *
 * @group readme
 */
class Readme_Validator_URL_Test extends TestCase {

	/**
	 * Requests the validator attempted, as `url` / `args` pairs.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $requests = array();

	/**
	 * The `pre_http_request` callback recording requests, kept so it can be removed again.
	 *
	 * @var callable|null
	 */
	private $request_spy = null;

	/**
	 * Records outbound requests instead of making them.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->requests    = array();
		$this->request_spy = function ( $preempt, $args, $url ) {
			$this->requests[] = array(
				'url'  => $url,
				'args' => $args,
			);

			return self::http_response( '' );
		};

		add_filter( 'pre_http_request', $this->request_spy, 10, 3 );
	}

	/**
	 * Builds a successful HTTP response for the request filter to return.
	 *
	 * @param string $body The response body.
	 * @return array<string, mixed>
	 */
	private static function http_response( string $body ): array {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Removes the request spy.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', $this->request_spy, 10 );
		$this->request_spy = null;

		parent::tearDown();
	}

	/**
	 * An allowed readme is fetched over HTTPS, without following redirects.
	 *
	 * The scheme matters because two of the three allowed hosts answer plain HTTP with a
	 * redirect, which would be reported as an invalid URL rather than followed.
	 *
	 * @dataProvider allowed_url_provider
	 *
	 * @param string $url The URL under test.
	 * @return void
	 */
	public function test_allowed_url_is_fetched( string $url ): void {
		Validator::instance()->validate_url( $url );

		$this->assertCount( 1, $this->requests, "Expected '{$url}' to be fetched" );
		$this->assertStringStartsWith( 'https://', $this->requests[0]['url'] );
		$this->assertSame( 0, $this->requests[0]['args']['redirection'] );
		$this->assertSame( Validator::MAX_FETCH_BYTES, $this->requests[0]['args']['limit_response_size'] );
	}

	/**
	 * A readme within the size limit is handed to the parser.
	 *
	 * @return void
	 */
	public function test_readme_within_size_limit_is_validated(): void {
		$readme = "=== Readme Validator Test ===\nStable tag: 1.0\n\nA readme served to the test suite.\n";
		$result = $this->validate_with_body( $readme );

		$this->assertSame( $readme, Validator::instance()->last_content );
		$this->assertArrayNotHasKey( 'invalid_url', $result['errors'] );
		$this->assertArrayNotHasKey( 'readme_too_large', $result['errors'] );
	}

	/**
	 * A response that reached the size limit is reported rather than validated.
	 *
	 * @return void
	 */
	public function test_truncated_response_is_reported(): void {
		$result = $this->validate_with_body( str_repeat( 'a', Validator::MAX_FETCH_BYTES ) );

		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( '512 KB', $result['errors']['readme_too_large'] );
	}

	/**
	 * A URL that does not resolve to a readme is reported rather than parsed.
	 *
	 * Every allowed host answers a missing file with a non-empty body, so the status code is
	 * the only thing separating a readme from an error page. A redirect lands here too, since
	 * redirects are not followed.
	 *
	 * @dataProvider non_200_response_provider
	 *
	 * @param int    $code The response status code.
	 * @param string $body The response body.
	 * @return void
	 */
	public function test_non_200_response_is_reported( int $code, string $body ): void {
		$respond = function () use ( $code, $body ) {
			$response                     = self::http_response( $body );
			$response['response']['code'] = $code;

			return $response;
		};

		add_filter( 'pre_http_request', $respond, 20 );
		$result = Validator::instance()->validate_url( 'https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt' );
		remove_filter( 'pre_http_request', $respond, 20 );

		$this->assertSame( array( 'invalid_url' ), array_keys( $result['errors'] ) );
	}

	/**
	 * Supplies responses that do not carry a readme.
	 *
	 * @return array<string, array<int, int|string>>
	 */
	public static function non_200_response_provider(): array {
		return array(
			// Format: status code, response body.
			'github not found' => array( 404, '404: Not Found' ),
			'svn not found'    => array( 404, '<html><title>404 Not Found</title></html>' ),
			'redirect'         => array( 301, '' ),
			'server error'     => array( 500, 'Internal Server Error' ),
		);
	}

	/**
	 * Validates an allowed URL against a canned response body.
	 *
	 * @param string $body The body the fetch should return.
	 * @return array The validation results.
	 */
	private function validate_with_body( string $body ): array {
		$respond = function () use ( $body ) {
			return self::http_response( $body );
		};

		add_filter( 'pre_http_request', $respond, 20 );
		$result = Validator::instance()->validate_url( 'https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt' );
		remove_filter( 'pre_http_request', $respond, 20 );

		return $result;
	}

	/**
	 * A readme anywhere else is rejected, and no request is made for it.
	 *
	 * @dataProvider disallowed_host_provider
	 *
	 * @param string $url The URL under test.
	 * @return void
	 */
	public function test_disallowed_host_is_not_fetched( string $url ): void {
		$result = Validator::instance()->validate_url( $url );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertSame( array(), $this->requests, "An outbound request was made for '{$url}'" );
	}

	/**
	 * An allowed host still has to name a readme, and no request is made if it does not.
	 *
	 * @dataProvider disallowed_path_provider
	 *
	 * @param string $url The URL under test.
	 * @return void
	 */
	public function test_disallowed_path_is_not_fetched( string $url ): void {
		$result = Validator::instance()->validate_url( $url );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertSame( array(), $this->requests, "An outbound request was made for '{$url}'" );
	}

	/**
	 * Supplies the URLs the validator is meant to fetch.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function allowed_url_provider(): array {
		return array(
			// Format: [ $url ].
			'plugin readme'   => array( 'https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt' ),
			'tagged readme'   => array( 'https://plugins.svn.wordpress.org/hello-dolly/tags/1.7.2/readme.txt' ),
			'markdown readme' => array( 'https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.md' ),
			'theme readme'    => array( 'https://themes.svn.wordpress.org/twentytwentyfive/1.0/readme.txt' ),
			'github raw'      => array( 'https://raw.githubusercontent.com/Automattic/jetpack/trunk/projects/plugins/jetpack/readme.txt' ),
			'mixed case host' => array( 'https://Plugins.SVN.WordPress.org/hello-dolly/trunk/readme.txt' ),
			'plain http'      => array( 'http://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt' ),
			'query string'    => array( 'https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt?p=1' ),
		);
	}

	/**
	 * Supplies hosts the validator must refuse to connect to.
	 *
	 * The addresses here are not all rejected by `wp_safe_remote_get()`: Core's private-range
	 * check covers neither 169.254.0.0/16 nor 100.64.0.0/10, and any of them can be reached
	 * through a hostname that resolves differently for the check and for the connection.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function disallowed_host_provider(): array {
		return array(
			// Format: [ $url ].
			'unrelated host'      => array( 'https://example.org/readme.txt' ),
			'loopback'            => array( 'http://127.0.0.1/readme.txt' ),
			'loopback by name'    => array( 'http://localhost/readme.txt' ),
			'private range'       => array( 'http://10.0.0.5:8080/readme.txt' ),
			'link local'          => array( 'http://169.254.169.254/readme.txt' ),
			'carrier grade nat'   => array( 'http://100.64.0.1/readme.txt' ),
			'userinfo prefix'     => array( 'https://plugins.svn.wordpress.org@example.org/readme.txt' ),
			'suffix lookalike'    => array( 'https://plugins.svn.wordpress.org.example.org/readme.txt' ),
			'prefix lookalike'    => array( 'https://notplugins.svn.wordpress.org/readme.txt' ),
			'subdomain lookalike' => array( 'https://a.plugins.svn.wordpress.org/readme.txt' ),
			'directory front end' => array( 'https://wordpress.org/plugins/hello-dolly/readme.txt' ),
			'github blob page'    => array( 'https://github.com/Automattic/jetpack/blob/trunk/readme.txt' ),
			'github raw path'     => array( 'https://github.com/Automattic/jetpack/raw/trunk/readme.txt' ),
			'github lookalike'    => array( 'https://raw.githubusercontent.com.example.org/readme.txt' ),
			'ftp scheme'          => array( 'ftp://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt' ),
			'file scheme'         => array( 'file:///etc/passwd' ),
			'bare filename'       => array( 'readme.txt' ),
			'empty string'        => array( '' ),
		);
	}

	/**
	 * Supplies allowed hosts whose path does not name a readme.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function disallowed_path_provider(): array {
		return array(
			// Format: [ $url ].
			'directory'       => array( 'https://plugins.svn.wordpress.org/hello-dolly/trunk/' ),
			'another file'    => array( 'https://plugins.svn.wordpress.org/hello-dolly/trunk/hello.php' ),
			'readme in query' => array( 'https://plugins.svn.wordpress.org/hello-dolly/trunk/hello.php?f=readme.txt' ),
			'host only'       => array( 'https://plugins.svn.wordpress.org' ),
		);
	}
}
