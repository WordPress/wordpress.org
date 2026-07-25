<?php
/**
 * Functional tests for the themes/v1/github auto-review endpoint.
 *
 * Updating a ticket requires Trac credentials and its XML-RPC endpoint,
 * so these tests cover the access control boundary.
 *
 * @package theme-directory
 */

/**
 * Functional tests for the themes/v1/github auto-review endpoint.
 *
 * @group rest-api
 */
class Themes_Auto_Review_Test extends Theme_Directory_Endpoint_TestCase {

	/**
	 * Updating a ticket requires the GitHub bearer token.
	 */
	public function test_requires_authentication() {
		$request = new WP_REST_Request( 'POST', '/themes/v1/github/some-theme/12345' );
		$request->set_header( 'Authorization', 'Bearer not-the-right-token' );
		$request->set_body( 'Test results' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'not_authorized', $response->get_data()['code'] );
	}
}
