<?php
/**
 * Tests the shortcode handling of the one-line `style.css` headers other than the description.
 *
 * `Description:` is covered by `Theme_Description_Shortcodes_Test`. The name and the
 * author headers reach the directory through the same two writers and are stored the
 * same way: the upload refuses a header carrying shortcode syntax, and
 * `wporg_themes_approve_version()` — which re-reads `style.css` from SVN when a version
 * goes live, without passing the upload's check — encodes the delimiters of the one
 * header it writes, the name.
 *
 * Neither writer edits the shortcode out, for the reasons pinned in the description
 * test: removing one can splice the remaining text into another, and `strip_shortcodes()`
 * unwraps an escaped `[[tag]]` into a live `[tag]`.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Tests for the `Name`, `Author`, `Theme URI` and `Author URI` headers.
 *
 * @group upload
 */
class Theme_Header_Storage_Test extends TestCase {

	/**
	 * A header value whose shortcode is written plainly.
	 *
	 * `[caption]` is registered by core at file scope, so it is present in every
	 * process, and it builds its output from an attribute rather than from the
	 * enclosed text. The attributes are unquoted because `WP_Theme` runs the two URI
	 * headers through `esc_url_raw()`, which drops quotes.
	 *
	 * @var string
	 */
	const DIRECT = '[caption width=1 caption=x]y[/caption]';

	/**
	 * The same shortcode in core's escaped form.
	 *
	 * @var string
	 */
	const ESCAPED_TWIN = '[[caption width=1 caption=x]y[/caption]]';

	/**
	 * A value that becomes a shortcode only once one is removed from it.
	 *
	 * Deleting the inner `[caption]` splices the remainder into `[gallery ids=1]`.
	 *
	 * @var string
	 */
	const SPLICE = '[gal[caption]lery ids=1]';

	/**
	 * Bracketed prose, which is not a shortcode and must be left alone.
	 *
	 * @var string
	 */
	const PROSE = '[developers] and designers';

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
	 * Builds the `style.css` contents, overriding the given headers.
	 *
	 * @param array<string, string> $overrides Header lines to override, e.g. `Theme Name`.
	 * @return string
	 */
	protected function style_css( array $overrides ): string {
		$headers = array_merge(
			array(
				'Theme Name'  => 'Fixture Theme',
				'Description' => 'A fixture theme.',
				'Author'      => 'Fixture Author',
				'Theme URI'   => 'https://example.org/theme',
				'Author URI'  => 'https://example.org/author',
				'Version'     => '1.0',
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
	 * Runs an upload of a fixture theme carrying the given header.
	 *
	 * @param string $header Header line to set.
	 * @param string $value  Value for that header.
	 * @return WP_Error The accumulated header errors.
	 */
	protected function import_header( string $header, string $value ): WP_Error {
		$upload = new WPORG_Themes_Upload();
		$upload->create_tmp_dirs( 'fixture' );

		$this->uploads[] = $upload;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents( $upload->theme_dir . '/style.css', $this->style_css( array( $header => $value ) ) );

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
	 * @param string $name Value of the `Theme Name:` header.
	 * @return void
	 */
	protected function serve_style_css( string $name ): void {
		$css = $this->style_css( array( 'Theme Name' => $name ) );

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
	 * The slug `wporg_themes_approve_version()` derives from a name.
	 *
	 * A renamed theme only keeps the new name if it still sanitizes to the stored
	 * `post_name`, so a test that wants that branch has to store this.
	 *
	 * @param string $name Value of the `Theme Name:` header.
	 * @return string
	 */
	protected function slug_for( string $name ): string {
		$slugified = remove_accents( $name );
		$slugified = preg_replace( '/%[a-f0-9]{2}/i', '', $slugified );

		return sanitize_title_with_dashes( $slugified );
	}

	/**
	 * Creates a published repopackage to stand in for a listed theme.
	 *
	 * @param string $title Stored `post_title`.
	 * @param string $slug  Stored `post_name`.
	 * @return int The post ID.
	 */
	protected function create_theme_post( string $title = 'Fixture Theme', string $slug = 'fixture-theme' ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'repopackage',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => 'The description that is already stored.',
				'post_author'  => 1,
			)
		);

		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * The headers the upload refuses besides the description, with the code each is refused under.
	 *
	 * @return array
	 */
	public static function data_stored_headers(): array {
		return array(
			'name'   => array( 'Theme Name', 'shortcode_in_name' ),
			'author' => array( 'Author', 'shortcode_in_author' ),
		);
	}

	/**
	 * The payload shapes a single removal pass would leave a shortcode in.
	 *
	 * @return array
	 */
	public static function data_payloads(): array {
		return array(
			'direct'       => array( self::DIRECT ),
			'escaped twin' => array( self::ESCAPED_TWIN ),
			'splice'       => array( self::SPLICE ),
		);
	}

	/**
	 * Every header × every payload, for the refusal test.
	 *
	 * @return array
	 */
	public static function data_headers_and_payloads(): array {
		$cases = array();

		foreach ( self::data_stored_headers() as $header_label => $header ) {
			foreach ( self::data_payloads() as $payload_label => $payload ) {
				$cases[ "{$header_label}: {$payload_label}" ] = array( $header[0], $header[1], $payload[0] );
			}
		}

		return $cases;
	}

	/**
	 * A header carrying shortcode syntax is refused at upload.
	 *
	 * @dataProvider data_headers_and_payloads
	 *
	 * @param string $header  Header line to set.
	 * @param string $code    Error code the header is refused under.
	 * @param string $payload Value for that header.
	 * @return void
	 */
	public function test_upload_refuses_a_shortcode_in_a_stored_header( string $header, string $code, string $payload ): void {
		$this->assertContains(
			$code,
			$this->import_header( $header, "Fixture {$payload} Theme" )->get_error_codes(),
			"A shortcode in the {$header} header must be refused."
		);
	}

	/**
	 * Bracketed prose is not a shortcode, and is not refused.
	 *
	 * @dataProvider data_stored_headers
	 *
	 * @param string $header Header line to set.
	 * @param string $code   Error code the header would be refused under.
	 * @return void
	 */
	public function test_upload_accepts_bracketed_prose( string $header, string $code ): void {
		$this->assertNotContains(
			$code,
			$this->import_header( $header, 'A theme for ' . self::PROSE )->get_error_codes(),
			"Bracketed prose in the {$header} header must not be refused."
		);
	}

	/**
	 * The URI headers cannot carry the syntax, which is why they are not checked.
	 *
	 * `WP_Theme::get()` returns them through `esc_url_raw()`, which percent-encodes the
	 * delimiters. If that ever stops being true, this fails and the two belong in the
	 * list above.
	 *
	 * @dataProvider data_payloads
	 *
	 * @param string $payload Value appended to the URL.
	 * @return void
	 */
	public function test_uri_headers_are_inert_before_the_check( string $payload ): void {
		$upload = new WPORG_Themes_Upload();
		$upload->create_tmp_dirs( 'fixture' );

		$this->uploads[] = $upload;

		$url = 'https://example.org/' . $payload;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents(
			$upload->theme_dir . '/style.css',
			$this->style_css(
				array(
					'Theme URI'  => $url,
					'Author URI' => $url,
				)
			)
		);

		$theme = new WP_Theme( basename( $upload->theme_dir ), dirname( $upload->theme_dir ) );

		foreach ( array( 'ThemeURI', 'AuthorURI' ) as $header ) {
			$stored = (string) $theme->get( $header );

			$this->assertNotSame( '', $stored, "The {$header} header did not survive at all." );
			$this->assertSame( 0, preg_match( '/' . get_shortcode_regex() . '/', $stored ) );
		}
	}

	/**
	 * A version going live encodes the delimiters of a name that carries a shortcode.
	 *
	 * The name only reaches `post_title` from SVN when it still sanitizes to the stored
	 * `post_name`, which is the branch this exercises.
	 *
	 * @return void
	 */
	public function test_live_version_stores_an_inert_name(): void {
		$name    = 'Fixture ' . self::DIRECT . ' Theme';
		$post_id = $this->create_theme_post( 'Fixture Theme', $this->slug_for( $name ) );

		$this->serve_style_css( $name );
		wporg_themes_approve_version( $post_id, '1.0', 'old' );

		$stored = get_post( $post_id )->post_title;

		$this->assertStringNotContainsString( '[', $stored );
		$this->assertSame( $stored, do_shortcode( $stored ) );

		// The wording survives; only the delimiters the parser reads are encoded.
		$this->assertSame( $name, html_entity_decode( $stored ) );
	}

	/**
	 * A title stored before this change is encoded the next time a version goes live.
	 *
	 * Here the SVN name matches what is stored, so the existing title is what gets
	 * written back.
	 *
	 * @return void
	 */
	public function test_live_version_encodes_an_already_stored_name(): void {
		$name    = 'Fixture ' . self::ESCAPED_TWIN . ' Theme';
		$post_id = $this->create_theme_post( $name, 'fixture-theme' );

		$this->serve_style_css( $name );
		wporg_themes_approve_version( $post_id, '1.0', 'old' );

		$stored = get_post( $post_id )->post_title;

		$this->assertStringNotContainsString( '[', $stored );
		$this->assertSame( $stored, do_shortcode( $stored ) );
		$this->assertSame( $name, html_entity_decode( $stored ) );
	}

	/**
	 * Encoding a name that carries none of the syntax changes nothing.
	 *
	 * @return void
	 */
	public function test_live_version_leaves_an_ordinary_name_alone(): void {
		$post_id = $this->create_theme_post();

		$this->serve_style_css( 'Fixture Theme' );
		wporg_themes_approve_version( $post_id, '1.0', 'old' );

		$this->assertSame( 'Fixture Theme', get_post( $post_id )->post_title );
	}
}
