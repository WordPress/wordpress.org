<?php
/**
 * Tests for update_source writes during a release cooldown.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that a release cooldown defers only the version bump, while status
 * changes reach the `update_source` row immediately.
 *
 * Extends the plain PHPUnit TestCase: WP_UnitTestCase is not compatible with
 * the PHPUnit 11 runner used by this suite. Isolation comes from giving every
 * test its own plugin post instead of per-test transactions.
 *
 * The group is declared as an attribute as well as `@group`: PHPUnit 11 ignores
 * a class-level `@group` docblock, while older runners ignore the attribute.
 *
 * @group jobs
 */
#[Group( 'jobs' )]
class Update_Source_Cooldown_Test extends TestCase {

	/** The version served by the update_source row fixture. */
	private const SERVED_VERSION = '1.0.0';

	/** The newer version still inside its release cooldown. */
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

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'cooldown-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Cooldown Sync Test Plugin',
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
	 * Insert an update_source row serving the previous version.
	 */
	private function insert_served_row(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'        => $this->plugin->ID,
				'plugin_slug'      => $this->plugin->post_name,
				'available'        => 1,
				'version'          => self::SERVED_VERSION,
				'stable_tag'       => self::SERVED_VERSION,
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
}
