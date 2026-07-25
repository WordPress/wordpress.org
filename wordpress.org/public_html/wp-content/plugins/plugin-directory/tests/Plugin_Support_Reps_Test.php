<?php
/**
 * Functional tests for the plugins/v1/plugin/<slug>/support-reps endpoints.
 *
 * The support rep management capabilities are granted through the plugin's
 * custom capability mapping, so these tests cover the access control
 * boundary.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/plugin/<slug>/support-reps endpoints.
 *
 * @group rest-api
 */
class Plugin_Support_Reps_Test extends Plugin_Directory_Endpoint_TestCase {

	/**
	 * ID of the plugin fixture.
	 *
	 * @var int
	 */
	protected static $plugin_id;

	/**
	 * ID of the user fixture.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Creates the plugin and user fixtures.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$plugin_id = self::create_plugin( 'support-reps-test-plugin', 'Support Reps Test Plugin' );
		self::$user_id   = wp_insert_user(
			array(
				'user_login' => 'support-reps-test-user',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'support-reps-test-user@example.org',
			)
		);
	}

	/**
	 * Deletes the fixtures.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );
		wp_delete_user( self::$user_id );

		parent::tearDownAfterClass();
	}

	/**
	 * Listing support reps requires permission on the plugin.
	 */
	public function test_listing_requires_permission() {
		$request  = new WP_REST_Request( 'GET', '/plugins/v1/plugin/support-reps-test-plugin/support-reps' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Adding a support rep requires permission on the plugin.
	 */
	public function test_adding_requires_permission() {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/support-reps-test-plugin/support-reps' );
		$request->set_param( 'support_rep', 'someuser' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Removing a support rep requires permission on the plugin.
	 */
	public function test_removing_requires_permission() {
		$request  = new WP_REST_Request( 'DELETE', '/plugins/v1/plugin/support-reps-test-plugin/support-reps/support-reps-test-user' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}
}
