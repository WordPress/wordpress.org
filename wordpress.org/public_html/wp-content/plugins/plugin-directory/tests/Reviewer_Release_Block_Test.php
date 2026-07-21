<?php
/**
 * Tests for the reviewer release block: a reviewer holding a version out of `update_source`
 * from the Controls metabox, and force-releasing it again.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;

/**
 * Covers API_Update_Updater::block_release() and its interaction with the release delay and
 * the reviewer force-release.
 *
 * @group jobs
 */
class Reviewer_Release_Block_Test extends TestCase {

	/**
	 * The delay captured on the release under test.
	 */
	const DELAY = DAY_IN_SECONDS;

	/**
	 * The plugin slug under test.
	 */
	const SLUG = 'reviewer-block-test';

	/**
	 * The version already being served when a test starts.
	 */
	const SERVED_VERSION = '1.0';

	/**
	 * The new version waiting out its delay, and the one a reviewer blocks.
	 */
	const NEW_VERSION = '2.0';

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	protected $plugin;

	/**
	 * The reviewer performing the block.
	 *
	 * @var \WP_User
	 */
	protected $reviewer;

	/**
	 * A plugin serving 1.0, with 2.0 committed just now inside a 24-hour delay.
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
				'post_title'        => 'Reviewer Block Test',
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
		update_post_meta( $plugin_id, 'header_name', 'Reviewer Block Test' );
		update_post_meta( $plugin_id, 'header_author', 'WordPress' );
		update_post_meta( $plugin_id, 'version_date', gmdate( 'Y-m-d H:i:s', time() ) );

		$this->set_releases( array( $this->release() ) );

		$reviewer_id = wp_insert_user(
			array(
				'user_login' => 'reviewer-block-user',
				'user_pass'  => 'password',
				'user_email' => 'reviewer-block-user@example.org',
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $reviewer_id );
		$this->reviewer = get_user_by( 'id', $reviewer_id );

		$wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}update_source`" );
		$this->serve( self::SERVED_VERSION );
	}

	/**
	 * Remove the plugin, its meta, the reviewer, any audit-log notes, and any deferred cron
	 * event. There's no transaction to roll back without WP_UnitTestCase, so state would leak.
	 */
	protected function tearDown(): void {
		wp_clear_scheduled_hook( 'release_to_update_api:' . self::SLUG );

		foreach ( get_comments( array( 'post_id' => $this->plugin->ID ) ) as $note ) {
			wp_delete_comment( $note->comment_ID, true );
		}

		wp_delete_post( $this->plugin->ID, true );
		wp_delete_user( $this->reviewer->ID );

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
	 * The block payload the Controls metabox sends for a reviewer block.
	 *
	 * @return array
	 */
	protected function reviewer_block() {
		return array(
			'reason'     => 'Suspicious obfuscated code.',
			'blocked_by' => $this->reviewer->user_login,
		);
	}

	/**
	 * A reviewer block holds the in-cooldown version: the previous one keeps being served, the
	 * block is recorded with the reason and reviewer, and any deferred serve is cancelled.
	 */
	public function test_a_block_holds_the_in_cooldown_version() {
		$result = API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$this->assertTrue( $result );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
		$this->assertSame( 'Suspicious obfuscated code.', $this->get_release_block()['reason'] );
		$this->assertSame( $this->reviewer->user_login, $this->get_release_block()['blocked_by'] );
		$this->assertFalse( wp_next_scheduled( 'release_to_update_api:' . self::SLUG ) );
	}

	/**
	 * The block is recorded in the audit log with the supplied reason.
	 */
	public function test_a_block_records_an_audit_note() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);

		$block_notes = array_filter(
			$notes,
			function ( $note ) {
				return false !== strpos( $note->comment_content, 'Blocked version 2.0 from being served' );
			}
		);

		$this->assertCount( 1, $block_notes );
	}

	/**
	 * A force-release lifts a reviewer block: the held version is served and the block cleared.
	 */
	public function test_force_release_clears_a_reviewer_block_and_serves() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$result = API_Update_Updater::force_release( self::SLUG, 'Reviewed with the author; resolved.' );

		$this->assertTrue( $result );
		$this->assertNull( $this->get_release_block() );
		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}

	/**
	 * The force-release over a reviewer block is recorded, without a risk score to name.
	 */
	public function test_force_release_over_a_reviewer_block_records_the_override() {
		API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		API_Update_Updater::force_release( self::SLUG, 'Reviewed with the author; resolved.' );

		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);

		$override = array_filter(
			$notes,
			function ( $note ) {
				return false !== strpos( $note->comment_content, 'overriding the release block' );
			}
		);

		$this->assertCount( 1, $override );
	}

	/**
	 * A version that's already live can't be un-shipped by a block; `update_source` is left alone.
	 */
	public function test_a_block_leaves_an_already_served_version_untouched() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->update(
			$wpdb->prefix . 'update_source',
			array( 'version' => self::NEW_VERSION ),
			array( 'plugin_slug' => self::SLUG )
		);

		$result = API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$this->assertFalse( $result );
		$this->assertNull( $this->get_release_block() );
		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}

	/**
	 * With no release row for the current version there's nothing to hold, so the block is a no-op.
	 */
	public function test_a_block_without_a_release_does_nothing() {
		update_post_meta( $this->plugin->ID, 'version', '3.0' );

		$result = API_Update_Updater::block_release( self::SLUG, $this->reviewer_block() );

		$this->assertFalse( $result );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}
}
