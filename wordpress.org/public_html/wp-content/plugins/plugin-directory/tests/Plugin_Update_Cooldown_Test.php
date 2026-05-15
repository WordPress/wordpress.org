<?php
/**
 * Tests for the API_Update_Updater::compute_release_time() helper.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;

/**
 * Pure-logic coverage of the release_time computation.
 *
 * @group jobs
 */
class Plugin_Update_Cooldown_Test extends TestCase {

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
