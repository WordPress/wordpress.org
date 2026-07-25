<?php
/**
 * Functional tests for the themes/v1 internal endpoints.
 *
 * The svn-auth endpoint prints its output and exits, so only its access
 * control is covered here.
 *
 * @package theme-directory
 */

/**
 * Functional tests for the themes/v1 internal endpoints.
 *
 * @group rest-api
 */
class Internal_Endpoints_Test extends Theme_Directory_Endpoint_TestCase {

	/**
	 * ID of the published theme fixture.
	 *
	 * @var int
	 */
	protected static $theme_id;

	/**
	 * Creates the theme fixture.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$theme_id = self::create_theme( 'stats-test-theme', 'Stats Test Theme' );
	}

	/**
	 * Deletes the theme fixture.
	 */
	public static function tearDownAfterClass(): void {
		self::delete_theme( self::$theme_id );

		parent::tearDownAfterClass();
	}

	/**
	 * Updating stats requires the internal bearer token.
	 */
	public function test_update_stats_requires_authentication() {
		$request = new WP_REST_Request( 'POST', '/themes/v1/update-stats' );
		$request->set_param( 'themes', array( 'stats-test-theme' => array( 'active_installs' => 100 ) ) );
		$request->set_header( 'Authorization', 'Bearer not-the-right-token' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'not_authorized', $response->get_data()['code'] );
	}

	/**
	 * Active install counts are rounded down and written to the theme,
	 * while unknown themes are silently skipped.
	 */
	public function test_update_stats_updates_theme_stats() {
		$stats = array(
			'stats-test-theme'       => array(
				'active_installs' => 1234,
			),
			'not-a-registered-theme' => array(
				'active_installs' => 100,
			),
		);

		$request = $this->authorized_request();
		$request->set_param( 'themes', $stats );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data() );
		$this->assertEquals( 1000, get_post_meta( self::$theme_id, '_active_installs', true ) );
	}

	/**
	 * A malformed entry is skipped without rejecting the rest of the batch.
	 */
	public function test_update_stats_skips_malformed_entries() {
		$stats = array(
			'stats-test-theme' => array(
				'active_installs' => 5678,
			),
			'malformed-entry'  => 'not-an-object',
		);

		$request = $this->authorized_request();
		$request->set_param( 'themes', $stats );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data() );
		$this->assertEquals( 5000, get_post_meta( self::$theme_id, '_active_installs', true ) );
	}

	/**
	 * A scalar themes parameter is rejected instead of being iterated.
	 */
	public function test_update_stats_rejects_malformed_body() {
		$request = $this->authorized_request();
		$request->set_param( 'themes', 'not-an-object' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * A request without stats data is rejected.
	 */
	public function test_update_stats_requires_stats_data() {
		$request = $this->authorized_request();

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );
	}

	/**
	 * The SVN auth file requires its bearer token.
	 */
	public function test_svn_auth_requires_authentication() {
		$request = new WP_REST_Request( 'GET', '/themes/v1/svn-auth' );
		$request->set_header( 'Authorization', 'Bearer not-the-right-token' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'not_authorized', $response->get_data()['code'] );
	}

	/**
	 * Builds an update-stats request carrying the internal bearer token.
	 *
	 * @return WP_REST_Request The authorized request.
	 */
	protected function authorized_request() {
		if ( ! defined( 'THEME_API_INTERNAL_BEARER_TOKEN' ) ) {
			define( 'THEME_API_INTERNAL_BEARER_TOKEN', 'theme-directory-test-token' );
		}

		$request = new WP_REST_Request( 'POST', '/themes/v1/update-stats' );
		$request->set_header( 'Authorization', 'Bearer ' . THEME_API_INTERNAL_BEARER_TOKEN );

		return $request;
	}
}
