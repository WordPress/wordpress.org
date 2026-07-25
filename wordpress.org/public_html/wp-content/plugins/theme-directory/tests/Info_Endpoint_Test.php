<?php
/**
 * Functional tests for the themes/1.x info endpoint.
 *
 * @package theme-directory
 */

/**
 * Functional tests for the themes/1.x info endpoint.
 *
 * @group rest-api
 */
class Info_Endpoint_Test extends Theme_Directory_Endpoint_TestCase {

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

		self::$theme_id = self::create_theme( 'info-test-theme', 'Info Test Theme' );
	}

	/**
	 * Deletes the theme fixture.
	 */
	public static function tearDownAfterClass(): void {
		self::delete_theme( self::$theme_id );

		parent::tearDownAfterClass();
	}

	/**
	 * The endpoint returns the theme information.
	 */
	public function test_returns_theme_information() {
		$request  = new WP_REST_Request( 'GET', '/themes/1.1/info/info-test-theme' );
		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'info-test-theme', $data->slug );
		$this->assertSame( 'Info Test Theme', $data->name );
	}

	/**
	 * Unknown themes return a 404.
	 */
	public function test_returns_404_for_unknown_theme() {
		$request  = new WP_REST_Request( 'GET', '/themes/1.1/info/not-a-registered-theme' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Array input for the scalar slug parameter is rejected.
	 */
	public function test_rejects_array_slug() {
		$request = new WP_REST_Request( 'GET', '/themes/1.1/info' );
		$request->set_query_params( array( 'slug' => array( 'evil' => 'array' ) ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}
}
