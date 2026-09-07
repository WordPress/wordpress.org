<?php
/**
 * Tests that a theme description never reaches the directory as a live shortcode.
 *
 * A description is the one-line `Description:` header of an author's `style.css`,
 * and the theme page renders it through `the_content`. Two writers put it into
 * `post_content`: the upload, which refuses a header carrying shortcode syntax, and
 * `wporg_themes_approve_version()`, which re-reads `style.css` from SVN when a
 * version goes live and encodes the delimiters instead, since a commit reaches it
 * without passing the upload's check.
 *
 * Neither writer edits the shortcode out. Removing one can splice the remaining text
 * into a new shortcode, and `strip_shortcodes()` in particular unwraps an escaped
 * `[[tag]]` into a live `[tag]`, so both payload shapes are pinned here.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Tests for the shortcode handling of both description writers.
 *
 * @group upload
 */
class Theme_Description_Shortcodes_Test extends TestCase {

	/**
	 * A description whose shortcode is written plainly.
	 *
	 * `[caption]` is registered by core at file scope, so it is present in every
	 * process, and it builds its output from an attribute rather than from the
	 * enclosed text.
	 *
	 * @var string
	 */
	const DIRECT = 'Fixture [caption width="1" caption="x"]y[/caption] theme';

	/**
	 * The same shortcode in core's escaped form.
	 *
	 * `strip_shortcodes()` returns this as a live `[caption ...]`, which is why the
	 * directory refuses the header rather than editing it.
	 *
	 * @var string
	 */
	const ESCAPED_TWIN = 'Fixture [[caption width="1" caption="x"]y[/caption]] theme';

	/**
	 * A description that becomes a shortcode only once one is removed from it.
	 *
	 * Deleting the inner `[caption]` splices the remainder into `[gallery ids="1"]`.
	 *
	 * @var string
	 */
	const SPLICE = 'Fixture [gal[caption]lery ids="1"] theme';

	/**
	 * Bracketed prose, which is not a shortcode and must be left alone.
	 *
	 * @var string
	 */
	const PROSE = 'A theme for [developers] and designers';

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
	 * Uploads created during a test, cleaned up again on teardown.
	 *
	 * @var array
	 */
	protected $uploads = array();

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var array
	 */
	protected $post_ids = array();

	/**
	 * The `pre_http_request` callback, kept so it can be removed again.
	 *
	 * @var callable|null
	 */
	protected $http_callback = null;

	/**
	 * Creates the theme root the fixture styles are written into.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// A unique root per test, so WP_Theme's header cache cannot be reused.
		$this->theme_root = untrailingslashit( get_temp_dir() ) . '/wporg-theme-description-' . uniqid();
		wp_mkdir_p( $this->theme_root . '/' . $this->stylesheet );
	}

	/**
	 * Removes the fixtures, posts and hooks the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->http_callback ) {
			remove_filter( 'pre_http_request', $this->http_callback, 10 );
			$this->http_callback = null;
		}

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
	 * Builds the `style.css` contents for a description.
	 *
	 * @param string $description Value of the `Description:` header.
	 * @return string
	 */
	protected function style_css( string $description ): string {
		return "/*\nTheme Name: Fixture Theme\nDescription: {$description}\nVersion: 1.0\n*/\n";
	}

	/**
	 * Runs an upload of a fixture theme carrying the given description.
	 *
	 * @param string $description Value of the `Description:` header.
	 * @return WP_Error The accumulated header errors.
	 */
	protected function import_description( string $description ): WP_Error {
		$upload = new WPORG_Themes_Upload();
		$upload->create_tmp_dirs( 'fixture' );

		$this->uploads[] = $upload;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents( $upload->theme_dir . '/style.css', $this->style_css( $description ) );

		$args = array(
			'create_trac_ticket' => false,
			'commit_to_svn'      => false,
		);

		$import = new ReflectionMethod( WPORG_Themes_Upload::class, 'import' );
		$result = $import->invoke( $upload, $args );

		$this->assertInstanceOf( WP_Error::class, $result );

		return $result;
	}

	/**
	 * Serves the fixture `style.css` to the SVN read in place of a real request.
	 *
	 * `wporg_themes_get_header_data()` streams the file to the path it passes in
	 * `filename`, so the body is written there rather than returned.
	 *
	 * @param string $description Value of the `Description:` header.
	 * @return void
	 */
	protected function serve_style_css( string $description ): void {
		$css = $this->style_css( $description );

		$this->http_callback = function ( $preempt, $args, $url ) use ( $css ) {
			if ( ! str_ends_with( $url, '/style.css' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '',
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
			file_put_contents( $args['filename'], $css );

			return array(
				'response' => array( 'code' => 200 ),
				'filename' => $args['filename'],
				'body'     => '',
			);
		};

		add_filter( 'pre_http_request', $this->http_callback, 10, 3 );
	}

	/**
	 * Creates a published repopackage to stand in for a listed theme.
	 *
	 * @return int The post ID.
	 */
	protected function create_theme_post(): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'repopackage',
				'post_status'  => 'publish',
				'post_title'   => 'Fixture Theme',
				'post_name'    => 'fixture-theme',
				'post_content' => 'The description that is already stored.',
				'post_author'  => 1,
			)
		);

		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * A plainly written shortcode is refused at upload.
	 *
	 * @return void
	 */
	public function test_upload_refuses_a_direct_shortcode(): void {
		$this->assertContains(
			'shortcode_in_description',
			$this->import_description( self::DIRECT )->get_error_codes()
		);
	}

	/**
	 * The escaped twin is refused too, since stripping would activate it.
	 *
	 * @return void
	 */
	public function test_upload_refuses_an_escaped_shortcode_twin(): void {
		$this->assertContains(
			'shortcode_in_description',
			$this->import_description( self::ESCAPED_TWIN )->get_error_codes()
		);
	}

	/**
	 * A description that would become a shortcode once one is removed is refused.
	 *
	 * @return void
	 */
	public function test_upload_refuses_a_spliced_shortcode(): void {
		$this->assertContains(
			'shortcode_in_description',
			$this->import_description( self::SPLICE )->get_error_codes()
		);
	}

	/**
	 * Bracketed prose is not a shortcode, and is not refused.
	 *
	 * @return void
	 */
	public function test_upload_accepts_bracketed_prose(): void {
		$this->assertNotContains(
			'shortcode_in_description',
			$this->import_description( self::PROSE )->get_error_codes()
		);
	}

	/**
	 * A version going live stores the SVN description with its delimiters encoded.
	 *
	 * @return void
	 */
	public function test_live_version_stores_an_inert_description(): void {
		$post_id = $this->create_theme_post();

		$this->serve_style_css( self::DIRECT );
		wporg_themes_approve_version( $post_id, '1.0', 'old' );

		$stored = get_post( $post_id )->post_content;

		$this->assertStringNotContainsString( '[', $stored );
		$this->assertSame( $stored, do_shortcode( $stored ) );

		// The wording survives; only the delimiters the parser reads are encoded.
		$this->assertSame( self::DIRECT, html_entity_decode( $stored ) );
	}

	/**
	 * The escaped twin is stored inert as well, rather than being unwrapped.
	 *
	 * @return void
	 */
	public function test_live_version_stores_an_inert_escaped_twin(): void {
		$post_id = $this->create_theme_post();

		$this->serve_style_css( self::ESCAPED_TWIN );
		wporg_themes_approve_version( $post_id, '1.0', 'old' );

		$stored = get_post( $post_id )->post_content;

		$this->assertStringNotContainsString( '[', $stored );
		$this->assertSame( $stored, do_shortcode( $stored ) );
		$this->assertSame( self::ESCAPED_TWIN, html_entity_decode( $stored ) );
	}

	/**
	 * Encoding a description that carries none of the syntax changes nothing.
	 *
	 * @return void
	 */
	public function test_live_version_leaves_an_ordinary_description_alone(): void {
		$post_id = $this->create_theme_post();

		$this->serve_style_css( 'A tidy little theme' );
		wporg_themes_approve_version( $post_id, '1.0', 'old' );

		$this->assertSame( 'A tidy little theme', get_post( $post_id )->post_content );
	}
}
