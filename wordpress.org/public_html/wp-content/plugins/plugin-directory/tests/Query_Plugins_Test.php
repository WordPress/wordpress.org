<?php
/**
 * Functional tests for the plugins/v1/query-plugins endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * Functional tests for the plugins/v1/query-plugins endpoint.
 *
 * @group rest-api
 */
class Query_Plugins_Test extends Plugin_Directory_Endpoint_TestCase {

	/**
	 * ID of the first published plugin fixture.
	 *
	 * @var int
	 */
	protected static $alpha_id;

	/**
	 * ID of the second published plugin fixture.
	 *
	 * @var int
	 */
	protected static $beta_id;

	/**
	 * Creates two published plugin fixtures.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$alpha_id = self::create_plugin( 'query-test-plugin-alpha', 'Query Test Plugin Alpha' );
		self::$beta_id  = self::create_plugin( 'query-test-plugin-beta', 'Query Test Plugin Beta' );
	}

	/**
	 * Deletes the plugin fixtures.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$alpha_id, true );
		wp_delete_post( self::$beta_id, true );

		parent::tearDownAfterClass();
	}

	/**
	 * Querying returns the published plugins.
	 */
	public function test_returns_published_plugins() {
		$request = new WP_REST_Request( 'GET', '/plugins/v1/query-plugins' );
		$request->set_param( 'paged', 1 );

		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThanOrEqual( 2, $data['info']['results'] );
		$this->assertContains( 'query-test-plugin-alpha', $data['plugins'] );
		$this->assertContains( 'query-test-plugin-beta', $data['plugins'] );
	}

	/**
	 * The slug: search bypass returns exactly the requested plugin.
	 */
	public function test_finds_exact_plugin_by_slug() {
		$request = new WP_REST_Request( 'GET', '/plugins/v1/query-plugins' );
		$request->set_query_params( array( 's' => 'slug:query-test-plugin-alpha' ) );

		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'query-test-plugin-alpha' ), $data['plugins'] );
		$this->assertSame( 1, $data['info']['results'] );
	}

	/**
	 * Results are paginated according to posts_per_page, including when
	 * passed as a numeric string like existing consumers do.
	 */
	public function test_paginates_results() {
		$request = new WP_REST_Request( 'GET', '/plugins/v1/query-plugins' );
		$request->set_query_params(
			array(
				'paged'          => '1',
				'posts_per_page' => '1',
			)
		);

		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data['plugins'] );
		$this->assertGreaterThanOrEqual( 2, $data['info']['pages'] );
	}

	/**
	 * A query without any recognised parameters returns the empty response.
	 */
	public function test_empty_query_returns_no_results() {
		$request = new WP_REST_Request( 'GET', '/plugins/v1/query-plugins' );

		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 0, $data['info']['results'] );
		$this->assertSame( array(), $data['plugins'] );
	}

	/**
	 * Malformed pagination input is coerced rather than rejected, as
	 * api.wordpress.org proxies client input into this route verbatim.
	 */
	public function test_tolerates_invalid_pagination() {
		$request = new WP_REST_Request( 'GET', '/plugins/v1/query-plugins' );
		$request->set_query_params( array(
			'paged'          => 'not-a-number',
			'posts_per_page' => '',
		) );

		$response = self::server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( 'query-test-plugin-alpha', $data['plugins'] );
	}

	/**
	 * Array input for the scalar search parameter is sanitized away
	 * instead of being passed into WP_Query.
	 */
	public function test_tolerates_array_search() {
		$request = new WP_REST_Request( 'GET', '/plugins/v1/query-plugins' );
		$request->set_query_params( array( 's' => array( 'evil' => 'array' ) ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}
}
