<?php
/**
 * Tests the checks on anonymous video submissions.
 *
 * @package WordPressTV
 */

declare( strict_types = 1 );

namespace WordPressTV\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function WordPressTV\Submissions\validate;

/**
 * Anyone can submit a video, so the server checks what the form's JavaScript does.
 */
class Submissions_Test extends TestCase {

	/**
	 * Clears the submitted data.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * Submissions, with the error code each gets.
	 *
	 * @return array[]
	 */
	public static function data_submissions(): array {
		$valid = array(
			'wptv_honey'       => '',
			'wptv_uploaded_by' => 'Jane Doe',
			'wptv_email'       => 'jane@example.org',
			'wptv_video_title' => 'Building Block Themes',
		);

		return array(
			'valid'                  => array( $valid, false ),
			'filled honeypot'        => array( array_merge( $valid, array( 'wptv_honey' => 'https://spam.example' ) ), 13 ),
			'title over 100 letters' => array( array_merge( $valid, array( 'wptv_video_title' => str_repeat( 'é', 101 ) ) ), 6 ),
			'no name'                => array( array_merge( $valid, array( 'wptv_uploaded_by' => '' ) ), 10 ),
			'invalid email'          => array( array_merge( $valid, array( 'wptv_email' => 'jane' ) ), 12 ),
		);
	}

	/**
	 * An anonymous submission is rejected with the code the form shows.
	 *
	 * @param array     $post     Submitted fields.
	 * @param int|false $expected Error code, or false when the submission is valid.
	 * @return void
	 */
	#[DataProvider( 'data_submissions' )]
	public function test_anonymous_submission_is_validated( array $post, int|false $expected ): void {
		$_POST = wp_slash( $post );

		$this->assertSame( $expected, validate() );
	}
}
