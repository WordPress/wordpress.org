<?php
/**
 * Tests that Elasticsearch-powered theme searches are restricted to published themes.
 *
 * Guards against the regression where Jetpack Search returns (and counts) themes
 * in non-public statuses, leaving search result pages with fewer cards than the
 * reported total.
 *
 * @package theme-directory
 */

use PHPUnit\Framework\TestCase;

/**
 * @group search
 */
class Search_Published_Filter_Test extends TestCase {

	/**
	 * Builds a repopackage search WP_Query stub.
	 *
	 * @return WP_Query
	 */
	protected function repopackage_query() {
		$query                          = new WP_Query();
		$query->query_vars['post_type'] = 'repopackage';
		$query->query_vars['s']         = 'test';

		return $query;
	}

	/**
	 * Asserts the ES filter tree constrains `post_status` to `publish`.
	 *
	 * Accepts either a bare `terms` filter or one nested in an `and` group, so
	 * the assertion checks the behaviour (results are limited to published
	 * themes) rather than the exact shape the merge logic happens to produce.
	 *
	 * @param array $filter The `filter` value from the ES query args.
	 */
	protected function assertConstrainsToPublish( $filter ) {
		$clauses = isset( $filter['and'] ) ? $filter['and'] : array( $filter );

		foreach ( $clauses as $clause ) {
			if ( array( 'publish' ) === ( $clause['terms']['post_status'] ?? null ) ) {
				return;
			}
		}

		$this->fail( 'ES query is not constrained to the `publish` post status.' );
	}

	/**
	 * A search with no pre-existing ES filter is still constrained to publish.
	 */
	public function test_constrains_search_with_no_existing_filter() {
		$args = wporg_themes_restrict_search_to_published( array(), $this->repopackage_query() );

		$this->assertConstrainsToPublish( $args['filter'] );
	}

	/**
	 * The publish constraint is AND-combined with the query's existing filters,
	 * which must survive intact — dropping them would broaden the results.
	 */
	public function test_preserves_existing_filters() {
		$existing = array( 'terms' => array( 'taxonomy.theme_tags.slug' => array( 'blog' ) ) );

		$args = wporg_themes_restrict_search_to_published(
			array( 'filter' => $existing ),
			$this->repopackage_query()
		);

		$this->assertConstrainsToPublish( $args['filter'] );
		$this->assertContains( $existing, $args['filter']['and'], 'Existing ES filters were dropped.' );
	}

	/**
	 * Non-theme searches sharing the `jetpack_search_es_query_args` hook are
	 * left untouched.
	 */
	public function test_ignores_non_repopackage_queries() {
		$query                          = new WP_Query();
		$query->query_vars['post_type'] = 'post';

		$input = array( 'filter' => array( 'terms' => array( 'post_type' => array( 'post' ) ) ) );

		$this->assertSame( $input, wporg_themes_restrict_search_to_published( $input, $query ) );
	}
}
