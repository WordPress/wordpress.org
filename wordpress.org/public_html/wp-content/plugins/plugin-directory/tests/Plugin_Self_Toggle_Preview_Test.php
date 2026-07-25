<?php
/**
 * Functional tests for the plugins/v1/plugin/<slug>/self-toggle-preview endpoint.
 *
 * Toggling the preview requires the plugin_toggle_public_preview
 * capability, which is granted through the plugin's custom capability
 * mapping, so these tests cover the access control boundary.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/plugin/<slug>/self-toggle-preview endpoint.
 *
 * @group rest-api
 */
class Plugin_Self_Toggle_Preview_Test extends Plugin_Directory_Endpoint_TestCase {

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

		self::$plugin_id = self::create_plugin( 'preview-toggle-test-plugin', 'Preview Toggle Test Plugin' );
	}

	/**
	 * Deletes the plugin fixture.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );

		parent::tearDownAfterClass();
	}

	/**
	 * Toggling the preview requires permission on the plugin.
	 */
	public function test_toggling_requires_permission() {
		$request  = new WP_REST_Request( 'POST', '/plugins/v1/plugin/preview-toggle-test-plugin/self-toggle-preview' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Dismissing the blueprint notice requires permission on the plugin.
	 */
	public function test_dismissing_requires_permission() {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/preview-toggle-test-plugin/self-toggle-preview' );
		$request->set_param( 'dismiss', true );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}
}
