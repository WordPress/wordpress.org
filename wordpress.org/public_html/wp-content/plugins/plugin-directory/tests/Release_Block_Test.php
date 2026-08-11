<?php
/**
 * Tests for the release block API.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that a blocked release is held out of `update_source` until it is
 * force-released, while the previously served version keeps being served.
 *
 * @group jobs
 */
#[Group( 'jobs' )]
class Release_Block_Test extends TestCase {

	/** The version served by the update_source row fixture. */
	private const SERVED_VERSION = '1.0.0';

	/** The newer version the block is recorded against. */
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
				'post_name'   => 'release-block-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Release Block Test Plugin',
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
	private function get_release() {
		return Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::STAGED_VERSION );
	}

	/**
	 * Blocking an unserved release records the hold and keeps the row on the
	 * served version, with the deferred serve cancelled.
	 */
	public function test_block_holds_unserved_version(): void {
		$this->insert_served_row();

		$this->assertTrue( $this->block() );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );

		$block = $this->get_release()['release_block'];
		$this->assertSame( 'High-risk release.', $block['reason'] );
		$this->assertNotEmpty( $block['blocked_at'] );

		$this->assertSame( self::SERVED_VERSION, API_Update_Updater::get_served_version( $this->plugin->post_name ) );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * The block, not the cooldown clock, holds the version: a direct write
	 * attempt changes nothing.
	 */
	public function test_block_outlasts_cooldown(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

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
	 * A release without a record cannot be blocked.
	 */
	public function test_unknown_release_is_not_blockable(): void {
		delete_post_meta( $this->plugin->ID, 'releases' );

		$this->assertFalse( $this->block() );
	}

	/**
	 * A second block on an already-held release is refused, preserving the first.
	 */
	public function test_existing_block_is_not_replaced(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

		$second = API_Update_Updater::block_release(
			$this->plugin->post_name,
			array( 'reason' => 'Another reason.' )
		);

		$this->assertFalse( $second );
		$this->assertSame( 'High-risk release.', $this->get_release()['release_block']['reason'] );
	}

	/**
	 * A status change while a release is held still reaches the row.
	 */
	public function test_status_change_reaches_row_while_blocked(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

		wp_update_post(
			array(
				'ID'          => $this->plugin->ID,
				'post_status' => 'closed',
			)
		);
		update_post_meta( $this->plugin->ID, '_close_reason', 'security-issue' );
		update_post_meta( $this->plugin->ID, 'plugin_closed_date', current_time( 'mysql' ) );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT available, version, meta FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s", $this->plugin->post_name ) );

		$this->assertSame( '0', $row->available );
		$this->assertStringContainsString( 'closed_at', (string) $row->meta );
		$this->assertSame( self::SERVED_VERSION, $row->version );
	}

	/**
	 * A force-release clears the block and serves the version.
	 */
	public function test_force_release_clears_block(): void {
		$this->insert_served_row();
		$this->assertTrue( $this->block() );

		$this->assertTrue( API_Update_Updater::force_release( $this->plugin->post_name, 'Reviewed; false positive.' ) );

		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
		$this->assertSame( self::STAGED_VERSION, API_Update_Updater::get_served_version( $this->plugin->post_name ) );
	}
}
