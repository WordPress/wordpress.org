<?php
/**
 * Tests for Import::version_is_path_safe().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;

/**
 * Covers the values a Version header may hold when it is used as a path component.
 *
 * @group import
 */
class Import_Version_Is_Path_Safe_Test extends TestCase {

	/**
	 * Values that must remain usable as a path component.
	 *
	 * @dataProvider safe_provider
	 *
	 * @param mixed $version The Version header value under test.
	 * @return void
	 */
	public function test_safe( $version ): void {
		$this->assertTrue(
			Import::version_is_path_safe( $version ),
			"Expected '{$version}' to be usable as a path component"
		);
	}

	/**
	 * Values that would leave the directory the version names.
	 *
	 * @dataProvider unsafe_provider
	 *
	 * @param mixed $version The Version header value under test.
	 * @return void
	 */
	public function test_unsafe( $version ): void {
		$this->assertFalse(
			Import::version_is_path_safe( $version ),
			'Expected the value to be rejected as a path component'
		);
	}

	/**
	 * Supplies values that must be accepted.
	 *
	 * A `/` or `\` only nests a directory deeper, so the malformed-but-harmless headers already
	 * published in the directory have to keep importing.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public static function safe_provider(): array {
		return array(
			// Format: [ $version ].
			'plain'                   => array( '1.0.0' ),
			'four segments'           => array( '1.2.3.4' ),
			'pre-release suffix'      => array( '2.0-RC1' ),
			'double dot typo'         => array( '1..0' ),
			'parenthesised note'      => array( '1.0 (beta)' ),
			'semver build metadata'   => array( 'v1.2+build.5' ),
			'leading dots in segment' => array( '..foo' ),
			'date appended'           => array( '1.0 8/1/13' ),
			'date only'               => array( '2008/11/16' ),
			'comma then date'         => array( '0.8.3.2, 12/10/2010' ),
			'trailing backslash'      => array( '2.0\\' ),
			'leaked line comment'     => array( '1.2.1  // Increment from 1.2.0' ),
		);
	}

	/**
	 * Supplies values that must be rejected.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public static function unsafe_provider(): array {
		return array(
			// Format: [ $version ].
			'parent traversal'   => array( '../../../../var/www/html' ),
			'bare parent'        => array( '..' ),
			'parent mid-path'    => array( '1.0/../../etc' ),
			'backslash parent'   => array( '1.0\\..\\..' ),
			'absolute path'      => array( '/etc/passwd' ),
			'absolute backslash' => array( '\\etc' ),
			'trailing newline'   => array( "1.0\n" ),
			'tab separated'      => array( "1.0.2\t12/21/2009" ),
			'null byte'          => array( "1.0\0" ),
			'delete character'   => array( "1.0\x7f" ),
			'empty string'       => array( '' ),
			'not a string'       => array( array( '1.0' ) ),
		);
	}
}
