<?php
/**
 * Tests that a child theme is checked against the directory's copy of its parent.
 *
 * Core resolves a child's parent beside the child first, then among the site's
 * installed themes. The import exports the parent's live version from SVN beside
 * the child and blocks that fallback, so Theme Check never sees a local copy.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers WPORG_Themes_Upload::export_parent_theme() and ::load_theme().
 *
 * @group upload
 */
class Parent_Theme_Resolution_Test extends TestCase {

	/**
	 * Slug of the parent that exists in the directory only.
	 *
	 * @var string
	 */
	const DIRECTORY_PARENT = 'fixture-directory-parent';

	/**
	 * Slug of the parent that is installed on the site.
	 *
	 * @var string
	 */
	const INSTALLED_PARENT = 'fixture-installed-parent';

	/**
	 * Every slug a test here can leave a theme post under, cleaned up on teardown.
	 *
	 * @var string[]
	 */
	const FIXTURE_SLUGS = array( 'fixture-child', self::DIRECTORY_PARENT, self::INSTALLED_PARENT );

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
	 * A registered theme root holding the installed copy of the parent.
	 *
	 * @var string
	 */
	protected $installed_root = '';

	/**
	 * The theme directories registered before the test.
	 *
	 * @var array
	 */
	protected $original_theme_directories = array();

	/**
	 * Installs a parent theme on the site and blocks HTTP requests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		global $wp_theme_directories;

		parent::setUp();

		$this->http_callback = static function () {
			return new WP_Error( 'blocked', 'No HTTP during tests.' );
		};
		add_filter( 'pre_http_request', $this->http_callback );

		// A block theme without an index.php, which core looks for among installed themes even when a copy sits beside the child.
		$this->installed_root = untrailingslashit( get_temp_dir() ) . '/wporg-installed-themes-' . uniqid();
		$this->write_block_theme( $this->installed_root . '/' . self::INSTALLED_PARENT, 'installed-copy.txt' );

		$this->original_theme_directories = $wp_theme_directories;
		register_theme_directory( $this->installed_root );
		search_theme_directories( true );

		$this->assertArrayHasKey( self::INSTALLED_PARENT, search_theme_directories(), 'The fixture parent must be installed on the site.' );
	}

	/**
	 * Removes the fixtures, posts, users and hooks the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wp_theme_directories;

		remove_filter( 'pre_http_request', $this->http_callback );
		$this->http_callback = null;

		$wp_theme_directories = $this->original_theme_directories; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the original.
		search_theme_directories( true );
		$this->remove_fixture( $this->installed_root );

		foreach ( $this->uploads as $upload ) {
			remove_filter( 'override_load_textdomain', array( $upload, 'block_upload_textdomain' ), 10 );
			remove_action( 'shutdown', array( $upload, 'remove_files' ), 10 );

			if ( $upload->tmp_dir ) {
				$this->remove_fixture( $upload->tmp_dir );
			}
		}
		$this->uploads = array();

		// The plugin prevents repopackages from being deleted; detach that specific guard while cleaning up.
		remove_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		foreach ( self::FIXTURE_SLUGS as $slug ) {
			$post_ids = get_posts(
				array(
					'name'        => $slug,
					'post_type'   => 'repopackage',
					'post_status' => 'any',
					'fields'      => 'ids',
				)
			);
			foreach ( $post_ids as $post_id ) {
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
	 * Writes a file, creating its directory.
	 *
	 * @param string $path     Absolute path of the file.
	 * @param string $contents File contents.
	 * @return void
	 */
	protected function write_file( string $path, string $contents ): void {
		wp_mkdir_p( dirname( $path ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents( $path, $contents );
	}

	/**
	 * Writes a block theme with no index.php, plus a marker file identifying the copy.
	 *
	 * @param string $dir    Absolute path of the theme directory.
	 * @param string $marker Name of the marker file.
	 * @return void
	 */
	protected function write_block_theme( string $dir, string $marker ): void {
		$this->write_file( $dir . '/style.css', "/*\nTheme Name: " . basename( $dir ) . "\nVersion: 1.0\n*/\n" );
		$this->write_file( $dir . '/templates/index.html', '<!-- wp:paragraph --><p>Index</p><!-- /wp:paragraph -->' );
		$this->write_file( $dir . '/' . $marker, 'marker' );
	}

	/**
	 * Creates a user with no role, which is all the upload asks for.
	 *
	 * @return int The user ID.
	 */
	protected function create_user(): int {
		$name    = 'parent_resolution_' . wp_generate_password( 8, false );
		$user_id = wp_create_user( $name, wp_generate_password(), "{$name}@example.org" );

		$this->assertIsInt( $user_id );

		$this->user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Creates a published theme in the directory with the given version statuses.
	 *
	 * @param string                $slug   Stored `post_name`.
	 * @param array<string, string> $status Version => status.
	 * @return int The post ID.
	 */
	protected function create_theme_post( string $slug, array $status ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'repopackage',
				'post_status' => 'publish',
				'post_title'  => $slug,
				'post_name'   => $slug,
				'post_author' => $this->create_user(),
			)
		);

		update_post_meta( $post_id, '_status', $status );

		return $post_id;
	}

	/**
	 * Prepares an upload of a child theme, with SVN exports served from local fixtures.
	 *
	 * @param string $template The child's `Template` header.
	 * @param string $tags     The child's `Tags` header.
	 * @return WPORG_Themes_Upload
	 */
	protected function prepare_upload( string $template, string $tags = 'blog' ): WPORG_Themes_Upload {
		wp_set_current_user( $this->create_user() );

		$upload = new class() extends WPORG_Themes_Upload {

			/**
			 * SVN URLs exported during the import.
			 *
			 * @var string[]
			 */
			public $exported = array();

			/**
			 * The error the export fails with, or null for it to succeed.
			 *
			 * @var WP_Error|null
			 */
			public $export_error = null;

			/**
			 * Serves the directory's copy of a parent in place of an SVN export.
			 *
			 * @param string $url         The SVN URL to export.
			 * @param string $destination The local directory to export into.
			 * @return true|WP_Error
			 */
			protected function svn_export( $url, $destination ) {
				$this->exported[] = $url;

				if ( $this->export_error ) {
					return $this->export_error;
				}

				wp_mkdir_p( $destination . '/templates' );
				// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
				file_put_contents( $destination . '/style.css', "/*\nTheme Name: Directory Copy\nVersion: 1.1\n*/\n" );
				file_put_contents( $destination . '/templates/index.html', '<!-- wp:paragraph --><p>Index</p><!-- /wp:paragraph -->' );
				file_put_contents( $destination . '/directory-copy.txt', 'marker' );
				// phpcs:enable

				return true;
			}
		};

		// process_upload() resets the properties before every import; import() itself expects that done.
		( new ReflectionMethod( WPORG_Themes_Upload::class, 'reset_properties' ) )->invoke( $upload );
		$upload->create_tmp_dirs( 'fixture-child' );

		$this->uploads[] = $upload;

		$style_css = "/*\nTheme Name: Fixture Child\nDescription: A fixture theme.\nAuthor: Fixture Author\nVersion: 1.0\nTemplate: {$template}\nTags: {$tags}\n*/\n";
		$this->write_file( $upload->theme_dir . '/style.css', $style_css );
		$this->write_file( $upload->theme_dir . '/screenshot.png', '' );

		return $upload;
	}

	/**
	 * Runs the import without SVN commits, Trac or Theme Check, none of which are available here.
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
	 * Basenames of the parent files Theme Check reads for the child.
	 *
	 * @param WPORG_Themes_Upload $upload The imported upload.
	 * @return string[]
	 */
	protected function parent_files( WPORG_Themes_Upload $upload ): array {
		$child_dir = $upload->theme->get_stylesheet_directory() . '/';
		$files     = array();

		foreach ( $upload->theme->get_files( null, -1, true ) as $file ) {
			if ( ! str_starts_with( $file, $child_dir ) ) {
				$files[] = basename( $file );
			}
		}

		return $files;
	}

	/**
	 * A parent in the directory is exported at its live version, beside the child.
	 *
	 * @return void
	 */
	public function test_directory_parent_is_exported_beside_the_child(): void {
		$parent_id = $this->create_theme_post(
			self::DIRECTORY_PARENT,
			array(
				'1.0' => 'old',
				'1.1' => 'live',
				'1.2' => 'new',
			)
		);

		$upload = $this->prepare_upload( self::DIRECTORY_PARENT );
		$result = $this->import( $upload );

		$this->assertTrue( $result );
		$this->assertSame( array( 'https://themes.svn.wordpress.org/' . self::DIRECTORY_PARENT . '/1.1/' ), $upload->exported );
		$this->assertSame( $upload->tmp_dir . '/' . self::DIRECTORY_PARENT, $upload->theme->get_template_directory() );
		$this->assertContains( 'directory-copy.txt', $this->parent_files( $upload ) );
		$this->assertSame( $parent_id, $upload->theme->post_parent );
	}

	/**
	 * A parent that isn't in the directory fails the upload, even when installed on the site.
	 *
	 * @return void
	 */
	public function test_parent_missing_from_directory_is_invalid(): void {
		$upload = $this->prepare_upload( self::INSTALLED_PARENT );
		$result = $this->import( $upload );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'invalid_parent', $result->get_error_codes() );
		$this->assertSame( array(), $upload->exported );
		$this->assertNotContains( 'installed-copy.txt', $this->parent_files( $upload ), 'The installed copy must never stand in for the parent.' );
	}

	/**
	 * When the site has the parent installed, the directory's copy still wins.
	 *
	 * @return void
	 */
	public function test_directory_copy_wins_over_installed_parent(): void {
		$this->create_theme_post( self::INSTALLED_PARENT, array( '1.1' => 'live' ) );

		$upload = $this->prepare_upload( self::INSTALLED_PARENT );
		$result = $this->import( $upload );

		$this->assertTrue( $result );
		$this->assertSame( $upload->tmp_dir . '/' . self::INSTALLED_PARENT, $upload->theme->get_template_directory() );

		$parent_files = $this->parent_files( $upload );
		$this->assertContains( 'directory-copy.txt', $parent_files );
		$this->assertNotContains( 'installed-copy.txt', $parent_files );
	}

	/**
	 * A BuddyPress child may name a parent outside the directory, but still isn't checked against an installed copy.
	 *
	 * @return void
	 */
	public function test_buddypress_child_skips_parent_validation_without_fallback(): void {
		$upload = $this->prepare_upload( self::INSTALLED_PARENT, 'buddypress' );
		$result = $this->import( $upload );

		$this->assertTrue( $result );
		$this->assertNotContains( 'installed-copy.txt', $this->parent_files( $upload ) );
	}

	/**
	 * A failed export stops the import with a message of its own, not SVN's output.
	 *
	 * @return void
	 */
	public function test_failed_parent_export_reports_its_own_message(): void {
		$this->create_theme_post( self::DIRECTORY_PARENT, array( '1.1' => 'live' ) );

		$upload               = $this->prepare_upload( self::DIRECTORY_PARENT );
		$upload->export_error = new WP_Error( 'svn_error', 'A    /tmp/WPORG_THEME_x/<img src=x onerror=alert(1)>.php' );
		$result               = $this->import( $upload );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'parent_export_failed', $result->get_error_code() );
		$this->assertStringContainsString( '<code>' . self::DIRECTORY_PARENT . '</code>', $result->get_error_message() );
		$this->assertStringNotContainsString( '<img', $result->get_error_message() );
	}
}
