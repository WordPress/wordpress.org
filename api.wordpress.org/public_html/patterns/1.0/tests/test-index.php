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
		$this->assertIsArray( $patterns );
		$this->assertGreaterThan( 0, count( $patterns ) );
		$this->assertIsString( $patterns[0]->title->rendered );
		$this->assertIsInt( $patterns[0]->meta->wpop_viewport_width );
		$this->assertIsArray( $patterns[0]->category_slugs );
		$this->assertIsArray( $patterns[0]->keyword_slugs );
	}

	/**
	 * Asserts that the results contain the search term.
	 *
	 * @param array $patterns
	 * @param string $search_term
	 */
	public function assertAllPatternsMatchSearchTerm( $patterns, $search_term ) {
		if ( false !== strpos( $search_term, ' ' ) ) {
			$this->markTestIncomplete( "This doesn't support phrase-matching quoted terms yet." );
		}

		foreach ( $patterns as $pattern ) {
			$match_in_title       = stripos( $pattern->title->rendered, $search_term );
			$match_in_description = stripos( $pattern->meta->wpop_description, $search_term );

			if ( false === $match_in_title && false === $match_in_description ) {
				$this->fail( "`$search_term` not found in `{$pattern->title->rendered}` pattern (ID {$pattern->id})" );
			}
		}

		$this->pass();
	}

	/**
	 * PHPUnit provides `fail()`, but no equivalent for success. This makes calling code more obvious and self-documenting.
	 */
	public function pass() {
		$this->assertTrue( true );
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
		$response = send_request( '/patterns/1.0/?per_page=100' );
		$this->assertResponseHasPattern( $response );

		// When all locales and keywords are included, there should be at least 100 patterns.
		$patterns = json_decode( $response->body );
		$this->assertSame( 100, count( $patterns ) );

		/*
		 * The exact number of unique categories will vary based on which cohort of pattens happen to be returned,
		 * but `3` seems like a safe minimum in practice.
		 */
		$term_slugs = $this->get_term_slugs( $patterns );
		$this->assertGreaterThan( 3, count( $term_slugs ) );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_patterns_by_category() : void {
		$button_term_id = 2;

		/*
		 * This can't include a `pattern-keyword` param because of the workaround in
		 * `WordPressdotorg\Pattern_Directory\Pattern_Post_Type\register_rest_fields()`.
		 */
		$response = send_request( '/patterns/1.0/?pattern-categories=' . $button_term_id . '&locale=en_US' );
		$this->assertResponseHasPattern( $response );

		$patterns   = json_decode( $response->body );
		$term_slugs = $this->get_term_slugs( $patterns );
		$this->assertContains( 'buttons', $term_slugs );
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
		$found_locales = array_unique( array_column( $found_locales, 'wpop_locale' ) );

		if ( $expected_locale ) {
			/*
			 * Using `assertContains()` instead of `assertSame()` because `en_US` patterns are often included as
			 * a fallback, if the locale isn't 100% translated.
			 */
			$this->assertContains( $expected_locale, $found_locales );

		} else {
			/*
			 * This could start failing falsely in the future if new patterns are created in a way that results in
			 * the first page of patterns all having the same locale.
			 */
			$this->assertGreaterThan( 1, count( $found_locales ) );
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
	public function test_search_patterns( $search_term, $locale, $match_expected, $expected_post_ids ) : void {
		$response = send_request( "/patterns/1.0/?search=$search_term&pattern-keywords=11&locale=$locale" );

		if ( $match_expected ) {
			if ( empty( $expected_post_ids ) ) {
				$this->fail( 'Test case must provide the expected post IDs if a match is expected.' );
			}

			$this->assertResponseHasPattern( $response );

			$patterns    = json_decode( $response->body );
			$pattern_ids = array_column( $patterns, 'id' );

			$this->assertAllPatternsMatchSearchTerm( $patterns, $search_term );

			foreach ( $expected_post_ids as $id ) {
				$this->assertContains( $id, $pattern_ids );
			}

		} else {
			$this->assertSame( 200, $response->status_code );
			$this->assertSame( '[]', $response->body );
		}
	}

	public function data_search_patterns() {
		return array(
			// Should find posts that have the term in the title, but _not_ in the description.
			'match title only' => array(
				'search_term'       => 'side',
				'locale'            => 'en_US',
				'match_expected'    => true,
				'expected_post_ids' => array( 19 ),
			),

			// Should find posts that have the term in the description, but _not_ in the title.
			'match description only' => array(
				'search_term'       => 'quote',
				'locale'            => 'en_US',
				'match_expected'    => true,
				'expected_post_ids' => array( 185 ),
			),

			"don't match post_content" => array(
				'search_term'       => 'The voyage had begun', // Post ID 29.
				'locale'            => 'en_US',
				'match_expected'    => false,
				'expected_post_ids' => false,
			),

			'no matches' => array(
				'search_term'       => 'Supercalifragilisticexpialidocious',
				'locale'            => 'en_US',
				'match_expected'    => false,
				'expected_post_ids' => false,
			),

			"don't match unlisted posts" => array(
				'search_term'       => 'one filled and one outlined', // Post ID 5.
				'locale'            => 'en_US',
				'match_expected'    => false,
				'expected_post_ids' => false,
			),

			"don't match trashed posts" => array(
				'search_term'       => 'i18n test', // Post ID 866.
				'locale'            => 'es_MX',
				'match_expected'    => false,
				'expected_post_ids' => false,
			),

			// The Core keyword (11) is hardcoded in `test_search_patterns()`, so don't need to specify it here.
			"only match Core posts" => array(
				'search_term'       => 'two buttons', // Post ID 727.
				'locale'            => 'en_US',
				'match_expected'    => false,
				'expected_post_ids' => false,
			),

			"match spanish posts for es_MX request" => array(
				'search_term'       => 'compensación',
				'locale'            => 'es_MX',
				'match_expected'    => true,
				'expected_post_ids' => array( 2127 ),
			),

			"don't match nl_NL post for en_US request" => array(
				'search_term'       => 'verschoven', // Post ID 2226.
				'locale'            => 'en_US',
				'match_expected'    => false,
				'expected_post_ids' => false,
			),

			// Latin was chosen because it's unlikely to ever be translated
			// @link https://translate.wordpress.org/projects/patterns/core/
			"untranslated locales should have en_US posts as a fallback" => array(
				'search_term'       => 'offset',
				'locale'            => 'la',
				'match_expected'    => true,
				'expected_post_ids' => array( 201 ),
			),
		);
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_search_title_match_boosted_above_description_match() : void {
		$search_term = 'image';
		$locale      = 'en_US';

		$response = send_request( "/patterns/1.0/?search=$search_term&pattern-keywords=11&locale=$locale" );
		$this->assertResponseHasPattern( $response );

		$patterns = json_decode( $response->body );

		$this->assertAllPatternsMatchSearchTerm( $patterns, $search_term );

		$actualOrder = array_column( $patterns, 'id' );

		// Intentionally _not_ using `uasort()`, in order to catch different key orders.
		$expectedPatterns = json_decode( json_encode( $patterns ) );

		usort( $expectedPatterns, function( $a, $b ) use ( $search_term ) {
			$adjustment       = 0;
			$found_in_title_a = false === stripos( $search_term, $a->title->rendered );
			$found_in_title_b = false === stripos( $search_term, $b->title->rendered );

			if ( $found_in_title_a && ! $found_in_title_b ) {
				$adjustment = -1;
			} elseif ( ! $found_in_title_a && $found_in_title_b ) {
				$adjustment = 1;
			}

			return $adjustment;
		} );

		$expectedOrder = array_column( $expectedPatterns, 'id' );

		$this->assertSame( $expectedOrder, $actualOrder );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_search_locale_sort() : void {
		$search_term = 'offset';
		$locale      = 'nl_NL';

		$response = send_request( "/patterns/1.0/?search=$search_term&pattern-keywords=11&locale=$locale" );
		$this->assertResponseHasPattern( $response );

		$patterns = json_decode( $response->body );

		$this->assertAllPatternsMatchSearchTerm( $patterns, $search_term );

		$this->markTestIncomplete(); // todo the following code works, but `WordPressdotorg\Pattern_Directory\Search\modify_es_query_args` isn't boosting the primary locale yet

		$actualOrder = array_column( array_column( $patterns, 'meta' ), 'wpop_locale'  );

		// Intentionally _not_ using `uasort()`, in order to catch different key orders.
		$expectedOrder = json_decode( json_encode( $actualOrder ) );

		usort( $expectedOrder, function( $a, $b ) {
			$adjustment = 0;

			if ( $a !== 'en_US' && $b === 'en_US' ) {
				$adjustment = -1;
			} elseif ( $a === 'en_US' && $b !== 'en_US' ) {
				$adjustment = 1;
			}

			return $adjustment;
		} );

		$this->assertSame( $expectedOrder, $actualOrder );
	}

	/**
	 * @covers ::main()
	 *
	 * @group e2e
	 */
	public function test_browse_all_categories() : void {
		$response = send_request( '/patterns/1.0/?categories&pattern-keywords=11&locale=en_US' );
		$this->assertSame( 200, $response->status_code );

		$categories = json_decode( $response->body );
		$this->assertGreaterThan( 0, count( $categories ) );
		$this->assertIsInt( $categories[0]->id );
		$this->assertIsString( $categories[0]->name );
		$this->assertIsString( $categories[0]->slug );
	}
}
