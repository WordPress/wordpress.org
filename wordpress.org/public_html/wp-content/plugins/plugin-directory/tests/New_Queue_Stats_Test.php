<?php
/**
 * REST API endpoint tests.
 *
 * @package WordPressdotorg_Plugin_Directory
 */

/**
 * Tests for the new queue statistics REST API endpoint.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class New_Queue_Stats_Test extends WP_UnitTestCase {
	/**
	 * The endpoint returns only the number of plugins awaiting initial review.
	 */
	public function test_get_new_queue_count() {
		$this->factory()->post->create_many(
			2,
			array(
				'post_type'   => 'plugin',
				'post_status' => 'new',
			)
		);
		$this->factory()->post->create_many(
			2,
			array(
				'post_type'   => 'plugin',
				'post_status' => 'pending',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/plugins/v1/stats/new-queue-count' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'queue_count' => 2 ), $response->get_data() );
	}
}
