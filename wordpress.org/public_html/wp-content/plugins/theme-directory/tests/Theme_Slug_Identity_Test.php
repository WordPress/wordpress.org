<?php
/**
 * Tests that the slug the upload checks a theme under is the slug the directory stores.
 *
 * `WPORG_Themes_Upload` derives the slug from the `Theme Name:` header and hands the
 * same string to `wp_insert_post()` as `post_name`. Core sanitizes `post_name` once
 * more on the way in, so the derivation has to produce a value that second pass keeps.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Tests for `wporg_themes_slug_from_name()` and the upload's use of it.
 *
 * @group upload
 */
class Theme_Slug_Identity_Test extends TestCase {

	/**
	 * Every slug a test here can leave a theme post under, cleaned up on teardown.
	 *
	 * The upload itself creates posts, so cleanup goes by slug rather than by the IDs
	 * the test created.
	 *
	 * @var string[]
	 */
	const FIXTURE_SLUGS = array( 'fixture-sample', 'fixture-sample-2', 'fixture-41-sample', 'black-white', 'figureground', '%e3%83%86%e3%83%bc%e3%83%9e' );

	/**
	 * Uploads created during a test, cleaned up again on teardown.
	 *
	 * @var WPORG_Themes_Upload[]
	 */
	protected $uploads = array();

	/**
	 * IDs of users created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	protected $user_ids = array();

	/**
	 * The `pre_http_request` callback, kept so it can be removed again.
	 *
	 * @var callable|null
	 */
	protected $http_callback = null;

	/**
	 * Keeps the import's follow-up requests (GitHub, GlotPress) from leaving the test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->http_callback = static function () {
			return new WP_Error( 'blocked', 'No HTTP during tests.' );
		};
		add_filter( 'pre_http_request', $this->http_callback );
	}

	/**
	 * Removes the fixtures, posts, users and hooks the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_http_request', $this->http_callback );
		$this->http_callback = null;

		foreach ( $this->uploads as $upload ) {
			remove_filter( 'override_load_textdomain', array( $upload, 'block_upload_textdomain' ), 10 );
			remove_action( 'shutdown', array( $upload, 'remove_files' ), 10 );

			if ( $upload->tmp_dir ) {
				$this->remove_fixture( $upload->tmp_dir );
			}
		}
		$this->uploads = array();

		/*
		 * The plugin prevents repopackages from being deleted; detach that specific
		 * guard while cleaning up the fixture posts.
		 */
		remove_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		foreach ( self::FIXTURE_SLUGS as $slug ) {
			foreach ( $this->theme_posts_named( $slug ) as $post_id ) {
				wp_delete_post( $post_id, true );
			}
		}
		add_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = array();

		wp_set_current_user( 0 );

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
	 * Creates a user with no role, which is all the upload asks for.
	 *
	 * @return int The user ID.
	 */
	protected function create_user(): int {
		$name    = 'slug_identity_' . wp_generate_password( 8, false );
		$user_id = wp_create_user( $name, wp_generate_password(), "{$name}@example.org" );

		$this->assertIsInt( $user_id );

		$this->user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Builds the `style.css` contents, overriding the given headers.
	 *
	 * The default name avoids the words the upload refuses in a name.
	 *
	 * @param array<string, string> $overrides Header lines to override, e.g. `Theme Name`.
	 * @return string
	 */
	protected function style_css( array $overrides ): string {
		$headers = array_merge(
			array(
				'Theme Name'  => 'Fixture Sample',
				'Description' => 'A fixture theme.',
				'Author'      => 'Fixture Author',
				'Theme URI'   => 'https://example.org/theme',
				'Author URI'  => 'https://example.org/author',
				'Version'     => '1.0',
				'Tags'        => 'blog',
			),
			$overrides
		);

		$lines = '';
		foreach ( $headers as $name => $line ) {
			$lines .= "{$name}: {$line}\n";
		}

		return "/*\n{$lines}*/\n";
	}

	/**
	 * Prepares an upload of a fixture theme carrying the given headers, as the given user.
	 *
	 * @param array<string, string> $headers Header lines to override.
	 * @param int                   $user_id The uploading user.
	 * @return WPORG_Themes_Upload
	 */
	protected function prepare_upload( array $headers, int $user_id ): WPORG_Themes_Upload {
		wp_set_current_user( $user_id );

		$upload = new WPORG_Themes_Upload();

		// process_upload() resets the properties before every import; import() itself expects that done.
		( new ReflectionMethod( WPORG_Themes_Upload::class, 'reset_properties' ) )->invoke( $upload );
		$upload->create_tmp_dirs( 'fixture' );

		$this->uploads[] = $upload;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents( $upload->theme_dir . '/style.css', $this->style_css( $headers ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents( $upload->theme_dir . '/screenshot.png', '' );

		return $upload;
	}

	/**
	 * Runs the import without SVN, Trac or Theme Check, none of which are available here.
	 *
	 * @param WPORG_Themes_Upload $upload The prepared upload.
	 * @return true|WP_Error
	 */
	protected function import( WPORG_Themes_Upload $upload ) {
		$import = new ReflectionMethod( WPORG_Themes_Upload::class, 'import' );

		return $import->invoke(
			$upload,
			array(
				'create_trac_ticket' => false,
				'commit_to_svn'      => false,
				'run_themecheck'     => false,
			)
		);
	}

	/**
	 * Creates a published theme with one live version, to stand in for a listed theme.
	 *
	 * @param string $title     Stored `post_title`.
	 * @param string $slug      Stored `post_name`.
	 * @param int    $author_id Owning user.
	 * @return int The post ID.
	 */
	protected function create_theme_post( string $title, string $slug, int $author_id ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'repopackage',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => 'The description that is already stored.',
				'post_author'  => $author_id,
			)
		);

		update_post_meta( $post_id, '_status', array( '1.0' => 'live' ) );

		return $post_id;
	}

	/**
	 * The IDs of every theme post stored under a slug, whatever its status.
	 *
	 * @param string $slug The `post_name` to look for.
	 * @return int[]
	 */
	protected function theme_posts_named( string $slug ): array {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'repopackage' AND post_name = %s ORDER BY ID", $slug )
			)
		);
	}

	/**
	 * Names and the slug each is stored under.
	 *
	 * @return array
	 */
	public static function data_names(): array {
		return array(
			'plain'                          => array( 'Fixture Sample', 'fixture-sample' ),
			'soft hyphen'                    => array( "Fix\u{00AD}ture Sample", 'fixture-sample' ),
			'zero width space'               => array( "Fix\u{200B}ture Sample", 'fixture-sample' ),
			'byte order mark'                => array( "\u{FEFF}Fixture Sample", 'fixture-sample' ),
			'trade mark sign'                => array( "Fixture Sample\u{2122}", 'fixture-sample' ),
			'accents'                        => array( 'Fixturé Sämple', 'fixture-sample' ),
			'ampersand as WP_Theme reads it' => array( 'Black &amp; White', 'black-white' ),
			'slash'                          => array( 'Figure/Ground', 'figureground' ),
			'percent sign'                   => array( 'Fixture %41 Sample', 'fixture-41-sample' ),
			'nothing to keep'                => array( 'テーマ', '' ),
			'underscores only'               => array( '___', '' ),
		);
	}

	/**
	 * The derived slug is what a theme post created with it is stored under.
	 *
	 * @dataProvider data_names
	 *
	 * @param string $name     Value of the `Theme Name:` header.
	 * @param string $expected The slug it is stored under.
	 * @return void
	 */
	public function test_slug_survives_being_stored( string $name, string $expected ): void {
		$slug = wporg_themes_slug_from_name( $name );

		$this->assertSame( $expected, $slug );

		if ( '' === $slug ) {
			return;
		}

		// Stored the way create_or_update_theme_post() stores it: no status, so nothing de-duplicates it either.
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'repopackage',
				'post_title'  => $name,
				'post_name'   => $slug,
				'post_author' => 1,
			)
		);

		$this->assertSame( $slug, get_post( $post_id )->post_name );
	}

	/**
	 * A name that is stored under another author's slug is refused as that author's theme.
	 *
	 * @return void
	 */
	public function test_upload_refuses_a_name_that_stores_as_another_authors_slug(): void {
		$owner    = $this->create_user();
		$uploader = $this->create_user();

		$this->create_theme_post( 'Fixture Sample', 'fixture-sample', $owner );

		$upload = $this->prepare_upload( array( 'Theme Name' => "Fix\u{00AD}ture Sample" ), $uploader );
		$result = $this->import( $upload );

		$this->assertCount( 1, $this->theme_posts_named( 'fixture-sample' ), 'The upload must not add a second post under the slug.' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'cannot_upload_theme', $result->get_error_codes() );
		$this->assertSame( 'fixture-sample', $upload->theme_slug );
	}

	/**
	 * A name that is stored under the author's own slug updates that theme rather than adding one.
	 *
	 * @return void
	 */
	public function test_upload_updates_the_authors_theme_when_the_name_stores_as_its_slug(): void {
		$owner   = $this->create_user();
		$post_id = $this->create_theme_post( 'Fixture Sample', 'fixture-sample', $owner );

		$upload = $this->prepare_upload(
			array(
				'Theme Name' => "Fixture Sample\u{2122}",
				'Version'    => '1.1',
			),
			$owner
		);
		$result = $this->import( $upload );

		$this->assertTrue( $result );
		$this->assertSame( $post_id, $upload->theme_post->ID );
		$this->assertSame( array( $post_id ), $this->theme_posts_named( 'fixture-sample' ) );
	}

	/**
	 * A name with nothing that can be stored is refused with its own message.
	 *
	 * @return void
	 */
	public function test_upload_refuses_a_name_with_nothing_to_keep(): void {
		$upload = $this->prepare_upload( array( 'Theme Name' => 'テーマ' ), $this->create_user() );
		$result = $this->import( $upload );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'unsupported_name', $result->get_error_codes() );
		$this->assertSame( '', $upload->theme_slug );
	}

	/**
	 * A new theme post that core stores under a different slug than the checked one is discarded.
	 *
	 * @return void
	 */
	public function test_a_new_theme_post_stored_under_another_slug_is_discarded(): void {
		$upload = $this->prepare_upload( array(), $this->create_user() );

		// Have core store the slug differently from how it was derived.
		$divert = static function ( $title, $raw_title ) {
			return 'fixture-sample' === $raw_title ? 'fixture-sample-2' : $title;
		};
		add_filter( 'sanitize_title', $divert, 10, 2 );

		try {
			$result = $this->import( $upload );
		} finally {
			remove_filter( 'sanitize_title', $divert, 10 );
		}

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'slug_mismatch', $result->get_error_codes() );
		$this->assertSame( array(), $this->theme_posts_named( 'fixture-sample' ) );
		$this->assertSame( array(), $this->theme_posts_named( 'fixture-sample-2' ) );
	}
}
