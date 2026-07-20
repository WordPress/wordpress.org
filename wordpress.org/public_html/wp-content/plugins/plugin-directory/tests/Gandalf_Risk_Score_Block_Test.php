<?php
/**
 * Tests for the Gandalf risk-score block: holding a high-risk version out of `update_source`
 * until a reviewer force-releases it, and the cases where there's nothing to hold.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * Covers Plugin_Scan_Gandalf::handle_callback()'s blocking path and its interaction with the
 * release delay and the reviewer force-release.
 *
 * @group jobs
 */
class Gandalf_Risk_Score_Block_Test extends TestCase {

	/**
	 * The delay captured on the release under test.
	 */
	const DELAY = DAY_IN_SECONDS;

	/**
	 * The plugin slug under test.
	 */
	const SLUG = 'gandalf-block-test';

	/**
	 * The version already being served when a test starts.
	 */
	const SERVED_VERSION = '1.0';

	/**
	 * The new version waiting out its delay, and the one Gandalf scans.
	 */
	const NEW_VERSION = '2.0';

	/**
	 * The pending scan the callbacks refer to.
	 */
	const SCAN_ID = 'scan-abc-123';

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	protected $plugin;

	/**
	 * A plugin serving 1.0, with 2.0 committed just now inside a 24-hour delay, and a pending
	 * Gandalf scan of 2.0 awaiting its verdict.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		// Tools::audit_log() reads this unconditionally; CLI has no remote address.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_name'         => self::SLUG,
				'post_title'        => 'Gandalf Block Test',
				'post_status'       => 'publish',
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		$this->assertNotInstanceOf( WP_Error::class, $plugin_id );

		$this->plugin = get_post( $plugin_id );

		update_post_meta( $plugin_id, 'version', self::NEW_VERSION );
		update_post_meta( $plugin_id, 'stable_tag', self::NEW_VERSION );
		update_post_meta( $plugin_id, 'header_name', 'Gandalf Block Test' );
		update_post_meta( $plugin_id, 'header_author', 'WordPress' );
		update_post_meta( $plugin_id, 'version_date', gmdate( 'Y-m-d H:i:s', time() ) );

		$this->set_releases( array( $this->release() ) );
		$this->set_pending_scan( self::NEW_VERSION );

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}update_source`" );
		$this->serve( self::SERVED_VERSION );
	}

	/**
	 * Remove the plugin, its meta, any audit-log notes, and any deferred cron event. There's
	 * no transaction to roll back without WP_UnitTestCase, so state would otherwise leak.
	 */
	protected function tearDown(): void {
		wp_clear_scheduled_hook( 'release_to_update_api:' . self::SLUG );

		foreach ( get_comments( array( 'post_id' => $this->plugin->ID ) ) as $note ) {
			wp_delete_comment( $note->comment_ID, true );
		}

		wp_delete_post( $this->plugin->ID, true );

		parent::tearDown();
	}

	/**
	 * A complete release row. get_releases() reads keys beyond the ones under test.
	 *
	 * @param array $overrides Values to override on the default release.
	 * @return array
	 */
	protected function release( $overrides = array() ) {
		return array_merge(
			array(
				'date'                   => time(),
				'tag'                    => self::NEW_VERSION,
				'version'                => self::NEW_VERSION,
				'zips_built'             => true,
				'confirmations'          => array(),
				'confirmed'              => true,
				'confirmations_required' => 0,
				'committer'              => array(),
				'revision'               => array(),
				'release_delay'          => self::DELAY,
			),
			$overrides
		);
	}

	/**
	 * Seed the releases meta directly: get_releases() otherwise falls back to
	 * prefill_releases_meta(), which reaches out to SVN.
	 *
	 * @param array $releases The releases to store.
	 */
	protected function set_releases( $releases ) {
		update_post_meta( $this->plugin->ID, 'releases', $releases );
	}

	/**
	 * Record a pending Gandalf scan so handle_callback() recognizes the callback.
	 *
	 * @param string $version The version being scanned.
	 */
	protected function set_pending_scan( $version ) {
		update_post_meta(
			$this->plugin->ID,
			Plugin_Scan_Gandalf::PENDING_META_KEY,
			array(
				self::SCAN_ID => array(
					'version'      => $version,
					'release_ref'  => $version,
					'requested_at' => time(),
				),
			)
		);
	}

	/**
	 * Build a completed-scan callback payload for version 2.0.
	 *
	 * @param array $overrides Values to override on the default payload.
	 * @return array
	 */
	protected function callback_data( $overrides = array() ) {
		return array_merge(
			array(
				'scan_id'         => self::SCAN_ID,
				'version'         => self::NEW_VERSION,
				'release_ref'     => self::NEW_VERSION,
				'status'          => 'completed',
				'findings_count'  => 0,
				'severity_counts' => array(),
				'verdict_hash'    => 'hash',
				'report_url'      => 'https://gandalf.wordpress.org/report/abc',
			),
			$overrides
		);
	}

	/**
	 * Put a version into `update_source`, standing in for the currently-served release.
	 *
	 * @param string $version The version to serve.
	 */
	protected function serve( $version ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'    => $this->plugin->ID,
				'plugin_slug'  => self::SLUG,
				'available'    => 1,
				'version'      => $version,
				'last_updated' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * The version currently served from `update_source`.
	 *
	 * @return string|null
	 */
	protected function get_served_version() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress, and a cached read would defeat the assertion.
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `version` FROM `{$wpdb->prefix}update_source` WHERE `plugin_slug` = %s",
				self::SLUG
			)
		);
	}

	/**
	 * The block recorded against the current release, if any.
	 *
	 * @param string $tag The release tag.
	 * @return array|null The `release_block` value, or null when the release isn't held.
	 */
	protected function get_release_block( $tag = self::NEW_VERSION ) {
		foreach ( (array) get_post_meta( $this->plugin->ID, 'releases', true ) as $release ) {
			if ( $tag === $release['tag'] ) {
				return $release['release_block'] ?? null;
			}
		}

		return null;
	}

	/**
	 * A score at the threshold holds the version: the previous one keeps being served,
	 * the block is recorded, and any deferred serve is cancelled.
	 */
	public function test_a_blocking_score_holds_the_version() {
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
		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->callback_data( array( 'risk_score' => 8 ) ) );

		// The delay has since elapsed; without the block this would serve 2.0.
		update_post_meta( $this->plugin->ID, 'version_date', gmdate( 'Y-m-d H:i:s', time() - ( self::DELAY * 2 ) ) );

		API_Update_Updater::update_single_plugin( self::SLUG );

		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}
}
