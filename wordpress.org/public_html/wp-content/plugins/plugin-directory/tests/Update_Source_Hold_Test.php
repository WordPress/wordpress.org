<?php
/**
 * Tests for update_source writes while a version is held by a cooldown or block.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that a hold — a release cooldown or a release block — defers only the
 * version bump, while status changes reach the `update_source` row immediately.
 * A cooldown expires on its own; a block lasts until the release is force-released.
 *
 * @group jobs
 */
#[Group( 'jobs' )]
class Update_Source_Hold_Test extends TestCase {

	/** The version served by the update_source row fixture. */
	private const SERVED_VERSION = '1.0.0';

	/** The newer version held by the cooldown or block. */
	private const STAGED_VERSION = '1.4.4';

	/**
	 * Counter to give every test plugin a unique slug.
	 *
	 * @var int
	 */
	private static int $plugin_count = 0;

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $plugin;

	/**
	 * Create a published plugin with a staged release in cooldown.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		// Tools::audit_log() reads it unguarded.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'update-source-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Update Source Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( \WP_Post::class, $plugin );
		$this->plugin = $plugin;

		/*
		 * The stub update_source table survives across runs — the WP test
		 * installer only drops core tables — so clear leftovers that would
		 * collide with this run's plugin ID or read as a served version.
		 */
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_id' => $this->plugin->ID ) );
		$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_slug' => $this->plugin->post_name ) );

		update_post_meta( $this->plugin->ID, 'version', self::STAGED_VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', self::STAGED_VERSION );
		update_post_meta(
			$this->plugin->ID,
			'releases',
			array(
				array(
					'date'                     => time(),
					'tag'                      => self::STAGED_VERSION,
					'version'                  => self::STAGED_VERSION,
					'zips_built'               => true,
					'zips_built_from_revision' => 0,
					'confirmations'            => array(),
					'confirmed'                => true,
					'confirmations_required'   => 0,
					'committer'                => array(),
					'revision'                 => array(),
					'release_delay'            => DAY_IN_SECONDS,
				),
			)
		);
	}

	/**
	 * Insert an update_source row serving a version.
	 *
	 * @param string $version The version the row serves.
	 */
	private function insert_served_row( string $version = self::SERVED_VERSION ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'        => $this->plugin->ID,
				'plugin_slug'      => $this->plugin->post_name,
				'available'        => 1,
				'version'          => $version,
				'stable_tag'       => $version,
				'plugin_name'      => $this->plugin->post_title,
				'requires_plugins' => '',
				'last_updated'     => $this->plugin->post_modified,
			)
		);
	}

	/**
	 * Fetch the plugin's update_source row.
	 *
	 * @return object|null The row, or null when none exists.
	 */
	private function get_row(): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT available, version, meta FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s",
				$this->plugin->post_name
			)
		);
	}

	/**
	 * Set the plugin's status, mirroring the closure meta the admin UI writes.
	 *
	 * @param string $status The new post status.
	 */
	private function set_status( string $status ): void {
		wp_update_post(
			array(
				'ID'          => $this->plugin->ID,
				'post_status' => $status,
			)
		);

		if ( in_array( $status, array( 'closed', 'disabled' ), true ) ) {
			update_post_meta( $this->plugin->ID, '_close_reason', 'security-issue' );
			update_post_meta( $this->plugin->ID, 'plugin_closed_date', current_time( 'mysql' ) );
		} else {
			delete_post_meta( $this->plugin->ID, '_close_reason' );
			delete_post_meta( $this->plugin->ID, 'plugin_closed_date' );
		}
	}

	/**
	 * Block the staged release of the plugin fixture.
	 *
	 * @return bool Whether the release was blocked.
	 */
	private function block(): bool {
		return API_Update_Updater::block_release(
			$this->plugin->post_name,
			array( 'reason' => 'High-risk release.' )
		);
	}

	/**
	 * Fetch the release record for the staged version.
	 *
	 * @return array|false The release, or false when none exists.
	 */
	private function get_release(): array|false {
		return Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::STAGED_VERSION );
	}

	/**
	 * Fetch the plugin's audit log entries as a single string.
	 *
	 * @return string The concatenated internal-note comments.
	 */
	private function get_audit_log(): string {
		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);

		return implode( ' ', wp_list_pluck( $notes, 'comment_content' ) );
	}

	/**
	 * A new version inside its cooldown stays deferred; the row keeps serving
	 * the previous version.
	 */
	public function test_version_bump_is_deferred_during_cooldown(): void {
		$this->insert_served_row();

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $this->get_row();
		$this->assertSame( '1', $row->available );
		$this->assertSame( self::SERVED_VERSION, $row->version );
		$this->assertNotFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * Closing a plugin mid-cooldown withdraws its row immediately, still on
	 * the served version.
	 */
	public function test_closure_during_cooldown_reaches_row_immediately(): void {
		$this->insert_served_row();
		$this->set_status( 'closed' );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $this->get_row();
		$this->assertSame( '0', $row->available );
		$this->assertStringContainsString( 'closed_at', (string) $row->meta );
		$this->assertSame( self::SERVED_VERSION, $row->version );
	}

	/**
	 * Disabling a plugin mid-cooldown records its closure meta while the row
	 * stays available.
	 */
	public function test_disable_during_cooldown_records_closure_meta(): void {
		$this->insert_served_row();
		$this->set_status( 'disabled' );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $this->get_row();
		$this->assertSame( '1', $row->available );
		$this->assertStringContainsString( 'closed_at', (string) $row->meta );
		$this->assertSame( self::SERVED_VERSION, $row->version );
	}

	/**
	 * Reopening a closed plugin mid-cooldown restores its row immediately;
	 * only the version bump keeps waiting for the cooldown.
	 */
	public function test_reopen_during_cooldown_restores_row(): void {
		$this->insert_served_row();

		$this->set_status( 'closed' );
		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$this->set_status( 'publish' );
		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $this->get_row();
		$this->assertSame( '1', $row->available );
		$this->assertStringNotContainsString( 'closed_at', (string) $row->meta );
		$this->assertSame( self::SERVED_VERSION, $row->version );
	}

	/**
	 * A first-ever release in cooldown has no row to sync; none is created
	 * until the cooldown expires.
	 */
	public function test_first_release_in_cooldown_creates_no_row(): void {
		$this->set_status( 'closed' );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$this->assertNull( $this->get_row() );
	}

	/**
	 * Blocking an unserved release records the hold and keeps the row on the
	 * served version, with the deferred serve cancelled.
	 */
	public function test_block_holds_unserved_version(): void {
		$this->insert_served_row();

		// Schedule the deferred serve that the block is expected to cancel.
		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );
		$this->assertNotFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );

		$this->assertTrue( $this->block() );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );

		$block = $this->get_release()['release_block'];
		$this->assertSame( 'High-risk release.', $block['reason'] );
		$this->assertNotEmpty( $block['blocked_at'] );

		$this->assertSame( self::SERVED_VERSION, API_Update_Updater::get_served_version( $this->plugin->post_name ) );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * The block, not the cooldown clock, holds the version: with the cooldown
	 * cleared, a direct write attempt still changes nothing.
	 */
	public function test_block_outlasts_cooldown(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

		// Clear the cooldown so only the block can be holding the version.
		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'           => self::STAGED_VERSION,
				'release_delay' => 0,
			)
		);

		API_Update_Updater::update_single_plugin( $this->plugin->post_name );

		$this->assertSame( self::SERVED_VERSION, API_Update_Updater::get_served_version( $this->plugin->post_name ) );
	}

	/**
	 * A first-ever release can be blocked before any row exists; none is
	 * created while the hold is in effect.
	 */
	public function test_block_holds_first_release(): void {
		$this->assertTrue( $this->block() );

		API_Update_Updater::update_single_plugin( $this->plugin->post_name );

		$this->assertSame( '', API_Update_Updater::get_served_version( $this->plugin->post_name ) );
	}

	/**
	 * A version that is already being served cannot be blocked.
	 */
	public function test_served_version_is_not_blockable(): void {
		$this->insert_served_row( self::STAGED_VERSION );

		$this->assertFalse( $this->block() );
		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
	}

	/**
	 * A served version longer than the row's varchar(128) `version` column is
	 * stored truncated; the truncated match still counts as already live.
	 */
	public function test_served_truncated_version_is_not_blockable(): void {
		$long_version = str_repeat( '1.0.', 50 ) . '0';

		$release            = $this->get_release();
		$release['tag']     = $long_version;
		$release['version'] = $long_version;

		update_post_meta( $this->plugin->ID, 'version', $long_version );
		update_post_meta( $this->plugin->ID, 'releases', array( $release ) );
		$this->insert_served_row( substr( $long_version, 0, 128 ) );

		$this->assertFalse( $this->block() );
	}

	/**
	 * A version longer than the row's varchar(128) `version` column is stored
	 * truncated; the truncated match must not read as a new version, which would
	 * defer the already-served release to a cooldown over and over.
	 */
	public function test_served_truncated_version_is_not_deferred(): void {
		$long_version = str_repeat( '1.0.', 50 ) . '0';

		$release            = $this->get_release();
		$release['tag']     = $long_version;
		$release['version'] = $long_version;

		update_post_meta( $this->plugin->ID, 'version', $long_version );
		update_post_meta( $this->plugin->ID, 'releases', array( $release ) );
		$this->insert_served_row( substr( $long_version, 0, 128 ) );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * A release without a record cannot be blocked.
	 *
	 * An empty (rather than deleted) `releases` meta keeps get_releases() from
	 * prefilling via a live SVN lookup.
	 */
	public function test_unknown_release_is_not_blockable(): void {
		update_post_meta( $this->plugin->ID, 'releases', array() );

		$this->assertFalse( $this->block() );
	}

	/**
	 * A second block on an already-held release is a no-op success — the version
	 * is held, which is what the caller asked for — preserving the first block.
	 */
	public function test_existing_block_is_not_replaced(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

		$second = API_Update_Updater::block_release(
			$this->plugin->post_name,
			array( 'reason' => 'Another reason.' )
		);

		$this->assertTrue( $second );
		$this->assertSame( 'High-risk release.', $this->get_release()['release_block']['reason'] );
	}

	/**
	 * A status change while a release is held still reaches the row.
	 */
	public function test_status_change_reaches_row_while_blocked(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

		$this->set_status( 'closed' );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $this->get_row();
		$this->assertSame( '0', $row->available );
		$this->assertStringContainsString( 'closed_at', (string) $row->meta );
		$this->assertSame( self::SERVED_VERSION, $row->version );
	}

	/**
	 * A force-release clears the block, serves the version, and logs that a
	 * block was lifted — the only trace left once the block record is deleted.
	 * With the cooldown already cleared, the log claims no cooldown bypass.
	 */
	public function test_force_release_clears_block(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

		// Clear the cooldown so the block is the only thing being lifted.
		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'           => self::STAGED_VERSION,
				'release_delay' => 0,
			)
		);

		$this->assertTrue( API_Update_Updater::force_release( $this->plugin->post_name, 'Reviewed; false positive.' ) );

		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
		$this->assertSame( self::STAGED_VERSION, API_Update_Updater::get_served_version( $this->plugin->post_name ) );

		$audit_log = $this->get_audit_log();
		$this->assertStringContainsString( 'lifting the release block', $audit_log );
		$this->assertStringNotContainsString( 'release cooldown', $audit_log );
	}

	/**
	 * Force-releasing a version that is both blocked and still in cooldown lifts
	 * both holds in one go: the version is served and the log records both.
	 */
	public function test_force_release_clears_block_and_cooldown(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

		$this->assertTrue( API_Update_Updater::force_release( $this->plugin->post_name, 'Reviewed; false positive.' ) );

		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
		$this->assertSame( self::STAGED_VERSION, API_Update_Updater::get_served_version( $this->plugin->post_name ) );

		$this->assertStringContainsString(
			'lifting the release block and bypassing the 24-hour release cooldown',
			$this->get_audit_log()
		);
	}
}
