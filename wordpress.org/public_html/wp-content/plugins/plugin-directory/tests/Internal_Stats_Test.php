<?php
/**
 * Functional tests for the plugins/v1/update-stats endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/update-stats endpoint.
 *
 * @group rest-api
 */
class Internal_Stats_Test extends Plugin_Directory_Endpoint_TestCase {

	/**
	 * ID of the published plugin fixture.
	 *
	 * @var int
	 */
	protected static $plugin_id;

	/**
	 * Creates the plugin fixture.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$plugin_id = self::create_plugin( 'stats-test-plugin', 'Stats Test Plugin' );
	}

	/**
	 * Deletes the plugin fixture.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );

		parent::tearDownAfterClass();
	}

	/**
	 * Updating stats requires the internal bearer token.
	 */
	public function test_requires_authentication() {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/update-stats' );
		$request->set_param( 'plugins', array( 'stats-test-plugin' => array( 'active_installs' => 100 ) ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'not_authorized', $response->get_data()['code'] );
	}

	/**
	 * Whitelisted stats are written to the plugin, while unknown plugins
	 * and unknown stat names are silently skipped.
	 */
	public function test_updates_plugin_stats() {
		$stats = array(
			'stats-test-plugin'       => array(
				'active_installs' => 5000,
				'support_threads' => '7',
				'unknown_stat'    => 'ignored',
			),
			'not-a-registered-plugin' => array(
				'active_installs' => 100,
			),
		);

		$request = $this->authorized_request();
		$request->set_param( 'plugins', $stats );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data() );
		$this->assertEquals( 5000, get_post_meta( self::$plugin_id, '_active_installs', true ) );
		$this->assertEquals( 7, get_post_meta( self::$plugin_id, 'support_threads', true ) );
		$this->assertSame( '', get_post_meta( self::$plugin_id, 'unknown_stat', true ) );
	}

	/**
	 * A malformed entry is skipped without rejecting the rest of the batch.
	 */
	public function test_skips_malformed_entries() {
		$stats = array(
			'stats-test-plugin'  => array(
				'active_installs' => 100,
			),
			'malformed-entry'    => 'not-an-object',
		);

		$request = $this->authorized_request();
		$request->set_param( 'plugins', $stats );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data() );
		$this->assertEquals( 100, get_post_meta( self::$plugin_id, '_active_installs', true ) );
	}

	/**
	 * A scalar plugins parameter is rejected instead of being iterated.
	 */
	public function test_rejects_malformed_body() {
		$request = $this->authorized_request();
		$request->set_param( 'plugins', 'not-an-object' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * A request without stats data is rejected.
	 */
	public function test_requires_stats_data() {
		$request = $this->authorized_request();

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );
	}

	/**
	 * Builds an update-stats request carrying the internal bearer token.
	 *
	 * @return WP_REST_Request The authorized request.
	 */
	protected function authorized_request() {
		if ( ! defined( 'PLUGIN_API_INTERNAL_BEARER_TOKEN' ) ) {
			define( 'PLUGIN_API_INTERNAL_BEARER_TOKEN', 'plugin-directory-test-token' );
		}

		$request = new WP_REST_Request( 'POST', '/plugins/v1/update-stats' );
		$request->set_header( 'Authorization', 'Bearer ' . PLUGIN_API_INTERNAL_BEARER_TOKEN );

		return $request;
	}
}
