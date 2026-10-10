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
	 * Subjects are shortened by their kind, or by their prefix alone for kinds without a short name; a short subject never
	 * holds the full one, which the tab's title relies on to switch between them.
	 *
	 * @dataProvider data_subjects
	 *
	 * @param string $subject Subject.
	 * @param string $short   Its short version.
	 * @return void
	 */
	public function test_shortens_the_plugin_directorys_subjects( string $subject, string $short ): void {
		$this->assertSame( $short, Subject::short( $subject ) );
		$this->assertStringNotContainsString( $subject, $short );
	}

	/**
	 * Subjects, and their short versions.
	 *
	 * @return array[]
	 */
	public static function data_subjects(): array {
		return array(
			'review in progress'  => array( '[WordPress Plugin Directory] Review in Progress: Acme Forms', 'R: Acme Forms' ),
			'closure with reason' => array( '[WordPress Plugin Directory] Closure Notice - Trademarks: Acme: Forms', 'Closed (Trademarks): Acme: Forms' ),
			'reply'               => array( 'Re: RE: [WordPress Plugin Directory] Rejection Explanation: Acme Forms', 'Rejected: Acme Forms' ),
			'unknown kind'        => array( '[WordPress Plugin Directory] Notice: Acme Forms', 'Notice: Acme Forms' ),
			'unknown, no colon'   => array( '[WordPress Plugin Directory] Added to Block Directory - Acme Forms', 'Added to Block Directory - Acme Forms' ),
		);
	}

	/**
	 * Other subjects aren't shortened.
	 *
	 * @return void
	 */
	public function test_leaves_other_subjects_alone(): void {
		$this->assertSame( '', Subject::short( 'Question about Acme Forms' ) );
		$this->assertSame( '', Subject::short( '' ) );
	}
}
