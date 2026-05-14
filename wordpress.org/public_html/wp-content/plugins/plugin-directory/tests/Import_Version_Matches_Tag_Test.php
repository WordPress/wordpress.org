<?php
/**
 * Tests for Import::version_matches_tag().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;

/**
 * @group import
 */
class Import_Version_Matches_Tag_Test extends TestCase {

	/**
	 * @dataProvider matches_provider
	 */
	public function test_matches( $version, $tag ) {
		$this->assertTrue(
			Import::version_matches_tag( $version, $tag ),
			"Expected '{$version}' to match tag '{$tag}'"
		);
	}

	/**
	 * @dataProvider mismatches_provider
	 */
	public function test_mismatches( $version, $tag ) {
		$this->assertFalse(
			Import::version_matches_tag( $version, $tag ),
			"Expected '{$version}' to be flagged as a mismatch with tag '{$tag}'"
		);
	}

	public function matches_provider() {
		return array(
			'exact match'                     => array( '1.0', '1.0' ),
			'leading v on tag'                => array( '1.0', 'v1.0' ),
			'leading v on version'            => array( 'v1.0', '1.0' ),
			'leading v on both'               => array( 'v1.0', 'v1.0' ),
			'Version word prefix on version'  => array( 'Version 1.0', '1.0' ),
			'Version: prefix on version'      => array( 'Version: 1.0', '1.0' ),
			'tag is shorter version'          => array( '1.4.0', '1.4' ),
			'tag is longer version'           => array( '1.4', '1.4.0' ),
			'version with build suffix'       => array( '1.0-beta', '1.0' ),
			'empty version'                   => array( '', '1.0' ),
			'empty tag'                       => array( '1.0', '' ),
			'both empty'                      => array( '', '' ),
			'capital V prefix'                => array( 'V1.0', '1.0' ),
		);
	}

	public function mismatches_provider() {
		return array(
			'bandsintown case'        => array( '1.4.0', '1.4.1' ),
			'different major'         => array( '1.0', '2.0' ),
			'unrelated version'       => array( '3.2.1', '1.0' ),
			'partial overlap by char' => array( '1.4.0', '1.4.10' ), // 1.4.0 not in 1.4.10, 1.4.10 not in 1.4.0.
		);
	}
}
