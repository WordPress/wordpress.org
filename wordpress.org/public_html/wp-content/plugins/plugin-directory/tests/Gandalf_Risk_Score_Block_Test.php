<?php
/**
 * Tests for the Gandalf risk-score block: holding a high-risk version out of `update_source`
 * until a reviewer force-releases it, and the cases where there's nothing to hold.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * Covers Plugin_Scan_Gandalf::handle_callback()'s blocking path and its interaction with the
 * release delay and the reviewer force-release.
 *
 * @group jobs
 */
class Gandalf_Risk_Score_Block_Test extends Gandalf_Scan_Test_Case {

	/**
	 * The plugin slug under test.
	 */
	const SLUG = 'gandalf-block-test';

	/**
	 * A score at the threshold holds the version: the previous one keeps being served,
	 * the block is recorded, and any deferred serve is cancelled.
	 */
	public function test_a_blocking_score_holds_the_version() {
		$this->markTestSkipped( 'Auto-blocking on a risk score is disabled; see Plugin_Scan_Gandalf::handle_callback().' );

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 8 ) ) );

		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
		$this->assertSame( 8.0, $this->get_release_block()['risk_score'] );
		$this->assertSame( self::SCAN_ID, $this->get_release_block()['scan_id'] );
		$this->assertFalse( wp_next_scheduled( 'release_to_update_api:' . self::SLUG ) );
	}

	/**
	 * A score above the threshold blocks just the same.
	 */
	public function test_a_score_above_the_threshold_holds_the_version() {
		$this->markTestSkipped( 'Auto-blocking on a risk score is disabled; see Plugin_Scan_Gandalf::handle_callback().' );

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 9.5 ) ) );

		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
		$this->assertNotNull( $this->get_release_block() );
	}


	/**
	 * A score below the threshold is not a block; the release follows its normal delay.
	 */
	public function test_a_score_below_the_threshold_does_not_block() {
		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 7.9 ) ) );

		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * An absent risk_score — every scan until Gandalf sends one — never blocks.
	 */
	public function test_an_absent_risk_score_does_not_block() {
		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data() );

		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A non-numeric risk_score is ignored rather than read as a block.
	 */
	public function test_a_non_numeric_risk_score_does_not_block() {
		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 'high' ) ) );

		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A reviewer force-release overrides the block: the held version is served and the block cleared.
	 */
	public function test_force_release_clears_the_block_and_serves() {
		$this->markTestSkipped( 'Auto-blocking on a risk score is disabled; see Plugin_Scan_Gandalf::handle_callback().' );

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 8 ) ) );

		$result = API_Update_Updater::force_release( self::SLUG, 'Reviewed the scan; false positive.' );

		$this->assertTrue( $result );
		$this->assertNull( $this->get_release_block() );
		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}

	/**
	 * The override is recorded, naming the block it bypassed.
	 */
	public function test_force_release_records_the_override_in_the_audit_log() {
		$this->markTestSkipped( 'Auto-blocking on a risk score is disabled; see Plugin_Scan_Gandalf::handle_callback().' );

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 8 ) ) );

		API_Update_Updater::force_release( self::SLUG, 'Reviewed the scan; false positive.' );

		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);

		$override = array_filter(
			$notes,
			function ( $note ) {
				return false !== strpos( $note->comment_content, 'overriding the security-scan block' );
			}
		);

		$this->assertCount( 1, $override );
	}

	/**
	 * When the version is already live — the delay elapsed before the verdict arrived — a
	 * blocking score can't un-ship it: `update_source` is left untouched and nothing is held.
	 */
	public function test_a_blocking_score_leaves_an_already_served_version_untouched() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->update(
			$wpdb->prefix . 'update_source',
			array( 'version' => self::NEW_VERSION ),
			array( 'plugin_slug' => self::SLUG )
		);

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 8 ) ) );

		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A verdict on a version that a newer commit has already superseded does not hold anything.
	 */
	public function test_a_blocking_score_for_a_superseded_version_does_not_block() {
		update_post_meta( $this->plugin->ID, 'version', '3.0' );

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 8 ) ) );

		$this->assertNull( $this->get_release_block() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * With no delay captured at release creation, the version was served at import, so there's
	 * nothing left to hold.
	 */
	public function test_a_blocking_score_does_not_block_a_release_that_had_no_delay() {
		$this->set_releases( array( $this->release( array( 'release_delay' => 0 ) ) ) );

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 8 ) ) );

		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A held version stays held across the reconciliation cron that would otherwise serve it.
	 */
	public function test_a_held_version_is_not_served_by_a_later_update_run() {
		$this->markTestSkipped( 'Auto-blocking on a risk score is disabled; see Plugin_Scan_Gandalf::handle_callback().' );

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 8 ) ) );

		// The delay has since elapsed; without the block this would serve 2.0.
		$this->elapse_cooldown();

		API_Update_Updater::update_single_plugin( self::SLUG );

		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}
}
