<?php
/**
 * Functional tests for the plugins/v1/plugin/<slug>/blueprint.json endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/plugin/<slug>/blueprint.json endpoint.
 *
 * @group rest-api
 */
class Plugin_Blueprint_Test extends Plugin_Directory_Endpoint_TestCase {

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

		self::$plugin_id = self::create_plugin( 'blueprint-test-plugin', 'Blueprint Test Plugin' );
	}

	/**
	 * Deletes the plugin fixture.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );

		parent::tearDownAfterClass();
	}

	/**
	 * Requests for unknown plugins are rejected as invalid.
	 */
	public function test_rejects_unknown_plugin() {
		$request  = new WP_REST_Request( 'GET', '/plugins/v1/plugin/not-a-registered-plugin/blueprint.json' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * A plugin without a stored blueprint returns a 404.
	 */
	public function test_returns_404_without_blueprint() {
		$request  = new WP_REST_Request( 'GET', '/plugins/v1/plugin/blueprint-test-plugin/blueprint.json' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'no_blueprint', $response->get_data()['code'] );
	}
}
