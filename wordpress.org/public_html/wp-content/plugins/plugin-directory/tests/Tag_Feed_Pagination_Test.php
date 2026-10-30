<?php
/**
 * Tests pagination of plugin tags whose slug looks like a feed endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Tests;

use PHPUnit\Framework\Attributes\Group;

/**
 * Paginated archives for tags like `feed` stay on their own URL.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Tag_Feed_Pagination_Test extends \WP_UnitTestCase {

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
	 * Page 2 of the `feed` tag isn't redirected to the tag-less `/tags/page/2/`.
	 */
	public function test_second_page_of_feed_tag_is_canonical(): void {
		// The taxonomy was registered without pretty permalinks, so it has no `/tags/` rules yet.
		$this->set_permalink_structure( '/%postname%/' );
		get_taxonomy( 'plugin_tags' )->add_rewrite_rules();
		flush_rewrite_rules();

		$tag_id = self::factory()->term->create(
			array(
				'taxonomy' => 'plugin_tags',
				'name'     => 'feed',
				'slug'     => 'feed',
			)
		);

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

		$url = get_term_link( $tag_id, 'plugin_tags' ) . 'page/2/';
		$this->go_to( $url );

		$this->assertNull( redirect_canonical( $url, false ) );
	}
}
