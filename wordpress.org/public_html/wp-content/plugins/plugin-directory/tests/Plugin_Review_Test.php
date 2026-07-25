<?php
/**
 * Functional tests for the plugins/v1/plugin-review endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/plugin-review endpoint.
 *
 * @group rest-api
 */
class Plugin_Review_Test extends Plugin_Directory_Endpoint_TestCase {

	/**
	 * ID of the plugin fixture.
	 *
	 * @var int
	 */
	protected static $plugin_id;

	/**
	 * Creates the plugin fixture.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$plugin_id = self::create_plugin( 'review-test-plugin', 'Review Test Plugin' );
	}

	/**
	 * Deletes the plugin fixture.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );

		parent::tearDownAfterClass();
	}

	/**
	 * Access is denied without a valid review token.
	 */
	public function test_denies_invalid_token() {
		$route    = sprintf( '/plugins/v1/plugin-review/%d-%s', self::$plugin_id, str_repeat( 'f', 32 ) );
		$request  = new WP_REST_Request( 'GET', $route );
		$response = self::server()->dispatch( $request );

		// 401 for anonymous requests, 403 for authenticated ones.
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	/**
	 * Access is denied for plugins that do not exist.
	 */
	public function test_denies_unknown_plugin() {
		$route    = sprintf( '/plugins/v1/plugin-review/%d-%s', self::$plugin_id + 1000, str_repeat( 'f', 32 ) );
		$request  = new WP_REST_Request( 'GET', $route );
		$response = self::server()->dispatch( $request );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}
}
