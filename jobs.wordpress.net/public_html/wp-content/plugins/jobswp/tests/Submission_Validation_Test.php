<?php
/**
 * Tests validation of public job submissions.
 *
 * @package jobswp
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the supported "How to Apply" methods.
 */
#[Group( 'jobswp' )]
class Submission_Validation_Test extends TestCase {

	/**
	 * Clears request data left by a submission.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Phone applications are not offered on the public job form.
	 *
	 * @return void
	 */
	public function test_job_form_does_not_offer_phone_applications(): void {
		ob_start();
		require dirname( __DIR__, 3 ) . '/themes/jobswp/content-post-job.php';
		$form = ob_get_clean();

		$this->assertIsString( $form );
		$this->assertStringNotContainsString( 'value="phone"', $form );
		$this->assertStringContainsString( 'value="email"', $form );
		$this->assertStringContainsString( 'value="web"', $form );
	}

	/**
	 * A forged submission cannot use phone as its application method.
	 *
	 * @return void
	 */
	public function test_phone_application_submission_is_rejected(): void {
		$title = 'Phone application job ' . uniqid();

		$_POST = wp_slash(
			array(
				'postjob'           => '1',
				'verify'            => '1',
				'accept'            => '1',
				'first_name'        => 'Rose',
				'last_name'         => 'Carter',
				'email'             => 'rose@example.org',
				'company'           => 'Example Corp',
				'howtoapply_method' => 'phone',
				'howtoapply'        => '555-0100',
				'job_title'         => $title,
				'category'          => 'development',
				'jobtype'           => 'ft',
				'job_description'   => 'Description.',
			)
		);

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'jobswppostjob' );

		Jobs_Dot_WP::get_instance()->save_job();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- save_job() records why it refused the nonce-verified submission here.
		$errors = wp_unslash( $_POST['errors'] ?? false );

		$this->assertSame(
			'The provided "How to Apply" method is not valid.',
			$errors
		);
		$this->assertSame(
			array(),
			get_posts(
				array(
					'post_type'   => 'job',
					'post_status' => 'any',
					'title'       => $title,
				)
			)
		);
	}
}
