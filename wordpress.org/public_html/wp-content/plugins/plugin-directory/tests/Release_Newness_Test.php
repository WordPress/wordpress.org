<?php
/**
 * Tests that update_source newness is ref-aware, not version-only.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that a new release is recognised from a change to the served version OR
 * the served ref, so a new ref carrying the same version still passes through
 * the cooldown and block gates instead of shipping immediately.
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
class Release_Newness_Test extends TestCase {

	/** The version served by the update_source row fixture and the new tag. */
	private const VERSION = '2.0';

	/** The ref the update_source row serves — content differs from the new tag's. */
	private const SERVED_REF = 'trunk';

	/** The new tag carrying the same version as the served ref. */
	private const NEW_TAG = '2.0';

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
	 * Create a published plugin whose stable tag has flipped to a new ref at the
	 * same version the update_source row already serves.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		// Tools::audit_log() reads it unguarded.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'newness-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Release Newness Test Plugin',
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

		update_post_meta( $this->plugin->ID, 'version', self::VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', self::NEW_TAG );
	}

	/**
	 * Insert an update_source row serving a version from a ref.
	 *
	 * @param string $version    The served version.
	 * @param string $stable_tag The served ref.
	 */
	private function insert_served_row( string $version, string $stable_tag ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'        => $this->plugin->ID,
				'plugin_slug'      => $this->plugin->post_name,
				'available'        => 1,
				'version'          => $version,
				'stable_tag'       => $stable_tag,
				'plugin_name'      => $this->plugin->post_title,
				'requires_plugins' => '',
				'last_updated'     => $this->plugin->post_modified,
			)
		);
	}

	/**
	 * Register a release for the new tag.
	 *
	 * @param array $overrides Fields to override.
	 */
	private function add_new_tag_release( array $overrides = array() ): void {
		update_post_meta(
			$this->plugin->ID,
			'releases',
			array(
				array_merge(
					array(
						'date'                     => time(),
						'tag'                      => self::NEW_TAG,
						'version'                  => self::VERSION,
						'zips_built'               => true,
						'zips_built_from_revision' => 0,
						'confirmations'            => array(),
						'confirmed'                => true,
						'confirmations_required'   => 0,
						'committer'                => array(),
						'revision'                 => array(),
						'release_delay'            => DAY_IN_SECONDS,
					),
					$overrides
				),
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
				"SELECT version, stable_tag FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s",
				$this->plugin->post_name
			)
		);
	}

	/**
	 * A new ref at the already-served version is held by the cooldown, not
	 * written straight into the row.
	 */
	public function test_new_ref_same_version_defers_under_cooldown(): void {
		$this->insert_served_row( self::VERSION, self::SERVED_REF );
		$this->add_new_tag_release();

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $this->get_row();
		$this->assertSame( self::SERVED_REF, $row->stable_tag );
		$this->assertNotFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * A new ref at the already-served version that is blocked is held out of the
	 * row, and its deferred serve is cancelled.
	 */
	public function test_new_ref_same_version_blocked_is_held(): void {
		$this->insert_served_row( self::VERSION, self::SERVED_REF );
		$this->add_new_tag_release( array( 'release_block' => array( 'blocked_at' => time() ) ) );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $this->get_row();
		$this->assertSame( self::SERVED_REF, $row->stable_tag );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * An unchanged release — same version and same ref — is not treated as new,
	 * so the cooldown does not spuriously defer it.
	 */
	public function test_unchanged_release_is_not_deferred(): void {
		$this->insert_served_row( self::VERSION, self::NEW_TAG );
		$this->add_new_tag_release();

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $this->get_row();
		$this->assertSame( self::NEW_TAG, $row->stable_tag );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}
}
