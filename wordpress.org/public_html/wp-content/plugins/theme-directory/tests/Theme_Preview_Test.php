<?php
/**
 * Functional tests for the themes/v1 preview and review blueprint endpoints.
 *
 * @package theme-directory
 */

/**
 * Functional tests for the themes/v1 preview and review blueprint endpoints.
 *
 * @group rest-api
 */
class Theme_Preview_Test extends Theme_Directory_Endpoint_TestCase {

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

		self::$theme_id = self::create_theme( 'preview-test-theme', 'Preview Test Theme' );
	}

	/**
	 * Deletes the theme fixture.
	 */
	public static function tearDownAfterClass(): void {
		self::delete_theme( self::$theme_id );

		parent::tearDownAfterClass();
	}

	/**
	 * Previewing an unknown theme returns an error.
	 */
	public function test_preview_rejects_unknown_theme() {
		$request  = new WP_REST_Request( 'GET', '/themes/v1/preview-blueprint/not-a-registered-theme' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'error', $response->get_data()['code'] );
	}

	/**
	 * Previewing a theme returns a Playground blueprint.
	 */
	public function test_preview_returns_blueprint() {
		$request  = new WP_REST_Request( 'GET', '/themes/v1/preview-blueprint/preview-test-theme' );
		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'steps', $data );
		$this->assertNotEmpty( $data['steps'] );
	}

	/**
	 * Storing a blueprint requires being the theme author.
	 */
	public function test_set_blueprint_requires_permission() {
		$request = new WP_REST_Request( 'POST', '/themes/v1/preview-blueprint/preview-test-theme' );
		$request->set_param( 'blueprint', wp_json_encode( array( 'steps' => array() ) ) );

		$response = self::server()->dispatch( $request );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}
}
