<?php
/**
 * Functional tests for the plugins/v1/upload endpoints.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/upload endpoints.
 *
 * @group rest-api
 */
class Plugin_Upload_Test extends Plugin_Directory_Endpoint_TestCase {

	/**
	 * ID of the plugin author fixture.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * ID of the newly submitted plugin fixture.
	 *
	 * @var int
	 */
	protected static $plugin_id;

	/**
	 * Creates the author and their newly submitted plugin.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$author_id = wp_insert_user(
			array(
				'user_login' => 'upload-test-author',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'upload-test-author@example.org',
			)
		);

		$args = array(
			'post_status' => 'new',
			'post_author' => self::$author_id,
		);

		self::$plugin_id = self::create_plugin( 'upload-test-plugin', 'Upload Test Plugin', $args );
	}

	/**
	 * Deletes the fixtures.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );
		wp_delete_user( self::$author_id );
		wp_set_current_user( 0 );

		parent::tearDownAfterClass();
	}

	/**
	 * Resets the current user after each test.
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Changing a slug requires being the plugin author or a reviewer.
	 */
	public function test_slug_change_requires_permission() {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/upload/' . self::$plugin_id . '/slug' );
		$request->set_param( 'post_name', 'some-other-plugin-slug' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * The slug is required when requesting a slug change.
	 */
	public function test_slug_change_requires_slug() {
		wp_set_current_user( self::$author_id );

		$request  = new WP_REST_Request( 'POST', '/plugins/v1/upload/' . self::$plugin_id . '/slug' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );
	}

	/**
	 * Invalid slugs are rejected.
	 */
	public function test_slug_change_rejects_invalid_slug() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/plugins/v1/upload/' . self::$plugin_id . '/slug' );
		$request->set_param( 'post_name', 'Not A Valid Slug!' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'error', $response->get_data()['code'] );
		$this->assertSame( 'upload-test-plugin', get_post( self::$plugin_id )->post_name );
	}

	/**
	 * The plugin author can change the slug of their newly submitted plugin.
	 */
	public function test_author_can_change_slug() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/plugins/v1/upload/' . self::$plugin_id . '/slug' );
		$request->set_param( 'post_name', 'aurora-borealis-notes' );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data() );
		$this->assertSame( 'aurora-borealis-notes', get_post( self::$plugin_id )->post_name );
		$this->assertSame( 'upload-test-plugin', get_post_meta( self::$plugin_id, '_wporg_plugin_original_slug', true ) );
	}
}
