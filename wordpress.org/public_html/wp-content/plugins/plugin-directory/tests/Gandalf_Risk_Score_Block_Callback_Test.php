<?php
/**
 * Full-chain tests for the risk-score block: a scan callback arrives over the REST route,
 * `plugins/v1/plugin/{slug}/gandalf-scan`, and the release is held from sites or not.
 *
 * The route harness (fixture, POST helper, and the route-level authentication tests) lives in
 * Gandalf_Callback_Test_Case; this covers what a risk-score verdict does end to end. The
 * handler's finer branches are covered directly in Gandalf_Risk_Score_Block_Test.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * Covers what a risk-score verdict, delivered over the API, does to a release waiting out its delay.
 *
 * @group jobs
 */
class Gandalf_Risk_Score_Block_Callback_Test extends Gandalf_Callback_Test_Case {

	/**
	 * The block recorded against the scanned release, if any.
	 *
	 * @return array|null The `release_block` value, or null when the release isn't held.
	 */
	protected function get_release_block() {
		foreach ( (array) get_post_meta( $this->plugin->ID, 'releases', true ) as $release ) {
			if ( static::NEW_VERSION === $release['tag'] ) {
				return $release['release_block'] ?? null;
			}
		}

		return null;
	}

	/**
	 * The headline behaviour: Gandalf reports a blocking risk score over the API, and the
	 * version it scanned is held back — the previous one keeps being served — and recorded.
	 */
	public function test_a_blocking_score_over_the_api_holds_the_version() {
		$response = $this->post_callback( $this->callback_body( array( 'risk_score' => Plugin_Scan_Gandalf::RISK_SCORE_BLOCK_THRESHOLD ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( static::SERVED_VERSION, $this->get_served_version() );
		$this->assertSame( (float) Plugin_Scan_Gandalf::RISK_SCORE_BLOCK_THRESHOLD, $this->get_release_block()['risk_score'] );
		$this->assertSame( static::SCAN_ID, $this->get_release_block()['scan_id'] );
	}

	/**
	 * The block outlasts the cooldown: once the delay elapses, the reconciliation run that
	 * would normally serve the new version still leaves the old one in place.
	 */
	public function test_a_held_version_is_not_served_when_its_delay_later_elapses() {
		$this->post_callback( $this->callback_body( array( 'risk_score' => 9 ) ) );

		// Age the commit past the delay; without the block this run would serve NEW_VERSION.
		update_post_meta( $this->plugin->ID, 'version_date', gmdate( 'Y-m-d H:i:s', time() - ( static::DELAY * 2 ) ) );
		API_Update_Updater::update_single_plugin( static::SLUG );

		$this->assertSame( static::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * A score below the threshold is not a block; the release is left to its normal delay.
	 */
	public function test_a_score_below_the_threshold_over_the_api_does_not_block() {
		$response = $this->post_callback( $this->callback_body( array( 'risk_score' => Plugin_Scan_Gandalf::RISK_SCORE_BLOCK_THRESHOLD - 1 ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * An absent risk_score — every scan until Gandalf sends one — never blocks.
	 */
	public function test_an_absent_risk_score_over_the_api_does_not_block() {
		$response = $this->post_callback( $this->callback_body() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A blocking score for a version that is already live can't un-ship it: `update_source`
	 * is left untouched and nothing is held.
	 */
	public function test_a_blocking_score_for_an_already_served_version_is_left_untouched() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->update(
			$wpdb->prefix . 'update_source',
			array( 'version' => static::NEW_VERSION ),
			array( 'plugin_slug' => static::SLUG )
		);

		$response = $this->post_callback( $this->callback_body( array( 'risk_score' => 9 ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( static::NEW_VERSION, $this->get_served_version() );
		$this->assertNull( $this->get_release_block() );
	}

	/**
	 * A reviewer force-release closes the loop: a version blocked over the API is served, and
	 * the block cleared.
	 */
	public function test_force_release_after_an_api_block_serves_the_version() {
		$this->post_callback( $this->callback_body( array( 'risk_score' => 9 ) ) );

		$result = API_Update_Updater::force_release( static::SLUG, 'Reviewed the scan; false positive.' );

		$this->assertTrue( $result );
		$this->assertNull( $this->get_release_block() );
		$this->assertSame( static::NEW_VERSION, $this->get_served_version() );
	}
}
