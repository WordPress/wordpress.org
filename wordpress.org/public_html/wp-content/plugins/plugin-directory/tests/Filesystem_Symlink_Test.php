<?php
/**
 * Tests that Filesystem::list() never hands a symlink to its callers.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;

/**
 * Symlinks reach the plugin directory through `svn export`, which materialises `svn:special`
 * entries as real symlinks. `isFile()` and `isDir()` stat the target, so without an explicit
 * check a symlink is listed as whatever it points at and is then read by the caller.
 *
 * @group filesystem
 */
class Filesystem_Symlink_Test extends TestCase {

	/**
	 * Directory holding the tree under test.
	 *
	 * @var string
	 */
	protected string $dir = '';

	/**
	 * Directory outside the tree, holding the targets of the symlinks.
	 *
	 * @var string
	 */
	protected string $outside = '';

	// phpcs:disable WordPress.WP.AlternativeFunctions -- WP_Filesystem cannot create the symlinks this fixture is made of.

	/**
	 * Builds a tree containing one real file, one real directory, and a symlink to each.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->outside = sys_get_temp_dir() . '/' . uniqid( 'fs-outside-', true );
		mkdir( $this->outside );

		// A missing target would leave a dangling symlink, which `isFile()` rejects even without the guard under test.
		$this->assertNotFalse( file_put_contents( $this->outside . '/secret.txt', 'secret' ) );

		$this->dir = sys_get_temp_dir() . '/' . uniqid( 'fs-tree-', true );
		mkdir( $this->dir );
		mkdir( $this->dir . '/real-dir' );
		$this->assertNotFalse( file_put_contents( $this->dir . '/real.txt', 'real' ) );

		// Without these the fixture holds only real entries and every assertion below passes vacuously.
		$this->assertTrue( symlink( $this->outside . '/secret.txt', $this->dir . '/readme.txt' ) );
		$this->assertTrue( symlink( $this->outside, $this->dir . '/linked-dir' ) );
	}

	// phpcs:enable WordPress.WP.AlternativeFunctions

	/**
	 * Removes both fixture roots.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// `Filesystem::rmdir()` no-ops on the empty string an aborted `setUp()` leaves behind.
		Filesystem::rmdir( $this->dir );
		Filesystem::rmdir( $this->outside );

		parent::tearDown();
	}

	/**
	 * A symlink to a regular file must not be listed as a file.
	 *
	 * @return void
	 */
	public function test_symlinked_file_is_not_listed(): void {
		$files = array_map( 'basename', Filesystem::list_files( $this->dir ) );

		$this->assertContains( 'real.txt', $files );
		$this->assertNotContains( 'readme.txt', $files );
	}

	/**
	 * A symlink matching the caller's pattern must not be returned either — this is the
	 * lookup `Import::find_readme_file()` performs on the SVN export.
	 *
	 * @return void
	 */
	public function test_symlinked_file_is_not_matched_by_pattern(): void {
		$files = Filesystem::list_files( $this->dir, false, '!(?:^|/)readme\.(txt|md)$!i' );

		$this->assertSame( array(), $files );
	}

	/**
	 * A symlink to a directory must not be listed as a directory.
	 *
	 * @return void
	 */
	public function test_symlinked_directory_is_not_listed(): void {
		$dirs = array_map( 'basename', Filesystem::list( $this->dir, 'directories' ) );

		$this->assertContains( 'real-dir', $dirs );
		$this->assertNotContains( 'linked-dir', $dirs );
	}

	/**
	 * Neither symlink survives an unfiltered listing.
	 *
	 * @return void
	 */
	public function test_symlinks_are_absent_from_full_listing(): void {
		$all = array_map( 'basename', Filesystem::list( $this->dir ) );

		sort( $all );
		$this->assertSame( array( 'real-dir', 'real.txt' ), $all );
	}
}
