<?php
/**
 * Functional tests for the themes/1.x tags endpoint.
 *
 * @package theme-directory
 */

/**
 * Functional tests for the themes/1.x tags endpoint.
 *
 * @group rest-api
 */
class Tags_Endpoint_Test extends Theme_Directory_Endpoint_TestCase {

	/**
	 * Term IDs of the tag fixtures.
	 *
	 * @var int[]
	 */
	protected static $tag_ids = array();

	/**
	 * Creates the tag fixtures.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$tag_ids = self::create_tags( array( 'tags-test-one', 'tags-test-two', 'tags-test-three' ) );
	}

	/**
	 * Deletes the tag fixtures.
	 */
	public static function tearDownAfterClass(): void {
		foreach ( self::$tag_ids as $term_id ) {
			wp_delete_term( $term_id, 'post_tag' );
		}

		parent::tearDownAfterClass();
	}

	/**
	 * The endpoint lists the theme tags with their name, slug, and count.
	 */
	public function test_lists_tags() {
		$request  = new WP_REST_Request( 'GET', '/themes/1.1/tags' );
		$response = self::server()->dispatch( $request );
		$data     = (array) $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'tags-test-one', $data );
		$this->assertSame( 'tags-test-one', $data['tags-test-one']['slug'] );
		$this->assertArrayHasKey( 'name', $data['tags-test-one'] );
		$this->assertArrayHasKey( 'count', $data['tags-test-one'] );
	}

	/**
	 * The number parameter limits the result, including when passed as a
	 * numeric string like existing consumers do.
	 */
	public function test_limits_number_of_tags() {
		$request = new WP_REST_Request( 'GET', '/themes/1.1/tags' );
		$request->set_query_params( array( 'number' => '2' ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, (array) $response->get_data() );
	}

	/**
	 * A non-numeric number parameter is rejected.
	 */
	public function test_rejects_invalid_number() {
		$request = new WP_REST_Request( 'GET', '/themes/1.1/tags' );
		$request->set_query_params( array( 'number' => 'not-a-number' ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}
}
