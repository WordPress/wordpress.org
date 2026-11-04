<?php
/**
 * Tests for shortening subjects for the browser tab.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use Modules\WPOrgPluginReview\Services\Subject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the short subjects of the plugin directory's emails, known kinds or not.
 */
final class SubjectTest extends TestCase {

	/**
	 * The most common kinds of email get their mark in place of everything before the plugin's name, replies and
	 * forwards included.
	 *
	 * @dataProvider data_marked_subjects
	 *
	 * @param string $subject Subject.
	 * @param string $marked  Its marked version.
	 * @return void
	 */
	public function test_marks_the_most_common_subjects( string $subject, string $marked ): void {
		$this->assertSame( $marked, Subject::marked( $subject ) );
		$this->assertSame( $marked, Subject::short( $subject ) );
	}

	/**
	 * Subjects, and their marked versions.
	 *
	 * @return array[]
	 */
	public static function data_marked_subjects(): array {
		return array(
			'review in progress' => array( '[WordPress Plugin Directory] Review in Progress: Acme Forms', '🔌 Acme Forms' ),
			'forwarded reply'    => array( 'Fwd: Re: RE: [WordPress Plugin Directory] Review in Progress: Acme: Forms', '🔌 Acme: Forms' ),
			'trademarks'         => array( '[WordPress Plugin Directory] Closure Notice - Licensing/Trademark Violation: Acme Forms', '🔌™️ Acme Forms' ),
			'guidelines'         => array( '[WordPress Plugin Directory] Closure Notice - Guideline Violation: Acme Forms', '🔌📜 Acme Forms' ),
			'notice'             => array( '[WordPress Plugin Directory] Notice: Acme Forms', '🔌ℹ️ Acme Forms' ),
			'security'           => array( '[WordPress Plugin Directory] Security Notice: Acme Forms', '🔌🛡️ Acme Forms' ),
			'rejection'          => array( '[WordPress Plugin Directory] Rejection Explanation: Acme Forms', '🔌🚫 Acme Forms' ),
		);
	}

	/**
	 * Other kinds have no mark, and the tab's title shortens them by their kind, or by their prefix alone for kinds
	 * without a short name.
	 *
	 * @dataProvider data_other_subjects
	 *
	 * @param string $subject Subject.
	 * @param string $short   Its short version.
	 * @return void
	 */
	public function test_shortens_other_plugin_directory_subjects( string $subject, string $short ): void {
		$this->assertSame( '', Subject::marked( $subject ) );
		$this->assertSame( $short, Subject::short( $subject ) );
	}

	/**
	 * Subjects, and their short versions.
	 *
	 * @return array[]
	 */
	public static function data_other_subjects(): array {
		return array(
			'other closure'     => array( '[WordPress Plugin Directory] Closure Notice - Author Request: Acme: Forms', 'Closed (Author Request): Acme: Forms' ),
			'unknown kind'      => array( 'Re: [WordPress Plugin Directory] Approved: Acme Forms', 'Approved: Acme Forms' ),
			'unknown, no colon' => array( '[WordPress Plugin Directory] Added to Block Directory - Acme Forms', 'Added to Block Directory - Acme Forms' ),
		);
	}

	/**
	 * Other subjects aren't shortened.
	 *
	 * @return void
	 */
	public function test_leaves_other_subjects_alone(): void {
		$this->assertSame( '', Subject::short( 'Question about Acme Forms' ) );
		$this->assertSame( '', Subject::marked( 'Review in Progress: Acme Forms' ) );
		$this->assertSame( '', Subject::short( '' ) );
	}
}
