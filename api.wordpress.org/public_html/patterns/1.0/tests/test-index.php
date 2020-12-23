<?php

namespace WordPressdotorg\API\Patterns\Tests;
use PHPUnit\Framework\TestCase;
use Requests, Requests_Response;

/**
 * @group patterns
 */
class Test_Patterns extends TestCase {
	/**
	 * Make an API request to the current sandbox.
	 */
	private function send_request( string $query_string ) : Requests_Response {
		global $wporg_sandbox_hostname;

		/*
		 * This has to use HTTP rather than HTTPS, because the request will be made from the sandbox, which is
		 * already behind the load balancer.
		 */
		$url = sprintf(
			'http://%s/patterns/1.0%s',
			$wporg_sandbox_hostname,
			$query_string
		);

		$headers = array(
			'Accept' => 'application/json',
			'Host'   => 'api.wordpress.org',
		);

		/*
		 * Warning: Only make `get()` requests in this suite.
		 *
		 * POST/UPDATE/DELETE requests would change production data, so those would have to be done in a local
		 * environment.
		 */
		return Requests::get( $url, $headers );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_get_all_patterns() : void {
		$response = $this->send_request( '/?action=get_patterns' );
		$body     = json_decode( $response->body );

		$this->assertSame( 200, $response->status_code );
		$this->assertIsString( $body[0]->title->rendered );
		$this->assertIsInt( $body[0]->meta->wpop_viewport_width );
	}
}
