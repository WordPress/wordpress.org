<?php
/**
 * Tests for the daily job digest emailed through Jetpack Subscriptions.
 *
 * @package jobswp
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `Jobs_Dot_WP_Digest::publish_digests()`.
 */
#[Group( 'jobswp' )]
class Digest_Test extends TestCase {

	/**
	 * Job category the test jobs are filed under.
	 *
	 * @var WP_Term
	 */
	private WP_Term $job_category;

	/**
	 * IDs of jobs created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	private array $job_ids = array();

	/**
	 * Creates a job category and registers a shortcode whose output shows it ran.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$term = wp_insert_term( 'Development', 'job_category', array( 'slug' => 'digest-test-development' ) );
		$this->assertIsArray( $term, 'The job category could not be created.' );
		$this->job_category = get_term( $term['term_id'], 'job_category' );

		add_shortcode( 'jobswp_test', static fn (): string => 'SHORTCODE RAN' );
	}

	/**
	 * Removes the jobs, digests and categories a test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->job_ids as $job_id ) {
			wp_delete_post( $job_id, true );
		}
		$this->job_ids = array();

		foreach ( $this->get_digests() as $digest ) {
			wp_delete_post( $digest->ID, true );
		}

		$category = get_term_by( 'slug', Jobs_Dot_WP_Digest::CATEGORY, 'category' );
		if ( $category ) {
			$children = get_terms(
				array(
					'taxonomy'   => 'category',
					'parent'     => $category->term_id,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			foreach ( $children as $child_id ) {
				wp_delete_term( $child_id, 'category' );
			}
			wp_delete_term( $category->term_id, 'category' );
		}

		wp_delete_term( $this->job_category->term_id, 'job_category' );
		remove_shortcode( 'jobswp_test' );
		delete_option( 'wpcom_newsletter_categories_enabled' );
		delete_option( Jobs_Dot_WP_Digest::LAST_RUN_OPTION );

		parent::tearDown();
	}

	/**
	 * Creates a job the way moderation does: submitted as a draft, then published.
	 *
	 * @param string $title Job title.
	 * @return int Job ID.
	 */
	private function publish_job( string $title ): int {
		$job_id = wp_insert_post(
			array(
				'post_type'    => 'job',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => 'Description.',
			)
		);
		$this->assertIsInt( $job_id );
		$this->job_ids[] = $job_id;

		wp_set_object_terms( $job_id, array( $this->job_category->term_id ), 'job_category' );
		add_post_meta( $job_id, 'company', 'Acme' );
		wp_update_post(
			array(
				'ID'          => $job_id,
				'post_status' => 'publish',
			)
		);

		return $job_id;
	}

	/**
	 * Returns the digest posts that exist.
	 *
	 * @return WP_Post[]
	 */
	private function get_digests(): array {
		return get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'category_name'  => Jobs_Dot_WP_Digest::CATEGORY,
			)
		);
	}

	/**
	 * A published job goes into exactly one digest, listed under its category.
	 *
	 * Catches the window no longer starting where the last digest ended, which
	 * would email the same jobs to subscribers again.
	 *
	 * @return void
	 */
	public function test_publishes_each_job_in_one_digest(): void {
		$job_id = $this->publish_job( 'WordPress Developer' );

		Jobs_Dot_WP_Digest::publish_digests();
		Jobs_Dot_WP_Digest::publish_digests();

		$digests = $this->get_digests();
		$this->assertCount( 1, $digests );
		$this->assertStringStartsWith( '1 new job, ', $digests[0]->post_title );
		$this->assertStringContainsString(
			"<h2>Development</h2>\n<ul>\n<li><a href=\"" . esc_url( get_permalink( $job_id ) ) . '">WordPress Developer</a> &mdash; Acme</li>',
			$digests[0]->post_content
		);
	}

	/**
	 * With newsletter categories on, a job category's digest is filed only under its mirror.
	 *
	 * Catches the per-category digest landing in the Job Alerts category, which
	 * would email subscribers of all jobs a second copy of every job.
	 *
	 * @return void
	 */
	public function test_publishes_category_digest_under_its_mirror(): void {
		update_option( 'wpcom_newsletter_categories_enabled', true );
		$this->publish_job( 'WordPress Developer' );

		Jobs_Dot_WP_Digest::publish_digests();

		$filed_under = array();
		foreach ( $this->get_digests() as $digest ) {
			$filed_under[ $digest->post_title ] = wp_list_pluck( get_the_category( $digest->ID ), 'slug' );
		}
		ksort( $filed_under );

		$date = wp_date( get_option( 'date_format' ) );
		$this->assertSame(
			array(
				"1 new Development job, $date" => array( 'job-alerts-digest-test-development' ),
				"1 new job, $date"             => array( 'job-alerts' ),
			),
			$filed_under
		);
	}

	/**
	 * Job titles come from anonymous visitors and must stay text in the digest.
	 *
	 * Catches the store-time bracket encoding being dropped or moved ahead of
	 * kses, which would run shortcodes from a job title on the site and in the email.
	 *
	 * @return void
	 */
	public function test_job_title_is_not_parsed_as_markup(): void {
		$this->publish_job( "[jobswp_test] Developer\nhttps://example.org/apply" );

		Jobs_Dot_WP_Digest::publish_digests();

		$digests = $this->get_digests();
		$this->assertCount( 1, $digests );

		$content = $digests[0]->post_content;
		$this->assertStringContainsString( '&#91;jobswp_test] Developer https://example.org/apply</a>', $content );
		$this->assertStringNotContainsString( 'SHORTCODE RAN', do_shortcode( $content ) );
	}
}
