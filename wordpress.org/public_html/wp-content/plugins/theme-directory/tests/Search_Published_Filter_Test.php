<?php
/**
 * Tests that Elasticsearch-powered theme searches are restricted to published themes.
 *
 * Guards against the regression where Jetpack Search returns (and counts) themes
 * in non-public statuses, leaving search result pages with fewer cards than the
 * reported total. See https://github.com/WordPress/wporg-theme-directory/issues/69.
 *
 * @package theme-directory
 */

use PHPUnit\Framework\TestCase;

/**
 * @group search
 */
class Search_Published_Filter_Test extends TestCase {

	/**
	 * The `post_status` filter the callback is expected to add.
	 *
	 * @var array
	 */
	const STATUS_FILTER = array(
		'terms' => array(
			'post_status' => array( 'publish' ),
		),
	);

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
	 * The publish restriction is added when the ES query has no existing filter.
	 */
	public function test_adds_filter_when_none_exists() {
		$args = wporg_themes_restrict_search_to_published( array(), $this->repopackage_query() );

		$this->assertSame( self::STATUS_FILTER, $args['filter'] );
	}

	/**
	 * The publish restriction is combined with a single existing filter.
	 */
	public function test_wraps_single_existing_filter() {
		$existing = array( 'terms' => array( 'post_type' => array( 'repopackage' ) ) );

		$args = wporg_themes_restrict_search_to_published(
			array( 'filter' => $existing ),
			$this->repopackage_query()
		);

		$this->assertArrayHasKey( 'and', $args['filter'] );
		$this->assertContains( $existing, $args['filter']['and'] );
		$this->assertContains( self::STATUS_FILTER, $args['filter']['and'] );
	}

	/**
	 * The publish restriction is appended to an existing `and` filter group.
	 */
	public function test_appends_to_existing_and_group() {
		$existing = array( 'terms' => array( 'post_type' => array( 'repopackage' ) ) );

		$args = wporg_themes_restrict_search_to_published(
			array( 'filter' => array( 'and' => array( $existing ) ) ),
			$this->repopackage_query()
		);

		$this->assertCount( 2, $args['filter']['and'] );
		$this->assertContains( $existing, $args['filter']['and'] );
		$this->assertContains( self::STATUS_FILTER, $args['filter']['and'] );
	}

	/**
	 * Non-theme queries are left untouched.
	 */
	public function test_ignores_non_repopackage_queries() {
		$query                          = new WP_Query();
		$query->query_vars['post_type'] = 'post';

		$input = array( 'filter' => array( 'terms' => array( 'post_type' => array( 'post' ) ) ) );

		$this->assertSame( $input, wporg_themes_restrict_search_to_published( $input, $query ) );
	}
}
