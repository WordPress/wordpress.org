<?php
/**
 * Functional tests for the themes/v1/stats endpoints.
 *
 * @package theme-directory
 */

/**
 * Functional tests for the themes/v1/stats endpoints.
 *
 * @group rest-api
 */
class Theme_Review_Stats_Test extends Theme_Directory_Endpoint_TestCase {

	/**
	 * The uploaded themes report requires reviewer permissions.
	 */
	public function test_uploaded_themes_require_permission() {
		$request  = new WP_REST_Request( 'GET', '/themes/v1/stats' );
		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * The public stats endpoints return monthly data rows.
	 *
	 * @dataProvider data_public_stats_routes
	 *
	 * @param string $route The stats route.
	 */
	public function test_returns_stats( $route ) {
		$request = new WP_REST_Request( 'GET', $route );
		$request->set_query_params( array( 'startDate' => '2024-01-01' ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * The public stats endpoints reject non-scalar startDate input.
	 *
	 * @dataProvider data_public_stats_routes
	 *
	 * @param string $route The stats route.
	 */
	public function test_rejects_array_start_date( $route ) {
		$request = new WP_REST_Request( 'GET', $route );
		$request->set_query_params( array( 'startDate' => array( 'evil' => 'array' ) ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Data provider of the public stats routes.
	 *
	 * @return array[]
	 */
	public static function data_public_stats_routes() {
		return array(
			'byThemeType'  => array( '/themes/v1/stats/byThemeType' ),
			'bySegment'    => array( '/themes/v1/stats/bySegment' ),
			'byAuthorType' => array( '/themes/v1/stats/byAuthorType' ),
			'reviewDays'   => array( '/themes/v1/stats/reviewDays' ),
		);
	}
}
