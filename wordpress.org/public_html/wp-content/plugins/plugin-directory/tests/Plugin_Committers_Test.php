<?php
/**
 * Functional tests for the plugins/v1/plugin/<slug>/committers endpoints.
 *
 * The committer management capabilities are granted through the plugin's
 * custom capability mapping, which is tied to the production SVN access
 * table, so these tests cover the access control boundary.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/plugin/<slug>/committers endpoints.
 *
 * @group rest-api
 */
class Plugin_Committers_Test extends Plugin_Directory_Endpoint_TestCase {

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

		self::$plugin_id = self::create_plugin( 'committers-test-plugin', 'Committers Test Plugin' );
		self::$user_id   = wp_insert_user(
			array(
				'user_login' => 'committers-test-user',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'committers-test-user@example.org',
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
	 * Listing committers requires permission on the plugin.
	 */
	public function test_listing_requires_permission() {
		$request  = new WP_REST_Request( 'GET', '/plugins/v1/plugin/committers-test-plugin/committers' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Adding a committer requires permission on the plugin.
	 */
	public function test_adding_requires_permission() {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/committers-test-plugin/committers' );
		$request->set_param( 'committer', 'someuser' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Removing a committer requires permission on the plugin.
	 */
	public function test_removing_requires_permission() {
		$request  = new WP_REST_Request( 'DELETE', '/plugins/v1/plugin/committers-test-plugin/committers/committers-test-user' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}
}
