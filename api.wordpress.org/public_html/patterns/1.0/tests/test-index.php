<?php

namespace WordPressdotorg\API\Patterns\Tests;
use PHPUnit\Framework\TestCase;
use Requests, Requests_Response;

/**
 * @group patterns
 */
class Test_Patterns extends TestCase {
	/**
	 * Make an API request to the current sandbox.
	 */
	private function send_request( string $query_string ) : Requests_Response {
		global $wporg_sandbox_hostname;

		/*
		 * This has to use HTTP rather than HTTPS, because the request will be made from the sandbox, which is
		 * already behind the load balancer.
		 */
		$url = sprintf(
			'http://%s/patterns/1.0%s',
			$wporg_sandbox_hostname,
			$query_string
		);

		$headers = array(
			'Accept' => 'application/json',
			'Host'   => 'api.wordpress.org',
		);

		/*
		 * Warning: Only make `get()` requests in this suite.
		 *
		 * POST/UPDATE/DELETE requests would change production data, so those would have to be done in a local
		 * environment.
		 */
		return Requests::get( $url, $headers );
	}

	/**
	 * Asserts that an HTTP response is valid and contains a pattern.
	 *
	 * @param Requests_Response $response
	 */
	public function assertResponseHasPattern( $response ) {
		$patterns = json_decode( $response->body );

		$this->assertSame( 200, $response->status_code );
		$this->assertIsString( $patterns[0]->title->rendered );
		$this->assertIsInt( $patterns[0]->meta->wpop_viewport_width );
	}

	/**
	 * Pluck term IDs from a list of patterns.
	 *
	 * @param object[] $patterns
	 *
	 * @return int[]
	 */
	public function get_term_ids( $patterns ) {
		$term_ids = array();

		foreach ( $patterns as $pattern ) {
			$term_ids = array_merge(
				$term_ids,
				array_column( $pattern->_embedded->{'wp:term'}[0], 'id' )
			);
		}

		return array_unique( $term_ids );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_all_patterns() : void {
		$response = $this->send_request( '/' );
		$patterns = json_decode( $response->body );
		$term_ids = $this->get_term_ids( $patterns );

		$this->assertResponseHasPattern( $response );
		$this->assertGreaterThan( 1, count( $term_ids ) );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_category() : void {
		$query_term_id = 2;
		$response      = $this->send_request( '/?pattern-categories=' . $query_term_id );
		$patterns      = json_decode( $response->body );
		$term_ids      = $this->get_term_ids( $patterns );

		$this->assertResponseHasPattern( $response );
		$this->assertSame( array( $query_term_id ), $term_ids );
	}

	/**
	 * @covers ::main()
	 *
	 * @dataProvider data_search_patterns
	 *
	 * @group e2e
	 *
	 * @param string $search_query
	 */
	public function test_search_patterns( $search_term, $match_expected ) : void {
		$response = $this->send_request( '/?search=' . $search_term );
		$patterns = json_decode( $response->body );

		if ( $match_expected ) {
			$this->assertResponseHasPattern( $response );

			$all_patterns_include_query = true;

			foreach ( $patterns as $pattern ) {
				$match_in_title       = stripos( $pattern->title->rendered, $search_term );
				$match_in_description = stripos( $pattern->meta->wpop_description, $search_term );;

				if ( ! $match_in_title && ! $match_in_description ) {
					$all_patterns_include_query = false;
					break;
				}
			}

			$this->assertTrue( $all_patterns_include_query );

		} else {
			$this->assertSame( 200, $response->status_code );
			$this->assertSame( '[]', $response->body );
		}
	}

	public function data_search_patterns() {
		return array(
			'match title' => array(
				'search_term'    => 'side by side',
				'match_expected' => true,
			),

			// todo Enable this once https://github.com/WordPress/pattern-directory/issues/28 is done
//			'match description' => array(
//				'search_term'    => 'bright gradient background',
//				'match_expected' => true,
//			),

			'no matches' => array(
				'search_term'    => 'Supercalifragilisticexpialidocious',
				'match_expected' => false,
			),
		);
	}
}
