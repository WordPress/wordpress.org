<?php
/**
 * Tests for the release delay: holding a new version back from `update_source` until its
 * delay elapses, and the paths that end that wait early.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;

/**
 * Covers the lifecycle of a release under a delay.
 *
 * @group jobs
 */
class Release_Delay_Test extends TestCase {

	/**
	 * The delay captured on the release under test.
	 */
	const DELAY = DAY_IN_SECONDS;

	/**
	 * The plugin slug under test.
	 */
	const SLUG = 'release-delay-test';

	/**
	 * The version already being served when a test starts.
	 */
	const SERVED_VERSION = '1.0';

	/**
	 * The new version waiting out its delay.
	 */
	const NEW_VERSION = '2.0';

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	protected $plugin;

	/**
	 * A plugin serving 1.0, with 2.0 committed just now and sitting inside a 24-hour delay.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		// Tools::audit_log() reads this unconditionally; CLI has no remote address.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		// post_modified is required: Plugin_Directory::filter_wp_insert_post_data() copies it
		// from $postarr, and wp_insert_post() doesn't default it, so the insert fails without.
		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_name'         => self::SLUG,
				'post_title'        => 'Release Delay Test',
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
		update_post_meta( $plugin_id, 'header_name', 'Release Delay Test' );
		update_post_meta( $plugin_id, 'header_author', 'WordPress' );

		$this->set_committed_at( time() );
		$this->set_releases( array( $this->release() ) );

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
	 * A complete release row. get_releases() reads keys well beyond the ones under test,
	 * so partial rows warn rather than fail, which is easy to miss.
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
	 * Move the commit time the delay is measured from. compute_release_time() reads
	 * `version_date`, so this decides whether the window is still open.
	 *
	 * @param int $timestamp When the version was committed.
	 */
	protected function set_committed_at( $timestamp ) {
		update_post_meta( $this->plugin->ID, 'version_date', gmdate( 'Y-m-d H:i:s', $timestamp ) );
	}

	/**
	 * Put a version into `update_source`, standing in for a previous release.
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
	 * The delay currently stored against a release tag.
	 *
	 * @param string $tag The release tag.
	 * @return int|null Delay in seconds, or null when the tag isn't found.
	 */
	protected function get_stored_delay( $tag ) {
		foreach ( (array) get_post_meta( $this->plugin->ID, 'releases', true ) as $release ) {
			if ( $tag === $release['tag'] ) {
				return $release['release_delay'];
			}
		}

		return null;
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
	 * The previous version keeps being served while the new one waits.
	 */
	public function test_holds_back_a_version_that_is_still_inside_its_delay() {
		API_Update_Updater::update_single_plugin( self::SLUG );

		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * The deferred write is scheduled for the moment the delay runs out.
	 */
	public function test_schedules_the_held_version_to_be_served_when_its_delay_elapses() {
		$committed_at = time();
		$this->set_committed_at( $committed_at );

		API_Update_Updater::update_single_plugin( self::SLUG );

		$this->assertSame(
			$committed_at + self::DELAY,
			wp_next_scheduled( 'release_to_update_api:' . self::SLUG )
		);
	}

	/**
	 * Once the window has passed the version is served and the schedule is cleared.
	 */
	public function test_serves_a_version_whose_delay_has_elapsed() {
		$this->set_committed_at( time() - ( self::DELAY * 2 ) );

		API_Update_Updater::update_single_plugin( self::SLUG );

		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
		$this->assertFalse( wp_next_scheduled( 'release_to_update_api:' . self::SLUG ) );
	}

	/**
	 * With the delay disabled, releases are served as they were before it existed.
	 */
	public function test_serves_a_version_that_has_no_delay_at_all() {
		$this->set_releases( array( $this->release( array( 'release_delay' => 0 ) ) ) );

		API_Update_Updater::update_single_plugin( self::SLUG );

		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}

	/**
	 * Ending the wait early serves the version and zeroes the delay it skipped.
	 */
	public function test_serving_now_serves_the_version_and_clears_its_delay() {
		$result = API_Update_Updater::serve_release_now( self::SLUG, self::NEW_VERSION, 'Gandalf scan reported no findings.' );

		$this->assertTrue( $result );
		$this->assertSame( 0, $this->get_stored_delay( self::NEW_VERSION ) );
		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}

	/**
	 * Whatever ended the wait is recorded, since the delay existed for a reason.
	 */
	public function test_serving_now_records_the_reason_in_the_audit_log() {
		API_Update_Updater::serve_release_now( self::SLUG, self::NEW_VERSION, 'Gandalf scan reported no findings.' );

		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);

		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( 'Gandalf scan reported no findings.', $notes[0]->comment_content );
		$this->assertStringContainsString( '24-hour release delay', $notes[0]->comment_content );
	}

	/**
	 * The guard that matters: when a newer commit lands while a scan or review is in
	 * flight, the verdict on the older version must not skip the newer one's delay.
	 */
	public function test_serving_now_refuses_a_version_that_is_no_longer_current() {
		update_post_meta( $this->plugin->ID, 'version', '3.0' );

		$result = API_Update_Updater::serve_release_now( self::SLUG, self::NEW_VERSION, 'Gandalf scan reported no findings.' );

		$this->assertFalse( $result );
		$this->assertSame( self::DELAY, $this->get_stored_delay( self::NEW_VERSION ) );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * There's no wait to end when no delay was captured at release creation.
	 */
	public function test_serving_now_refuses_a_release_that_has_no_delay() {
		$this->set_releases( array( $this->release( array( 'release_delay' => 0 ) ) ) );

		$result = API_Update_Updater::serve_release_now( self::SLUG, self::NEW_VERSION, 'Gandalf scan reported no findings.' );

		$this->assertFalse( $result );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * An unknown slug is refused rather than treated as a missing plugin to create.
	 */
	public function test_serving_now_refuses_an_unknown_plugin() {
		$this->assertFalse( API_Update_Updater::serve_release_now( 'no-such-plugin-here', self::NEW_VERSION, 'Gandalf scan reported no findings.' ) );
	}

	/**
	 * A release is never served without both a version to check and a reason to log.
	 */
	public function test_serving_now_refuses_an_empty_version_or_reason() {
		$this->assertFalse( API_Update_Updater::serve_release_now( self::SLUG, '', 'Gandalf scan reported no findings.' ) );
		$this->assertFalse( API_Update_Updater::serve_release_now( self::SLUG, self::NEW_VERSION, '' ) );
		$this->assertSame( self::DELAY, $this->get_stored_delay( self::NEW_VERSION ) );
	}
}
