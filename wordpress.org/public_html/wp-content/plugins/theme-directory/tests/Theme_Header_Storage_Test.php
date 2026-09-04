<?php
/**
 * Tests that the one-line `style.css` headers are stored as text.
 *
 * The name, the description and the author headers are single lines an uploader
 * types into `style.css`, not body content, so nothing in them is meant to run
 * when the directory renders a theme page. `create_or_update_theme_post()` runs
 * them through `strip_shortcodes()` on the way into the post.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Tests for the headers `WPORG_Themes_Upload::create_or_update_theme_post()` stores.
 *
 * @group upload
 */
class Theme_Header_Storage_Test extends TestCase {

	/**
	 * A header value that would run if it were stored verbatim.
	 *
	 * `[caption]` is registered by core, so it is present wherever the directory
	 * renders, and it builds its output from an attribute rather than from the
	 * enclosed text.
	 *
	 * @var string
	 */
	const SHORTCODE_HEADER = 'Fixture [caption width="1" caption="x"]y[/caption] Theme';

	/**
	 * What is left of SHORTCODE_HEADER once the shortcode has been removed.
	 *
	 * @var string
	 */
	const STRIPPED_HEADER = 'Fixture  Theme';

	/**
	 * Absolute path of the temporary theme root holding the fixture.
	 *
	 * @var string
	 */
	protected $theme_root = '';

	/**
	 * Directory name of the fixture theme within the theme root.
	 *
	 * @var string
	 */
	protected $stylesheet = 'fixture-theme';

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var array
	 */
	protected $post_ids = array();

	/**
	 * Writes a fixture theme whose one-line headers all carry the payload.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// A unique root per test, so WP_Theme's header cache cannot be reused.
		$this->theme_root = untrailingslashit( get_temp_dir() ) . '/wporg-theme-storage-' . uniqid();
		wp_mkdir_p( $this->theme_root . '/' . $this->stylesheet );

		$style_css = "/*\n"
			. 'Theme Name: ' . self::SHORTCODE_HEADER . "\n"
			. 'Description: ' . self::SHORTCODE_HEADER . "\n"
			. 'Author: ' . self::SHORTCODE_HEADER . "\n"
			. 'Theme URI: https://example.org/' . self::SHORTCODE_HEADER . "\n"
			. 'Author URI: https://example.org/' . self::SHORTCODE_HEADER . "\n"
			. "Version: 1.0\n*/\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents( $this->theme_root . '/' . $this->stylesheet . '/style.css', $style_css );
	}

	/**
	 * Removes the fixture theme and the posts the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		/*
		 * The plugin prevents repopackages from being deleted; detach that
		 * specific guard while cleaning up the fixture posts.
		 */
		remove_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		add_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );

		$this->post_ids = array();

		$this->remove_fixture( $this->theme_root );

		parent::tearDown();
	}

	/**
	 * Recursively removes a fixture directory.
	 *
	 * @param string $path Absolute path to remove.
	 * @return void
	 */
	protected function remove_fixture( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		foreach ( (array) glob( $path . '/*' ) as $item ) {
			if ( is_dir( $item ) ) {
				$this->remove_fixture( $item );
			} else {
				wp_delete_file( $item );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture in a temporary directory.
		rmdir( $path );
	}

	/**
	 * Runs the fixture through the first-submission branch and returns the post.
	 *
	 * @return WP_Post
	 */
	protected function store_fixture(): WP_Post {
		$upload             = new WPORG_Themes_Upload();
		$upload->theme      = new WP_Theme( $this->stylesheet, $this->theme_root );
		$upload->theme_slug = 'fixture-theme-' . uniqid();
		$upload->author     = get_user_by( 'id', 1 );
		$upload->theme_post = null;

		$upload->create_or_update_theme_post();

		$this->assertInstanceOf( WP_Post::class, $upload->theme_post );
		$this->post_ids[] = $upload->theme_post->ID;

		return $upload->theme_post;
	}

	/**
	 * The stored title keeps the header's text and none of its shortcode.
	 *
	 * @return void
	 */
	public function test_name_header_is_stored_as_text(): void {
		$theme_post = $this->store_fixture();

		$this->assertSame( self::STRIPPED_HEADER, $theme_post->post_title );
		$this->assertSame( $theme_post->post_title, do_shortcode( $theme_post->post_title ) );
	}

	/**
	 * The description gets the same treatment, which it already had.
	 *
	 * @return void
	 */
	public function test_description_header_is_stored_as_text(): void {
		$theme_post = $this->store_fixture();

		$this->assertSame( self::STRIPPED_HEADER, $theme_post->post_content );
	}

	/**
	 * The remaining one-line headers stored as post meta get it too.
	 *
	 * These are written through `update_versioned_meta()`, so each is an array
	 * keyed by the version the upload carried.
	 *
	 * @return void
	 */
	public function test_author_headers_are_stored_as_text(): void {
		$theme_post = $this->store_fixture();

		foreach ( array( '_author', '_theme_url', '_author_url' ) as $key ) {
			$stored = get_post_meta( $theme_post->ID, $key, true );

			$this->assertIsArray( $stored, "No versioned value was stored in {$key}." );
			$this->assertArrayHasKey( '1.0', $stored, "The upload's version is missing from {$key}." );

			$value = $stored['1.0'];

			$this->assertStringNotContainsString( '[caption', $value, "Stored a shortcode in {$key}." );
			$this->assertSame( $value, do_shortcode( $value ), "The value stored in {$key} is parsed as a shortcode." );
		}

		$this->assertSame( self::STRIPPED_HEADER, get_post_meta( $theme_post->ID, '_author', true )['1.0'] );
	}
}
