<?php
/**
 * Tests that the theme package holds no file the automated review cannot read.
 *
 * Review runs over `WP_Theme::get_files()`, while the package is built from the whole
 * extracted tree. The scanner's dot-prefixed exclusion is not filterable, so a hidden
 * file ships without any check reading it; the import fails on that difference.
 * Filenames that Win32 resolves to a different target are rejected for the same reason.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Covers WPORG_Themes_Upload::unreviewable_files() and non_portable_files().
 *
 * @group upload
 */
class Review_Corpus_Test extends TestCase {

	/**
	 * Absolute path of the theme root built for the current test.
	 *
	 * @var string
	 */
	private $theme_root = '';

	/**
	 * Removes the theme tree built for the test.
	 */
	public function tearDown(): void {
		if ( $this->theme_root && is_dir( $this->theme_root ) ) {
			$entries = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $this->theme_root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $entries as $entry ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions -- A test fixture under the system temp dir; WP_Filesystem is not bootstrapped here.
				$entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
			}

			rmdir( $this->theme_root ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- See above.
		}

		$this->theme_root = '';

		parent::tearDown();
	}

	/**
	 * Writes a theme tree and returns an uploader pointed at it.
	 *
	 * @param array $files Package-relative paths mapped to their contents.
	 * @return WPORG_Themes_Upload The uploader under test.
	 */
	private function create_upload( array $files ): WPORG_Themes_Upload {
		$this->theme_root = get_temp_dir() . 'review-corpus-' . wp_generate_password( 12, false );
		$theme_dir        = $this->theme_root . '/a-theme';

		foreach ( $files as $path => $contents ) {
			$full = $theme_dir . '/' . $path;
			wp_mkdir_p( dirname( $full ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions -- A test fixture under the system temp dir; WP_Filesystem is not bootstrapped here.
			file_put_contents( $full, $contents );
		}

		$upload            = new WPORG_Themes_Upload();
		$upload->theme_dir = $theme_dir;
		$upload->theme     = new WP_Theme( 'a-theme', $this->theme_root );

		return $upload;
	}

	/**
	 * The theme's own files are all reviewed.
	 */
	public function test_ordinary_tree_is_fully_reviewable(): void {
		$upload = $this->create_upload(
			array(
				'style.css'     => '/* Theme Name: A Theme */',
				'index.php'     => '<?php',
				'inc/setup.php' => '<?php',
			)
		);

		$this->assertSame( array(), $upload->unreviewable_files() );
	}

	/**
	 * Directories the scanner excludes by name are not reported; they are a separate, open gap.
	 */
	public function test_excluded_directories_are_not_reported(): void {
		$upload = $this->create_upload(
			array(
				'style.css'                => '/* Theme Name: A Theme */',
				'vendor/autoload.php'      => '<?php',
				'node_modules/a/index.php' => '<?php',
				'bower_components/b.php'   => '<?php',
				'CVS/c.php'                => '<?php',
			)
		);

		$this->assertSame(
			array(),
			$upload->unreviewable_files(),
			'Files under an excluded directory name should not be reported as unreviewable.'
		);

		$this->assertNotContains(
			'vendor/autoload.php',
			array_keys( (array) $upload->theme->get_files( null, -1, false ) ),
			'The scanner itself should still not reach the excluded subtrees.'
		);
	}

	/**
	 * Hidden files ship in the package but never reach the scanner, so they fail the import.
	 */
	public function test_hidden_files_are_unreviewable(): void {
		$upload = $this->create_upload(
			array(
				'style.css'              => '/* Theme Name: A Theme */',
				'languages/.payload.php' => '<?php',
				'.env.php'               => '<?php',
			)
		);

		$this->assertSame( array( '.env.php', 'languages/.payload.php' ), $upload->unreviewable_files() );
	}

	/**
	 * A hidden file inside an excluded directory is reported; its visible siblings are not.
	 */
	public function test_hidden_file_inside_excluded_directory_is_reported(): void {
		$upload = $this->create_upload(
			array(
				'style.css'                      => '/* Theme Name: A Theme */',
				'vendor/autoload.php'            => '<?php',
				'vendor/wptt/.gitattributes'     => 'text eol=lf',
				'vendor/wptt/webfont-loader.php' => '<?php',
			)
		);

		$this->assertSame( array( 'vendor/wptt/.gitattributes' ), $upload->unreviewable_files() );
	}

	/**
	 * Portable names are accepted.
	 *
	 * @dataProvider data_portable_names
	 *
	 * @param string $path A package path that should be accepted.
	 */
	public function test_portable_names_are_accepted( string $path ): void {
		$this->assertSame( array(), WPORG_Themes_Upload::non_portable_files( array( $path ) ) );
	}

	/**
	 * Names that resolve to one file on every supported platform.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_portable_names(): array {
		return array(
			'plain file'          => array( 'functions.php' ),
			'nested file'         => array( 'inc/core/ThemeSetup.php' ),
			'spaces'              => array( 'assets/my icon.svg' ),
			'leading dot'         => array( 'languages/.gitkeep' ),
			'device name in word' => array( 'inc/console.php' ),
			'device as extension' => array( 'assets/icon.con' ),
			'mixed case'          => array( 'assets/Foo.php' ),
		);
	}

	/**
	 * Names that Win32 resolves to a different target are rejected.
	 *
	 * @dataProvider data_non_portable_names
	 *
	 * @param string $path A package path that should be rejected.
	 */
	public function test_non_portable_names_are_rejected( string $path ): void {
		$this->assertSame( array( $path ), WPORG_Themes_Upload::non_portable_files( array( $path ) ) );
	}

	/**
	 * Names that alias to a file other than the one review read.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_non_portable_names(): array {
		return array(
			'alternate data stream' => array( 'functions.php::$DATA' ),
			'named stream'          => array( 'style.css:hidden.php' ),
			'zone identifier'       => array( 'assets/icon.svg:Zone.Identifier' ),
			'stream on a directory' => array( 'inc:stream/setup.php' ),
			'backslash separator'   => array( 'inc\\setup.php' ),
			'trailing period'       => array( 'functions.php.' ),
			'trailing space'        => array( 'functions.php ' ),
			'reserved device'       => array( 'inc/nul.php' ),
			'reserved device alone' => array( 'CON' ),
			'serial port'           => array( 'assets/com1.txt' ),
			'control character'     => array( "assets/icon\t.svg" ),
			'less than'             => array( 'assets/a<b.txt' ),
			'greater than'          => array( 'assets/a>b.txt' ),
			'double quote'          => array( 'assets/a"b.txt' ),
			'pipe'                  => array( 'assets/a|b.php' ),
			'question mark'         => array( 'assets/icon?.svg' ),
			'asterisk'              => array( 'assets/x*.css' ),
		);
	}

	/**
	 * Paths differing only by case name one file on a case-insensitive filesystem, so every member is reported.
	 */
	public function test_case_only_duplicates_are_rejected(): void {
		$files = array(
			'style.css',
			'assets/Foo.php',
			'assets/foo.php',
			'inc/setup.php',
		);

		$this->assertSame(
			array( 'assets/Foo.php', 'assets/foo.php' ),
			WPORG_Themes_Upload::non_portable_files( $files )
		);
	}

	/**
	 * A path caught by two rules is reported once.
	 */
	public function test_paths_are_reported_once(): void {
		$files = array( 'assets/A?.php', 'assets/a?.php' );

		$this->assertSame(
			array( 'assets/A?.php', 'assets/a?.php' ),
			WPORG_Themes_Upload::non_portable_files( $files )
		);
	}

	/**
	 * Every rejected path is reported, and accepted ones are left out.
	 */
	public function test_only_the_non_portable_paths_are_returned(): void {
		$files = array(
			'style.css',
			'functions.php::$DATA',
			'inc/setup.php',
			'assets/icon.svg:Zone.Identifier',
		);

		$this->assertSame(
			array( 'assets/icon.svg:Zone.Identifier', 'functions.php::$DATA' ),
			WPORG_Themes_Upload::non_portable_files( $files )
		);
	}
}
