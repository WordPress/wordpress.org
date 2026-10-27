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
		delete_option( Jobs_Dot_WP_Digest::LAST_RUN_OPTION );

		parent::tearDown();
	}

	/**
	 * Creates a job the way moderation does: submitted as a draft, then published.
	 *
	 * Published two minutes ago, since a digest window stops a minute short of the run.
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
		$published = time() - 2 * MINUTE_IN_SECONDS;
		wp_update_post(
			array(
				'ID'            => $job_id,
				'post_status'   => 'publish',
				'post_date'     => wp_date( 'Y-m-d H:i:s', $published ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $published ),
				'edit_date'     => true,
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
	 * The digest is filed under Job Alerts and under the mirror of each job category it lists.
	 *
	 * Catches the digest missing a job category's mirror, which would never
	 * email that category's subscribers.
	 *
	 * @return void
	 */
	public function test_files_digest_under_job_category_mirrors(): void {
		$this->publish_job( 'WordPress Developer' );

		Jobs_Dot_WP_Digest::publish_digests();

		$digests = $this->get_digests();
		$this->assertCount( 1, $digests );

		$slugs = wp_list_pluck( get_the_category( $digests[0]->ID ), 'slug' );
		sort( $slugs );
		$this->assertSame( array( 'job-alerts', 'job-alerts-digest-test-development' ), $slugs );
	}

	/**
	 * Job titles come from anonymous visitors and must stay text in the digest.
	 *
	 * Catches the bracket encoding being dropped, which would run shortcodes
	 * from a job title on the site and in the email.
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
