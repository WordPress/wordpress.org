<?php

namespace WordPressdotorg\API\Patterns\Tests;
use PHPUnit\Framework\TestCase;
use Requests_Response;

/**
 * @group patterns
 */
class Test_Patterns extends TestCase {
	/**
	 * Asserts that an HTTP response is valid and contains a pattern.
	 *
	 * @param Requests_Response $response
	 */
	public function assertResponseHasPattern( $response ) {
		$this->assertSame( 200, $response->status_code );

		$patterns = json_decode( $response->body );
		$this->assertGreaterThan( 0, count( $patterns ) );
		$this->assertIsString( $patterns[0]->title->rendered );
		$this->assertIsInt( $patterns[0]->meta->wpop_viewport_width );
		$this->assertIsArray( $patterns[0]->category_slugs );
		$this->assertIsArray( $patterns[0]->keyword_slugs );
	}

	/**
	 * Pluck term IDs from a list of patterns.
	 *
	 * @param object[] $patterns
	 *
	 * @return int[]
	 */
	public function get_term_slugs( $patterns ) {
		$term_slugs = array();

		foreach ( $patterns as $pattern ) {
			$term_slugs = array_merge(
				$term_slugs,
				$pattern->category_slugs
			);
		}

		return array_unique( $term_slugs );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_all_patterns() : void {
		$response = send_request( '/patterns/1.0/' );
		$this->assertResponseHasPattern( $response );

		$patterns   = json_decode( $response->body );
		$term_slugs = $this->get_term_slugs( $patterns );
		$this->assertGreaterThan( 1, count( $term_slugs ) );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_patterns_by_category() : void {
		$button_term_id = 2;
		$response       = send_request( '/patterns/1.0/?pattern-categories=' . $button_term_id );
		$this->assertResponseHasPattern( $response );

		$patterns   = json_decode( $response->body );
		$term_slugs = $this->get_term_slugs( $patterns );
		$this->assertSame( array( 'buttons' ), $term_slugs );
	}

	/**
	 * @covers ::main()
	 *
	 * @dataProvider data_results_limited_to_requested_locale
	 *
	 * @group e2e
	 *
	 * @param string $locale
	 */
	public function test_results_limited_to_requested_locale( $expected_locale ) : void {
		/*
		 * Use Core patterns since they should always have translated versions.
		 *
		 * Fetch 100 to reduce chances of a false-positive/negative due to way results are paginated.
		 * e.g., the 'none' case may return a mix of locales, but the first page of results may only
		 * have `en_US` ones.
		 */
		$query_args = '/patterns/1.0/?pattern-keywords=11&per_page=100';

		if ( $expected_locale ) {
			$query_args .= "&locale=$expected_locale";
		}

		$response = send_request( $query_args );
		$this->assertResponseHasPattern( $response );

		$patterns      = json_decode( $response->body );
		$found_locales = array_column( $patterns, 'meta' );
		$found_locales = array_column( $found_locales, 'wpop_locale' );

		if ( $expected_locale ) {
			$this->assertSame( array( $expected_locale ), array_unique( $found_locales ) );
		} else {
			$this->assertGreaterThan( 1, count( array_unique( $found_locales ) ) );
		}
	}

	public function data_results_limited_to_requested_locale() {
		return array(
			'none' => array(
				'locale' => '',
			),

			'american english' => array(
				'locale' => 'en_US',
			),

			/*
			 * This is expected to fail until the translated patterns are added to the database.
			 *
			 * @link https://github.com/WordPress/pattern-directory/issues/223
			 * @link https://github.com/WordPress/pattern-directory/issues/224
			 */
			'mexican spanish' => array(
				'locale' => 'es_MX',
			),
		);
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
		$response = send_request( '/patterns/1.0/?search=' . $search_term );
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

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_all_categories() : void {
		$response = send_request( '/patterns/1.0/?categories' );
		$this->assertSame( 200, $response->status_code );

		$categories = json_decode( $response->body );
		$this->assertGreaterThan( 0, count( $categories ) );
		$this->assertIsInt( $categories[0]->id );
		$this->assertIsString( $categories[0]->name );
		$this->assertIsString( $categories[0]->slug );
	}
}
