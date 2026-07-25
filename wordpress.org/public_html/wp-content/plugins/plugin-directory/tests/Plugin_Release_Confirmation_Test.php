<?php
/**
 * Functional tests for the plugins/v1 release-confirmation endpoints.
 *
 * Managing releases requires the plugin_manage_releases capability and a
 * two-factor session, which are tied to production infrastructure, so
 * these tests cover the access control boundary.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1 release-confirmation endpoints.
 *
 * @group rest-api
 */
class Plugin_Release_Confirmation_Test extends Plugin_Directory_Endpoint_TestCase {

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

		self::$plugin_id = self::create_plugin( 'release-test-plugin', 'Release Test Plugin' );
	}

	/**
	 * Deletes the plugin fixture.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );

		parent::tearDownAfterClass();
	}

	/**
	 * Enabling release confirmations requires permission on the plugin.
	 */
	public function test_enabling_requires_permission() {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/release-test-plugin/release-confirmation' );
		$request->set_param( 'confirmations_required', 1 );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Confirming a release is rejected for tags without a known release.
	 */
	public function test_confirming_rejects_unknown_release() {
		$request  = new WP_REST_Request( 'POST', '/plugins/v1/plugin/release-test-plugin/release-confirmation/1.0' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}
}
