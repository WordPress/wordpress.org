<?php
/**
 * Tests for the scheduled pruning of expired jobs.
 *
 * @package jobswp
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `Jobs_Dot_WP::scheduled_job_pruning()`.
 */
#[Group( 'jobswp' )]
class Pruning_Test extends TestCase {

	/**
	 * A description with markup kses strips from job descriptions, as a moderator with unfiltered_html can save it.
	 *
	 * @var string
	 */
	private const MODERATED_CONTENT = 'Apply on <a href="/apply/">our site</a>.';

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
	 * Callbacks a test hooked to the publish-to-pruned transition, removed again on teardown.
	 *
	 * @var callable[]
	 */
	private array $prune_callbacks = array();

	/**
	 * Creates a job category.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$term = wp_insert_term( 'Development', 'job_category', array( 'slug' => 'pruning-test-development' ) );
		$this->assertIsArray( $term, 'The job category could not be created.' );
		$this->job_category = get_term( $term['term_id'], 'job_category' );
	}

	/**
	 * Removes the jobs and category a test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->job_ids as $job_id ) {
			wp_delete_post( $job_id, true );
		}
		$this->job_ids = array();

		foreach ( $this->prune_callbacks as $callback ) {
			remove_action( 'publish_to_pruned', $callback );
		}
		$this->prune_callbacks = array();

		wp_delete_term( $this->job_category->term_id, 'job_category' );

		parent::tearDown();
	}

	/**
	 * Hooks a callback to the publish-to-pruned transition for the rest of the test.
	 *
	 * @param callable $callback Receives the pruned job.
	 * @return void
	 */
	private function on_prune( callable $callback ): void {
		add_action( 'publish_to_pruned', $callback );
		$this->prune_callbacks[] = $callback;
	}

	/**
	 * Creates a published job in the test category, saved the way a moderator's account saves it.
	 *
	 * @param int $age Seconds since the job was published.
	 * @return int Job ID.
	 */
	private function publish_job( int $age ): int {
		$published = time() - $age;

		kses_remove_filters();
		$job_id = wp_insert_post(
			array(
				'post_type'     => 'job',
				'post_status'   => 'publish',
				'post_title'    => 'WordPress Developer',
				'post_content'  => self::MODERATED_CONTENT,
				'post_date'     => wp_date( 'Y-m-d H:i:s', $published ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $published ),
			)
		);
		kses_init();

		$this->assertIsInt( $job_id );
		$this->job_ids[] = $job_id;

		wp_set_object_terms( $job_id, array( $this->job_category->term_id ), 'job_category' );

		return $job_id;
	}

	/**
	 * Returns the current count of the test category.
	 *
	 * @return int
	 */
	private function get_category_count(): int {
		return (int) get_term( $this->job_category->term_id, 'job_category' )->count;
	}

	/**
	 * Only jobs listed for longer than the allotted days are pruned.
	 *
	 * @return void
	 */
	public function test_prunes_only_expired_jobs(): void {
		$days    = Jobs_Dot_WP::get_days_until_pruning();
		$expired = $this->publish_job( $days * DAY_IN_SECONDS + HOUR_IN_SECONDS );
		$current = $this->publish_job( $days * DAY_IN_SECONDS - HOUR_IN_SECONDS );

		Jobs_Dot_WP::get_instance()->scheduled_job_pruning();

		$this->assertSame( 'pruned', get_post_status( $expired ) );
		$this->assertSame( 'publish', get_post_status( $current ) );
	}

	/**
	 * Pruning a job takes it out of its category's count.
	 *
	 * Catches the status being changed without the transition, which leaves
	 * expired jobs counted on the category listings.
	 *
	 * @return void
	 */
	public function test_pruned_jobs_leave_the_category_count(): void {
		$days = Jobs_Dot_WP::get_days_until_pruning();
		$this->publish_job( $days * DAY_IN_SECONDS + HOUR_IN_SECONDS );
		$this->publish_job( $days * DAY_IN_SECONDS - HOUR_IN_SECONDS );
		$this->assertSame( 2, $this->get_category_count() );

		Jobs_Dot_WP::get_instance()->scheduled_job_pruning();

		$this->assertSame( 1, $this->get_category_count() );
	}

	/**
	 * Hooks on the transition see the job as pruned.
	 *
	 * Catches the post cache not being cleaned before the transition, which
	 * hands hooks the job as it was before pruning.
	 *
	 * @return void
	 */
	public function test_transition_hooks_see_the_pruned_job(): void {
		$job_id   = $this->publish_job( ( Jobs_Dot_WP::get_days_until_pruning() + 1 ) * DAY_IN_SECONDS );
		$statuses = array();

		// Cached as published, so a cache left uncleaned would reach the hook.
		get_post( $job_id );

		$this->on_prune(
			static function ( WP_Post $post ) use ( &$statuses ): void {
				$statuses[ $post->ID ] = $post->post_status;
			}
		);

		Jobs_Dot_WP::get_instance()->scheduled_job_pruning();

		$this->assertSame( array( $job_id => 'pruned' ), $statuses );
	}

	/**
	 * Pruning leaves the job's content and modified date as they were.
	 *
	 * Catches pruning through wp_update_post(), which runs without a user's
	 * unfiltered_html and so strips markup a moderator saved.
	 *
	 * @return void
	 */
	public function test_pruning_leaves_the_job_unchanged(): void {
		$job_id = $this->publish_job( ( Jobs_Dot_WP::get_days_until_pruning() + 1 ) * DAY_IN_SECONDS );
		$before = get_post( $job_id );
		$this->assertSame( self::MODERATED_CONTENT, $before->post_content );
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );

		Jobs_Dot_WP::get_instance()->scheduled_job_pruning();

		$after = get_post( $job_id );
		$this->assertSame( 'pruned', $after->post_status );
		$this->assertSame( $before->post_content, $after->post_content );
		$this->assertSame( $before->post_modified_gmt, $after->post_modified_gmt );
	}

	/**
	 * A job closed while a run is underway is left closed and not transitioned.
	 *
	 * @return void
	 */
	public function test_skips_jobs_closed_during_the_run(): void {
		global $wpdb;

		$age          = ( Jobs_Dot_WP::get_days_until_pruning() + 1 ) * DAY_IN_SECONDS;
		$job_ids      = array( $this->publish_job( $age ), $this->publish_job( $age ) );
		$transitioned = array();

		// The first job pruned closes the other one, as a poster's removal would between the query and its update.
		$this->on_prune(
			static function ( WP_Post $post ) use ( &$transitioned, $job_ids, $wpdb ): void {
				$transitioned[] = $post->ID;

				if ( 1 === count( $transitioned ) ) {
					$other = current( array_diff( $job_ids, array( $post->ID ) ) );
					$wpdb->update( $wpdb->posts, array( 'post_status' => 'closed' ), array( 'ID' => $other ) );
					clean_post_cache( $other );
				}
			}
		);

		Jobs_Dot_WP::get_instance()->scheduled_job_pruning();

		$this->assertCount( 1, $transitioned );
		$closed = current( array_diff( $job_ids, $transitioned ) );
		$this->assertSame( 'closed', get_post_status( $closed ) );
	}

	/**
	 * A job extended while a run is underway stays published and is not transitioned.
	 *
	 * @return void
	 */
	public function test_skips_jobs_extended_during_the_run(): void {
		global $wpdb;

		$age          = ( Jobs_Dot_WP::get_days_until_pruning() + 1 ) * DAY_IN_SECONDS;
		$job_ids      = array( $this->publish_job( $age ), $this->publish_job( $age ) );
		$transitioned = array();

		// The first job pruned moves the other one's publish date to now, as extending its listing would.
		$this->on_prune(
			static function ( WP_Post $post ) use ( &$transitioned, $job_ids, $wpdb ): void {
				$transitioned[] = $post->ID;

				if ( 1 === count( $transitioned ) ) {
					$other = current( array_diff( $job_ids, array( $post->ID ) ) );
					$wpdb->update(
						$wpdb->posts,
						array(
							'post_date'     => current_time( 'mysql' ),
							'post_date_gmt' => current_time( 'mysql', true ),
						),
						array( 'ID' => $other )
					);
					clean_post_cache( $other );
				}
			}
		);

		Jobs_Dot_WP::get_instance()->scheduled_job_pruning();

		$this->assertCount( 1, $transitioned );
		$extended = current( array_diff( $job_ids, $transitioned ) );
		$this->assertSame( 'publish', get_post_status( $extended ) );
		$this->assertSame( 1, $this->get_category_count() );
	}
}
