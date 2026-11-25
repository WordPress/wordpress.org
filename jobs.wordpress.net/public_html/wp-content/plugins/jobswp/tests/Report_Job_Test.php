<?php
/**
 * Tests for the "Report this job" link and contact form prefill.
 *
 * @package jobswp
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `Jobs_Dot_WP_Contact_Form::get_report_subject()` and the shortcode prefill.
 */
#[Group( 'jobswp' )]
class Report_Job_Test extends TestCase {

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	private array $post_ids = array();

	/**
	 * Global state saved in setUp and restored on teardown.
	 *
	 * @var array<string, mixed>
	 */
	private array $saved_globals = array();

	/**
	 * Saves the globals the shortcode test replaces.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->saved_globals = array(
			'post'     => $GLOBALS['post'] ?? null,
			'wp_query' => $GLOBALS['wp_query'] ?? null,
		);
	}

	/**
	 * Removes created posts and restores request globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_GET[ Jobs_Dot_WP_Contact_Form::REPORT_QUERY_VAR ] );

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		$GLOBALS['post']     = $this->saved_globals['post'];
		$GLOBALS['wp_query'] = $this->saved_globals['wp_query'];

		parent::tearDown();
	}

	/**
	 * Creates a post and tracks it for cleanup.
	 *
	 * @param array<string, mixed> $args Post arguments overriding the defaults.
	 * @return int Post ID.
	 */
	private function create_post( array $args = array() ): int {
		$post_id = wp_insert_post(
			array_merge(
				array(
					'post_type'    => 'job',
					'post_status'  => 'publish',
					'post_title'   => 'Senior WordPress Developer',
					'post_content' => 'Description.',
				),
				$args
			),
			true
		);
		$this->assertIsInt( $post_id, 'The post could not be created.' );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * A published job yields the expected subject.
	 *
	 * @return void
	 */
	public function test_published_job_subject(): void {
		$id = $this->create_post();

		$this->assertSame(
			sprintf( 'Reporting - Senior WordPress Developer (%d)', $id ),
			Jobs_Dot_WP_Contact_Form::get_report_subject( $id )
		);
	}

	/**
	 * A draft job yields no subject.
	 *
	 * @return void
	 */
	public function test_draft_job_has_no_subject(): void {
		$id = $this->create_post( array( 'post_status' => 'draft' ) );

		$this->assertSame( '', Jobs_Dot_WP_Contact_Form::get_report_subject( $id ) );
	}

	/**
	 * A non-job post yields no subject.
	 *
	 * @return void
	 */
	public function test_non_job_has_no_subject(): void {
		$id = $this->create_post( array( 'post_type' => 'post' ) );

		$this->assertSame( '', Jobs_Dot_WP_Contact_Form::get_report_subject( $id ) );
	}

	/**
	 * A nonexistent ID yields no subject.
	 *
	 * @return void
	 */
	public function test_nonexistent_job_has_no_subject(): void {
		$this->assertSame( '', Jobs_Dot_WP_Contact_Form::get_report_subject( 999999 ) );
	}

	/**
	 * A very long title is truncated so the subject fits the form's 200 character limit.
	 *
	 * @return void
	 */
	public function test_long_title_fits_subject_limit(): void {
		$id      = $this->create_post( array( 'post_title' => str_repeat( 'A', 300 ) ) );
		$subject = Jobs_Dot_WP_Contact_Form::get_report_subject( $id );

		$this->assertNotSame( '', $subject );
		$this->assertLessThanOrEqual( 200, mb_strlen( $subject ) );
		// The form validates with strlen() (bytes), so the limit must hold there too.
		$this->assertLessThanOrEqual( 200, strlen( $subject ) );
	}

	/**
	 * A long multibyte title still fits the byte-based limit, without broken characters.
	 *
	 * @return void
	 */
	public function test_long_multibyte_title_fits_subject_limit(): void {
		$id      = $this->create_post( array( 'post_title' => str_repeat( 'ワードプレス', 50 ) ) );
		$subject = Jobs_Dot_WP_Contact_Form::get_report_subject( $id );

		$this->assertStringStartsWith( 'Reporting - ワ', $subject );
		$this->assertLessThanOrEqual( 200, strlen( $subject ) );
		$this->assertTrue( mb_check_encoding( $subject, 'UTF-8' ) );
	}

	/**
	 * Apostrophes and ampersands stay plain text rather than becoming HTML entities.
	 *
	 * @return void
	 */
	public function test_title_stays_plain_text(): void {
		$id      = $this->create_post( array( 'post_title' => "Dev's Role & Support" ) );
		$subject = Jobs_Dot_WP_Contact_Form::get_report_subject( $id );

		$this->assertSame( sprintf( "Reporting - Dev's Role & Support (%d)", $id ), $subject );
		$this->assertStringNotContainsString( '&#', $subject );
		$this->assertStringNotContainsString( '&amp;', $subject );
	}

	/**
	 * The report URL points at the feedback page with the job ID.
	 *
	 * @return void
	 */
	public function test_report_url(): void {
		$url = Jobs_Dot_WP_Contact_Form::get_report_url( 8 );

		$this->assertStringContainsString( '/feedback/', $url );
		$this->assertStringContainsString( 'report_job=8', $url );
	}

	/**
	 * The contact form prefills its subject field from the report query var.
	 *
	 * @return void
	 */
	public function test_shortcode_prefills_subject(): void {
		$job_id  = $this->create_post( array( 'post_title' => "Dev's Role & Support" ) );
		$page_id = $this->create_post(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Feedback',
				'post_content' => '[jobswp-contact-form]',
			)
		);

		$query = new WP_Query( array( 'page_id' => $page_id ) );
		$query->get_posts();
		$GLOBALS['wp_query'] = $query;
		$GLOBALS['post']     = get_post( $page_id );

		$_GET[ Jobs_Dot_WP_Contact_Form::REPORT_QUERY_VAR ] = (string) $job_id;

		$html = Jobs_Dot_WP_Contact_Form::shortcode();

		$expected = sprintf( "Reporting - Dev's Role & Support (%d)", $job_id );
		$this->assertStringContainsString( 'value="' . esc_attr( $expected ) . '"', $html );

		$_GET[ Jobs_Dot_WP_Contact_Form::REPORT_QUERY_VAR ] = '999999';
		$this->assertStringNotContainsString( 'Reporting -', Jobs_Dot_WP_Contact_Form::shortcode() );
	}
}
