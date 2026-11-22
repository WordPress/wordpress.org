<?php
/**
 * Tests pagination of plugin tags whose slug looks like a feed endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Paginated archives for tags like `feed` stay on their own URL.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Tag_Feed_Pagination_Test extends \WP_UnitTestCase {

	/**
	 * The rewrite state before the test switched to pretty permalinks.
	 *
	 * @var \WP_Rewrite
	 */
	private \WP_Rewrite $original_rewrite;

	/**
	 * Restore the PHPUnit 9 API that Core's expectDeprecated() relies on.
	 *
	 * Core's test framework predates PHPUnit 10 and falls back to other
	 * PHPUnit 9 APIs when this method is missing. None of these tests use
	 * annotation-based expectations, so an empty set is equivalent.
	 *
	 * @return array[]
	 */
	public function getAnnotations(): array {
		return array(
			'class'  => array(),
			'method' => array(),
		);
	}

	/**
	 * Switches to pretty permalinks with `/tags/` rewrite rules.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->original_rewrite = clone $GLOBALS['wp_rewrite'];

		// The taxonomy was registered without pretty permalinks, so it has no `/tags/` rules yet.
		$this->set_permalink_structure( '/%postname%/' );
		get_taxonomy( 'plugin_tags' )->add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Restores the rewrite state, which Core's test case only resets for Core tests.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rewrite'] = $this->original_rewrite; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the state saved in set_up().

		parent::tear_down();
	}

	/**
	 * Slugs that Canonical strips from paginated URLs as feed endpoints, and the request scheme.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function feed_like_slug_provider(): array {
		return array(
			'feed'            => array( 'feed', 'http' ),
			'rss'             => array( 'rss', 'http' ),
			'rss2'            => array( 'rss2', 'http' ),
			'rdf'             => array( 'rdf', 'http' ),
			'atom'            => array( 'atom', 'http' ),
			'feed over https' => array( 'feed', 'https' ),
		);
	}

	/**
	 * Page 2 of a feed-like tag isn't redirected to the tag-less `/tags/page/2/`.
	 *
	 * @param string $slug   The tag slug.
	 * @param string $scheme The request scheme.
	 */
	#[DataProvider( 'feed_like_slug_provider' )]
	public function test_second_page_of_feed_like_tag_is_canonical( string $slug, string $scheme ): void {
		$tag_id = $this->create_tag( $slug );

		// One more plugin than fits on a page, so page 2 exists.
		$plugin_ids = self::factory()->post->create_many(
			(int) get_option( 'posts_per_page' ) + 1,
			array(
				'post_type'         => 'plugin',
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
				// Tag archives sort by installs, so plugins without the count aren't listed.
				'meta_input'        => array( '_active_installs' => 100 ),
			)
		);
		foreach ( $plugin_ids as $plugin_id ) {
			wp_set_object_terms( $plugin_id, $tag_id, 'plugin_tags' );
		}

		$url = set_url_scheme( get_term_link( $tag_id, 'plugin_tags' ) . 'page/2/', $scheme );
		$this->go_to( $url );

		$this->assertNull( redirect_canonical( $url, false ) );
	}

	/**
	 * A feed-like tag's query-string URL still redirects to its permalink.
	 */
	public function test_feed_like_tag_keeps_canonical_redirect_to_permalink(): void {
		$tag_id = $this->create_tag( 'feed' );

		$url = home_url( '/?plugin_tags=feed' );
		$this->go_to( $url );

		$this->assertSame( get_term_link( $tag_id, 'plugin_tags' ), redirect_canonical( $url, false ) );
	}

	/**
	 * Creates a plugin tag.
	 *
	 * @param string $slug The tag name and slug.
	 * @return int The tag ID.
	 */
	private function create_tag( string $slug ): int {
		return self::factory()->term->create(
			array(
				'taxonomy' => 'plugin_tags',
				'name'     => $slug,
				'slug'     => $slug,
			)
		);
	}
}
