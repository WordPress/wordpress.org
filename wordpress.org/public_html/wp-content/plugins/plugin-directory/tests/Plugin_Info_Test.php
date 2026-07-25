<?php
/**
 * Functional tests for the plugins/v1/plugin/<slug> information endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/plugin/<slug> information endpoint.
 *
 * @group rest-api
 */
class Plugin_Info_Test extends Plugin_Directory_Endpoint_TestCase {

	/**
	 * ID of the published plugin fixture.
	 *
	 * @var int
	 */
	protected static $plugin_id;

	/**
	 * ID of the plugin author fixture.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Creates the author and plugin fixtures.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$author_id = wp_insert_user(
			array(
				'user_login' => 'info-test-author',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'info-test-author@example.org',
			)
		);

		$args = array(
			'post_author' => self::$author_id,
		);

		self::$plugin_id = self::create_plugin( 'info-test-plugin', 'Info Test Plugin', $args );
	}

	/**
	 * Deletes the fixtures.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );
		wp_delete_user( self::$author_id );

		parent::tearDownAfterClass();
	}

	/**
	 * The endpoint returns the plugin information.
	 */
	public function test_returns_plugin_information() {
		$request  = new WP_REST_Request( 'GET', '/plugins/v1/plugin/info-test-plugin' );
		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'info-test-plugin', $data['slug'] );
		$this->assertSame( 'Info Test Plugin', $data['name'] );
	}

	/**
	 * Hostile locale input is sanitized before reaching switch_to_locale()
	 * and does not break the response.
	 */
	public function test_sanitizes_hostile_locale() {
		$request = new WP_REST_Request( 'GET', '/plugins/v1/plugin/info-test-plugin' );
		$request->set_query_params( array( 'locale' => '../../evil' ) );

		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'info-test-plugin', $data['slug'] );
	}

	/**
	 * Requests for unknown plugins are rejected as invalid.
	 */
	public function test_rejects_unknown_plugin() {
		$request  = new WP_REST_Request( 'GET', '/plugins/v1/plugin/not-a-registered-plugin' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}
}
