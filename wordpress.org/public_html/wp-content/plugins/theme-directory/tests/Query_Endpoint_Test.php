<?php
/**
 * Functional tests for the themes/1.x query endpoint.
 *
 * @package theme-directory
 */

/**
 * Functional tests for the themes/1.x query endpoint.
 *
 * @group rest-api
 */
class Query_Endpoint_Test extends Theme_Directory_Endpoint_TestCase {

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

		self::$theme_id = self::create_theme( 'query-test-theme', 'Query Test Theme' );
	}

	/**
	 * Deletes the theme fixture.
	 */
	public static function tearDownAfterClass(): void {
		self::delete_theme( self::$theme_id );

		parent::tearDownAfterClass();
	}

	/**
	 * A direct theme query returns the requested theme.
	 */
	public function test_finds_theme_by_slug() {
		$request = new WP_REST_Request( 'GET', '/themes/1.1/query' );
		$request->set_query_params( array( 'theme' => 'query-test-theme' ) );

		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $data->info['results'] );
		$this->assertSame( 'query-test-theme', $data->themes[0]->slug );
	}

	/**
	 * An unmatched search returns the empty result structure, including
	 * when pagination is passed as numeric strings like existing
	 * consumers do.
	 */
	public function test_unmatched_search_returns_empty_results() {
		$request = new WP_REST_Request( 'GET', '/themes/1.1/query' );
		$request->set_query_params(
			array(
				'search'   => 'no-theme-matches-this-search-term',
				'page'     => '1',
				'per_page' => '5',
			)
		);

		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertObjectHasProperty( 'info', $data );
		$this->assertSame( 0, $data->info['results'] );
	}

	/**
	 * Array input for scalar parameters is rejected with a 400, as the
	 * Themes_API bad_input handling already did.
	 */
	public function test_rejects_array_search() {
		$request = new WP_REST_Request( 'GET', '/themes/1.1/query' );
		$request->set_query_params( array( 'search' => array( 'evil' => 'array' ) ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Non-numeric pagination input is rejected.
	 */
	public function test_rejects_invalid_page() {
		$request = new WP_REST_Request( 'GET', '/themes/1.1/query' );
		$request->set_query_params( array( 'page' => 'not-a-number' ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}
}
