<?php
/**
 * Tests that the SVN watcher finds every version a changeset touched.
 *
 * A file-only commit rewrites bytes inside an existing version without adding or
 * removing a directory node, and one commit may touch several versions. Each must
 * be scheduled for re-import and re-scan, so the path selection considers file
 * paths, not only directories, and returns every version it finds.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Theme_Directory\Jobs\SVN_Import;

/**
 * Covers SVN_Import::changed_slug_versions().
 *
 * @group svn-import
 */
class Svn_Import_Changed_Version_Test extends TestCase {

	/**
	 * Builds a `<logentry>` element from a list of changed paths.
	 *
	 * @param array<int, array{0: string, 1: string}> $paths Pairs of SVN node kind and path.
	 * @return SimpleXMLElement
	 */
	private function log_entry( array $paths ): SimpleXMLElement {
		$path_xml = '';
		foreach ( $paths as $path ) {
			$path_xml .= sprintf(
				'<path kind="%1$s" action="M">%2$s</path>',
				$path[0],
				$path[1]
			);
		}

		$element = simplexml_load_string(
			'<logentry revision="123"><author>author</author><msg>m</msg><paths>' . $path_xml . '</paths></logentry>'
		);
		$this->assertNotFalse( $element, 'The fixture XML failed to parse.' );

		return $element;
	}

	/**
	 * A directory node for a version selects that version.
	 */
	public function test_directory_commit_selects_version(): void {
		$entry = $this->log_entry( array( array( 'dir', '/my-theme/1.4' ) ) );

		$this->assertSame( array( array( 'my-theme', '1.4' ) ), SVN_Import::changed_slug_versions( $entry ) );
	}

	/**
	 * A file-only commit inside a version selects that version.
	 *
	 * This is the case the watcher previously skipped, letting post-review bytes
	 * ship without a re-scan.
	 */
	public function test_file_only_commit_selects_version(): void {
		$entry = $this->log_entry( array( array( 'file', '/my-theme/1.4/functions.php' ) ) );

		$this->assertSame( array( array( 'my-theme', '1.4' ) ), SVN_Import::changed_slug_versions( $entry ) );
	}

	/**
	 * A file directly under the theme root names no version and is ignored.
	 */
	public function test_theme_root_file_selects_nothing(): void {
		$entry = $this->log_entry( array( array( 'file', '/my-theme/readme.txt' ) ) );

		$this->assertSame( array(), SVN_Import::changed_slug_versions( $entry ) );
	}

	/**
	 * A node with no `kind` attribute makes no version from a bare `/slug/x` path,
	 * matching the watcher's former directory-only guard.
	 */
	public function test_kindless_theme_root_node_selects_nothing(): void {
		$entry = $this->log_entry( array( array( '', '/my-theme/assets' ) ) );

		$this->assertSame( array(), SVN_Import::changed_slug_versions( $entry ) );
	}

	/**
	 * A commit that touches no theme version path selects nothing.
	 */
	public function test_no_version_path_selects_nothing(): void {
		$entry = $this->log_entry( array( array( 'dir', '/my-theme' ) ) );

		$this->assertSame( array(), SVN_Import::changed_slug_versions( $entry ) );
	}

	/**
	 * A non-version directory (a name not starting with a digit) is not a version.
	 */
	public function test_non_version_directory_selects_nothing(): void {
		$entry = $this->log_entry( array( array( 'dir', '/my-theme/assets' ) ) );

		$this->assertSame( array(), SVN_Import::changed_slug_versions( $entry ) );
	}

	/**
	 * A file nested under a non-version directory is not a version either.
	 */
	public function test_file_under_non_version_directory_selects_nothing(): void {
		$entry = $this->log_entry( array( array( 'file', '/my-theme/assets/app.js' ) ) );

		$this->assertSame( array(), SVN_Import::changed_slug_versions( $entry ) );
	}

	/**
	 * A commit that adds a version and edits an older version's files selects both.
	 */
	public function test_mixed_commit_selects_every_version(): void {
		$entry = $this->log_entry(
			array(
				array( 'file', '/my-theme/1.4/functions.php' ),
				array( 'dir', '/my-theme/2.0' ),
			)
		);

		$this->assertSame(
			array( array( 'my-theme', '1.4' ), array( 'my-theme', '2.0' ) ),
			SVN_Import::changed_slug_versions( $entry )
		);
	}

	/**
	 * A file-only commit editing several versions selects each of them.
	 */
	public function test_file_only_commit_selects_every_version(): void {
		$entry = $this->log_entry(
			array(
				array( 'file', '/my-theme/1.4/functions.php' ),
				array( 'file', '/my-theme/1.5/style.css' ),
			)
		);

		$this->assertSame(
			array( array( 'my-theme', '1.4' ), array( 'my-theme', '1.5' ) ),
			SVN_Import::changed_slug_versions( $entry )
		);
	}

	/**
	 * Several changed paths within one version collapse to a single entry.
	 */
	public function test_paths_in_one_version_are_deduplicated(): void {
		$entry = $this->log_entry(
			array(
				array( 'dir', '/my-theme/2.0' ),
				array( 'file', '/my-theme/2.0/style.css' ),
			)
		);

		$this->assertSame( array( array( 'my-theme', '2.0' ) ), SVN_Import::changed_slug_versions( $entry ) );
	}
}
