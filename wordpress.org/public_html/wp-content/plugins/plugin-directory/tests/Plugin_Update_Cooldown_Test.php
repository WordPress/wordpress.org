<?php
/**
 * Tests for the API_Update_Updater cooldown helpers.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use const WordPressdotorg\Plugin_Directory\RELEASE_COOL_DOWN_DELAY;

/**
 * Pure-logic coverage of the cooldown helpers; bypasses WP boot.
 *
 * @group jobs
 */
class Plugin_Update_Cooldown_Test extends TestCase {

	/**
	 * A version bump while the cooldown window is still open should defer the update_source write.
	 */
	public function test_defers_new_version_inside_cooldown_window() {
		$now            = 1700000000;
		$release_time   = $now - HOUR_IN_SECONDS;
		$cooldown_until = API_Update_Updater::get_cooldown_defer_time( $release_time, '1.0', '1.1', $now );

		$this->assertSame( $release_time + RELEASE_COOL_DOWN_DELAY, $cooldown_until );
	}

	/**
	 * Once the cooldown has elapsed the write should go straight through.
	 */
	public function test_does_not_defer_when_cooldown_elapsed() {
		$now          = 1700000000;
		$release_time = $now - RELEASE_COOL_DOWN_DELAY - HOUR_IN_SECONDS;

		$this->assertFalse(
			API_Update_Updater::get_cooldown_defer_time( $release_time, '1.0', '1.1', $now )
		);
	}

	/**
	 * Status changes or metadata refreshes that leave the version unchanged should not defer.
	 */
	public function test_does_not_defer_when_version_unchanged() {
		$now          = 1700000000;
		$release_time = $now - HOUR_IN_SECONDS;

		$this->assertFalse(
			API_Update_Updater::get_cooldown_defer_time( $release_time, '1.0', '1.0', $now )
		);
	}

	/**
	 * Cron-backup paths shouldn't accidentally re-defer ancient commits whose cooldown is long past.
	 */
	public function test_does_not_defer_first_release_with_old_release_time() {
		$now          = 1700000000;
		$release_time = $now - ( 7 * DAY_IN_SECONDS );

		$this->assertFalse(
			API_Update_Updater::get_cooldown_defer_time( $release_time, '', '1.0', $now )
		);
	}

	/**
	 * A brand-new plugin's first commit should still be subject to the cooldown.
	 */
	public function test_defers_first_release_with_recent_release_time() {
		$now          = 1700000000;
		$release_time = $now - 60;

		$this->assertSame(
			$release_time + RELEASE_COOL_DOWN_DELAY,
			API_Update_Updater::get_cooldown_defer_time( $release_time, '', '1.0', $now )
		);
	}

	/**
	 * Without release confirmations the commit timestamp drives release_time.
	 */
	public function test_compute_release_time_uses_version_date_by_default() {
		$post = (object) array(
			'version_date'  => '2026-05-15 12:00:00',
			'post_modified' => '2026-05-10 00:00:00',
		);

		$this->assertSame(
			strtotime( '2026-05-15 12:00:00' ),
			API_Update_Updater::compute_release_time( $post, false )
		);
	}

	/**
	 * If version_date is missing the post's last modified time is the fallback.
	 */
	public function test_compute_release_time_falls_back_to_post_modified() {
		$post = (object) array(
			'version_date'  => '',
			'post_modified' => '2026-05-10 00:00:00',
		);

		$this->assertSame(
			strtotime( '2026-05-10 00:00:00' ),
			API_Update_Updater::compute_release_time( $post, false )
		);
	}

	/**
	 * With release confirmation required, release_time is the latest committer confirmation.
	 */
	public function test_compute_release_time_uses_latest_confirmation_when_required() {
		$post = (object) array(
			'version_date'  => '2026-05-15 12:00:00',
			'post_modified' => '2026-05-10 00:00:00',
		);

		$release = array(
			'confirmations_required' => 2,
			'confirmations'          => array(
				'alice' => 1700000100,
				'bob'   => 1700000500,
			),
		);

		$this->assertSame(
			1700000500,
			API_Update_Updater::compute_release_time( $post, $release )
		);
	}

	/**
	 * Confirmations on releases that don't require them should be ignored.
	 */
	public function test_compute_release_time_ignores_confirmations_when_not_required() {
		$post = (object) array(
			'version_date'  => '2026-05-15 12:00:00',
			'post_modified' => '2026-05-10 00:00:00',
		);

		$release = array(
			'confirmations_required' => 0,
			'confirmations'          => array( 'alice' => 1700000100 ),
		);

		$this->assertSame(
			strtotime( '2026-05-15 12:00:00' ),
			API_Update_Updater::compute_release_time( $post, $release )
		);
	}
}
