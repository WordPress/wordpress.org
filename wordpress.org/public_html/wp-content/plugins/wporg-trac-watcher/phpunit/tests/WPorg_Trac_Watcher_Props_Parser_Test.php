<?php
/**
 * Tests for extracting props from commit messages.
 *
 * @package wporg-trac-watcher
 */

declare( strict_types = 1 );

use function WordPressdotorg\Trac\Watcher\Props\from_log;

defined( 'ABSPATH' ) || die();

/**
 * Covers which names from_log() credits for a commit.
 */
class WPorg_Trac_Watcher_Props_Parser_Test extends WPorg_Trac_Watcher_TestCase {

	/**
	 * Names on an Unprops line are not credited.
	 *
	 * @dataProvider data_unprops
	 *
	 * @param string $message  Commit message.
	 * @param array  $expected Names credited for the commit.
	 * @return void
	 */
	public function test_unprops_are_not_credited( string $message, array $expected ): void {
		$this->assertSame( $expected, array_values( from_log( $message ) ) );
	}

	/**
	 * Commit messages that unprop someone.
	 *
	 * @return array
	 */
	public static function data_unprops(): array {
		return array(
			'alongside props' => array(
				"Docs: Fix a typo.\n\nProps alice.\nUnprops bob.\nFixes #59000.",
				array( 'alice' ),
			),
			'unprops only'    => array(
				"Coding Standards: Fix whitespace.\n\nUnprops: bob, carol.\nSee #59000.",
				array(),
			),
		);
	}
}
