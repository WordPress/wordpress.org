<?php
/**
 * Tests for the GitHub edit link in WPOrg_Cli\Handbook.
 *
 * @package wporg-cli
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPOrg_Cli\Handbook;

/**
 * Covers where a handbook page's edit link points.
 */
class WPOrg_Cli_Handbook_Test extends TestCase {

	/**
	 * Post IDs created by a test, deleted afterwards.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * Removes the fixtures a test set up.
	 */
	public function tearDown(): void {
		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->posts = array();

		parent::tearDown();
	}

	/**
	 * Creates a published handbook post with the given Markdown source.
	 *
	 * @param string $markdown_source Source to store.
	 * @return int Post ID.
	 */
	private function create_handbook_post( string $markdown_source ): int {
		$post_id = (int) wp_insert_post(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
				'post_title'  => 'Handbook page',
			)
		);

		$this->posts[] = $post_id;

		update_post_meta( $post_id, 'wporg_cli_markdown_source', $markdown_source );

		return $post_id;
	}

	/**
	 * Blob URLs the importer accepts, whatever case the host was entered in.
	 *
	 * @return array[]
	 */
	public static function get_blob_sources(): array {
		return array(
			'lower case host' => array( 'https://github.com/wp-cli/handbook/blob/main/README.md' ),
			'mixed case host' => array( 'https://GitHub.com/wp-cli/handbook/blob/main/README.md' ),
			'upper case host' => array( 'https://GITHUB.COM/wp-cli/handbook/blob/main/README.md' ),
		);
	}

	/**
	 * A blob URL is rewritten to GitHub's editor, whatever case the host is in.
	 *
	 * The importer accepts the host case insensitively, so a source it takes but
	 * this does not would send readers to GitHub's file view instead.
	 *
	 * @param string $markdown_source Stored source.
	 */
	#[DataProvider( 'get_blob_sources' )]
	public function test_edit_link_points_at_the_github_editor( string $markdown_source ): void {
		$post_id = $this->create_handbook_post( $markdown_source );

		$link = Handbook::redirect_edit_link_to_github( 'https://example.org/wp-admin/post.php', $post_id, 'raw' );

		$this->assertStringContainsString( '/edit/main/README.md', $link );
		$this->assertStringNotContainsString( '/blob/main/', $link );
	}

	/**
	 * A source on another host is handed back untouched.
	 */
	public function test_edit_link_leaves_a_source_on_another_host_alone(): void {
		$markdown_source = 'https://raw.githubusercontent.com/wp-cli/handbook/main/README.md';
		$post_id         = $this->create_handbook_post( $markdown_source );

		$link = Handbook::redirect_edit_link_to_github( 'https://example.org/wp-admin/post.php', $post_id, 'raw' );

		$this->assertSame( $markdown_source, $link );
	}

	/**
	 * A post without a source keeps WordPress's own edit link.
	 */
	public function test_edit_link_falls_back_to_the_original_without_a_source(): void {
		$post_id = $this->create_handbook_post( '' );
		$link    = 'https://example.org/wp-admin/post.php';

		$this->assertSame( $link, Handbook::redirect_edit_link_to_github( $link, $post_id, 'raw' ) );
	}
}
