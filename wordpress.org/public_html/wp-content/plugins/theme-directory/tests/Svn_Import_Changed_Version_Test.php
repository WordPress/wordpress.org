<?php
/**
 * Tests that the SVN watcher routes a changeset to the version it touched.
 *
 * A file-only commit rewrites bytes inside an existing version without adding or
 * removing a directory node. Such a commit must still be scheduled for re-import
 * and re-scan, so the path selection considers file paths, not only directories.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Theme_Directory\Jobs\SVN_Import;

/**
 * Covers SVN_Import::changed_slug_version().
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

		return simplexml_load_string(
			'<logentry revision="123"><author>author</author><msg>m</msg><paths>' . $path_xml . '</paths></logentry>'
		);
	}

	/**
	 * A directory node for a version routes to that version, as it always has.
	 */
	public function test_directory_commit_selects_version(): void {
		$entry = $this->log_entry( array( array( 'dir', '/my-theme/1.4' ) ) );

		$this->assertSame( array( 'my-theme', '1.4' ), SVN_Import::changed_slug_version( $entry ) );
	}

	/**
	 * A file-only commit inside a version routes to that version.
	 *
	 * This is the case the watcher previously skipped, letting post-review bytes
	 * ship without a re-scan.
	 */
	public function test_file_only_commit_selects_version(): void {
		$entry = $this->log_entry( array( array( 'file', '/my-theme/1.4/functions.php' ) ) );

		$this->assertSame( array( 'my-theme', '1.4' ), SVN_Import::changed_slug_version( $entry ) );
	}

	/**
	 * A file directly under the theme root names no version and is ignored.
	 */
	public function test_theme_root_file_selects_nothing(): void {
		$entry = $this->log_entry( array( array( 'file', '/my-theme/readme.txt' ) ) );

		$this->assertSame( array( '', '' ), SVN_Import::changed_slug_version( $entry ) );
	}

	/**
	 * A commit that touches no theme version path selects nothing.
	 */
	public function test_no_version_path_selects_nothing(): void {
		$entry = $this->log_entry( array( array( 'dir', '/my-theme' ) ) );

		$this->assertSame( array( '', '' ), SVN_Import::changed_slug_version( $entry ) );
	}

	/**
	 * The first version path in commit order wins, whatever its node kind.
	 */
	public function test_first_version_path_wins(): void {
		$entry = $this->log_entry(
			array(
				array( 'file', '/my-theme/2.0/style.css' ),
				array( 'dir', '/other-theme/1.0' ),
			)
		);

		$this->assertSame( array( 'my-theme', '2.0' ), SVN_Import::changed_slug_version( $entry ) );
	}
}
