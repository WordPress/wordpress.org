<?php
/**
 * Tests when localized theme queries put translated themes first.
 *
 * Localized sites sort themes with a translation in the site's language ahead of
 * the rest, which reorders author archives away from the chronological order
 * they have on wordpress.org.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Tests for the translated-first sort in `wporg_themes_pre_get_posts()`.
 *
 * @group query
 */
class Localized_Sort_Test extends TestCase {

	/**
	 * Forces a non-English locale for the duration of a test.
	 *
	 * @var Closure
	 */
	protected Closure $locale_filter;

	/**
	 * Switches to a localized request with no translated-first sort registered.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->locale_filter = static function (): string {
			return 'de_DE';
		};
		add_filter( 'locale', $this->locale_filter );

		remove_filter( 'posts_clauses', 'wporg_themes_prioritize_translated_posts_clauses', 11 );
	}

	/**
	 * Restores the locale and drops any sort the test registered.
	 */
	protected function tearDown(): void {
		remove_filter( 'locale', $this->locale_filter );
		remove_filter( 'posts_clauses', 'wporg_themes_prioritize_translated_posts_clauses', 11 );

		parent::tearDown();
	}

	/**
	 * Builds a theme query from the given query vars.
	 *
	 * @param array $vars Query vars, as parsed from the request.
	 * @return WP_Query
	 */
	protected function theme_query( array $vars ): WP_Query {
		$query             = new WP_Query();
		$query->query      = $vars;
		$query->query_vars = $vars;

		return $query;
	}

	/**
	 * Whether the translated-first sort is registered for the next query.
	 *
	 * @return bool
	 */
	protected function sorts_translated_first(): bool {
		return false !== has_filter( 'posts_clauses', 'wporg_themes_prioritize_translated_posts_clauses' );
	}

	/**
	 * Listings still put translated themes first on localized sites.
	 */
	public function test_sorts_translated_first_on_listings(): void {
		wporg_themes_pre_get_posts( $this->theme_query( array( 'browse' => 'popular' ) ) );

		$this->assertTrue( $this->sorts_translated_first() );
	}

	/**
	 * Author archives keep the same order in every locale.
	 */
	public function test_keeps_author_archive_order(): void {
		wporg_themes_pre_get_posts( $this->theme_query( array( 'author_name' => 'automattic' ) ) );

		$this->assertFalse( $this->sorts_translated_first() );
	}

	/**
	 * Author queries by ID keep their order too.
	 */
	public function test_keeps_author_id_query_order(): void {
		wporg_themes_pre_get_posts( $this->theme_query( array( 'author' => 1 ) ) );

		$this->assertFalse( $this->sorts_translated_first() );
	}
}
