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

	public static function matches_provider() {
		return array(
			// Format: [ $version, $tag ].
			'exact match'               => array( '1.0', '1.0' ),
			'short tag, long version'   => array( '1.0.0', '1.0' ),
			'short version, long tag'   => array( '1.0', '1.0.0' ),
			'leading v on tag'          => array( '1.0', 'v1.0' ),
			'leading v on version'      => array( 'v1.0', '1.0' ),
			'capital V prefix'          => array( 'V1.0', '1.0' ),
			'Version word prefix'       => array( 'Version 1.0', '1.0' ),
			'Version: prefix'           => array( 'Version: 1.0', '1.0' ),
			'release- prefix on tag'    => array( '1.4.0', 'release-1.4.0' ),
			'tag- prefix on tag'        => array( '2.0', 'tag-2.0' ),
			'hover- prefix on tag'      => array( '1.0', 'hover-1.0' ),
			'-beta trailing on version' => array( '1.0-beta', '1.0' ),
			'space-&-beta trailing'     => array( '1.0 & beta', '1.0' ),
			'empty version'             => array( '', '1.0' ),
			'empty tag'                 => array( '1.0', '' ),
			'both empty'                => array( '', '' ),
			'no digits'                 => array( 'abc', '1.0' ),
		);
	}

	public static function mismatches_provider() {
		return array(
			// Format: [ $version, $tag ].
			'bandsintown case'       => array( '1.4.0', '1.4.1' ),
			'different major'        => array( '1.0', '2.0' ),
			'tag has trailing digit' => array( '1.4.0', '1.4.10' ),
			'release- prefix on tag' => array( '1.0', 'release-2.0' ),
			'tag-beta still ahead'   => array( '1.4.0', '1.4.1-beta' ),
			'header ahead of tag'    => array( '2.0', '1.0' ),
		);
	}
}
