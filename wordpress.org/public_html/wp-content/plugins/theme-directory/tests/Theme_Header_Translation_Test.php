<?php
/**
 * Tests that theme headers are read without engaging the translation system.
 *
 * `WP_Theme::display()` loads the theme's own text domain before translating a
 * header, and WordPress loads PHP translation files by including them. A theme
 * being imported is unreviewed upload content, so
 * `WPORG_Themes_Upload::get_theme_header()` reads headers with translation
 * disabled, and `block_upload_textdomain()` backs that up by refusing any
 * translation load that resolves inside the extracted upload. These tests pin
 * both behaviors.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Tests for `WPORG_Themes_Upload::get_theme_header()`.
 *
 * @group upload
 */
class Theme_Header_Translation_Test extends TestCase {

	/**
	 * Text domain declared by the fixture theme.
	 *
	 * @var string
	 */
	const FIXTURE_DOMAIN = 'wporg-fixture-theme-domain';

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
	 * Number of translation lookups made against the fixture's text domain.
	 *
	 * @var int
	 */
	protected $translation_lookups = 0;

	/**
	 * The `gettext` callback, kept so it can be removed again.
	 *
	 * @var callable|null
	 */
	protected $gettext_callback = null;

	/**
	 * Uploads created during a test.
	 *
	 * An import registers hooks bound to the extraction directory; they are removed
	 * again on teardown so they cannot affect a later test.
	 *
	 * @var array
	 */
	protected $uploads = array();

	/**
	 * Writes the fixture theme and starts counting translation lookups.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// A unique root per test, so WP_Theme's header cache cannot be reused.
		$this->theme_root = untrailingslashit( get_temp_dir() ) . '/wporg-theme-header-' . uniqid();
		wp_mkdir_p( $this->theme_root . '/' . $this->stylesheet );

		$style_css = "/*\nTheme Name: Fixture Theme\nVersion: 1.0\nText Domain: " . self::FIXTURE_DOMAIN . "\n*/\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents( $this->theme_root . '/' . $this->stylesheet . '/style.css', $style_css );

		// A lookup against the fixture's domain means the translation system was engaged.
		$this->translation_lookups = 0;
		$this->gettext_callback    = function ( $translation, $text, $domain ) {
			if ( self::FIXTURE_DOMAIN === $domain ) {
				++$this->translation_lookups;

				return 'TRANSLATED';
			}

			return $translation;
		};

		add_filter( 'gettext', $this->gettext_callback, 10, 3 );
	}

	/**
	 * Removes the filter and the fixture theme.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_filter( 'gettext', $this->gettext_callback, 10 );
		$this->gettext_callback = null;

		foreach ( $this->uploads as $upload ) {
			remove_filter( 'override_load_textdomain', array( $upload, 'block_upload_textdomain' ), 10 );
			remove_action( 'shutdown', array( $upload, 'remove_files' ), 10 );

			if ( $upload->tmp_dir ) {
				$this->remove_fixture( $upload->tmp_dir );
			}
		}
		$this->uploads = array();

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
	 * Builds an upload whose theme is the fixture.
	 *
	 * @return WPORG_Themes_Upload
	 */
	protected function create_upload(): WPORG_Themes_Upload {
		$upload        = new WPORG_Themes_Upload();
		$upload->theme = new WP_Theme( $this->stylesheet, $this->theme_root );

		$this->uploads[] = $upload;

		return $upload;
	}

	/**
	 * Absolute path of the fixture theme's own directory, which exists on disk.
	 *
	 * @return string
	 */
	protected function theme_dir(): string {
		return $this->theme_root . '/' . $this->stylesheet;
	}

	/**
	 * Reading a header must return the value as written in style.css, without
	 * the theme's own text domain being consulted.
	 *
	 * @return void
	 */
	public function test_get_theme_header_does_not_translate(): void {
		$upload = $this->create_upload();

		$name = $upload->get_theme_header( 'Name' );

		$this->assertSame( 'Fixture Theme', $name );
		$this->assertSame(
			0,
			$this->translation_lookups,
			'Reading a header from an imported theme must not engage the translation system.'
		);
	}

	/**
	 * The same holds for every header the importer reads, not just the name.
	 *
	 * @return void
	 */
	public function test_get_theme_header_does_not_translate_version(): void {
		$upload = $this->create_upload();

		$version = $upload->get_theme_header( 'Version' );

		$this->assertSame( '1.0', $version );
		$this->assertSame( 0, $this->translation_lookups );
	}

	/**
	 * Characterizes the behavior the third argument of `WP_Theme::display()`
	 * guards against: left at its default, `display()` runs the theme's own text
	 * domain through the translation system, which is what loads translation
	 * files out of the theme directory.
	 *
	 * If this ever stops holding, `get_theme_header()` can be re-evaluated.
	 *
	 * @return void
	 */
	public function test_display_with_translation_enabled_engages_translation(): void {
		$theme = new WP_Theme( $this->stylesheet, $this->theme_root );

		$name = $theme->display( 'Name' );

		$this->assertSame( 'TRANSLATED', $name );
		$this->assertGreaterThan(
			0,
			$this->translation_lookups,
			'display() with translation enabled is expected to consult the theme text domain.'
		);
	}

	/**
	 * Importing attaches the backstop, before any of the theme's own files are read.
	 *
	 * `import()` is protected, so it is invoked by reflection. Reaching the
	 * registration needs nothing but an extracted style.css: Trac and SVN are gated
	 * off by their password constants, and the import returns the style.css errors
	 * shortly afterwards, so no network or repository work runs.
	 *
	 * @return void
	 */
	public function test_import_registers_the_backstop(): void {
		$upload = $this->create_upload();
		$upload->create_tmp_dirs( 'fixture' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Test fixture in a temporary directory.
		copy( $this->theme_dir() . '/style.css', $upload->theme_dir . '/style.css' );

		$import = new ReflectionMethod( WPORG_Themes_Upload::class, 'import' );
		$result = $import->invoke( $upload );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertTrue(
			apply_filters(
				'override_load_textdomain',
				false,
				self::FIXTURE_DOMAIN,
				$upload->theme_dir . '/en_US.mo'
			)
		);
	}

	/**
	 * A translation file resolving inside the extraction directory is refused.
	 *
	 * @return void
	 */
	public function test_blocks_translation_load_from_inside_the_upload(): void {
		$upload          = $this->create_upload();
		$upload->tmp_dir = $this->theme_root;

		$this->assertTrue(
			$upload->block_upload_textdomain( false, self::FIXTURE_DOMAIN, $this->theme_dir() . '/en_US.mo' )
		);
	}

	/**
	 * A translation file outside the extraction directory loads normally, so the
	 * backstop cannot break unrelated text domains.
	 *
	 * @return void
	 */
	public function test_allows_translation_load_from_outside_the_upload(): void {
		$upload          = $this->create_upload();
		$upload->tmp_dir = $this->theme_root;

		$outside = untrailingslashit( get_temp_dir() ) . '/en_US.mo';

		$this->assertFalse( $upload->block_upload_textdomain( false, 'wporg-themes', $outside ) );
	}

	/**
	 * With no extraction directory in play, every load passes through.
	 *
	 * @return void
	 */
	public function test_backstop_is_inert_without_an_extraction_directory(): void {
		$upload = $this->create_upload();

		$this->assertSame( '', $upload->tmp_dir );
		$this->assertFalse(
			$upload->block_upload_textdomain( false, 'wporg-themes', $this->theme_dir() . '/en_US.mo' )
		);
	}

	/**
	 * Once the extraction directory is gone there is nothing left to protect, and
	 * the backstop must not start refusing unrelated loads: `remove_files()`
	 * deletes that directory on shutdown while this filter is still attached.
	 *
	 * @return void
	 */
	public function test_backstop_is_inert_once_the_extraction_directory_is_gone(): void {
		$upload          = $this->create_upload();
		$upload->tmp_dir = $this->theme_root . '/already-removed';

		$this->assertFalse(
			$upload->block_upload_textdomain( false, 'wporg-themes', $this->theme_dir() . '/en_US.mo' )
		);
	}

	/**
	 * A path that cannot be resolved while the extraction directory is on disk is
	 * refused rather than guessed at.
	 *
	 * @return void
	 */
	public function test_refuses_an_unresolvable_path_while_the_upload_is_on_disk(): void {
		$upload          = $this->create_upload();
		$upload->tmp_dir = $this->theme_root;

		$this->assertTrue(
			$upload->block_upload_textdomain( false, self::FIXTURE_DOMAIN, $this->theme_root . '/missing/en_US.mo' )
		);
	}
}
