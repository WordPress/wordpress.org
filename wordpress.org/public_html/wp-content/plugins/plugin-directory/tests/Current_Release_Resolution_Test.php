<?php
/**
 * Tests for resolving the release a hold applies to.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that a Version header that doesn't match its tag cannot orphan the
 * release's block or cooldown: the hold is resolved from the stable tag, the
 * source of the served package, not from the header's version label.
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
class Current_Release_Resolution_Test extends TestCase {

	/** The version served by the update_source row fixture. */
	private const SERVED_VERSION = '1.0.0';

	/** The tag of the held release. */
	private const HELD_TAG = '1.4.4';

	/** The mismatched Version header committed into the held tag. */
	private const RENAMED_VERSION = '1.4.5';

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
	 * Create a published plugin whose held tag carries a mismatched Version header.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		// Tools::audit_log() reads it unguarded.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'resolution-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Release Resolution Test Plugin',
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

		update_post_meta( $this->plugin->ID, 'version', self::RENAMED_VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', self::HELD_TAG );
	}

	/**
	 * Register a release for a tag.
	 *
	 * @param string $tag       The release tag.
	 * @param string $version   The release version.
	 * @param array  $overrides Fields to override.
	 */
	private function add_release( string $tag, string $version, array $overrides = array() ): void {
		Plugin_Directory::add_release(
			$this->plugin,
			array_merge(
				array(
					'tag'                      => $tag,
					'version'                  => $version,
					'zips_built'               => true,
					'zips_built_from_revision' => 0,
					'confirmed'                => true,
					'confirmations_required'   => 0,
					'release_delay'            => DAY_IN_SECONDS,
				),
				$overrides
			)
		);
	}

	/**
	 * The version currently in the plugin's update_source row.
	 *
	 * @return string The served version.
	 */
	private function served_version(): string {
		return API_Update_Updater::get_served_version( $this->plugin->post_name );
	}

	/**
	 * A renamed Version header inside a tag under cooldown keeps the hold.
	 */
	public function test_renamed_header_keeps_cooldown_hold(): void {
		$this->add_release( self::HELD_TAG, self::RENAMED_VERSION );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$this->assertSame( self::SERVED_VERSION, $this->served_version() );
		$this->assertNotFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * A renamed Version header inside a blocked tag keeps the block's hold.
	 */
	public function test_renamed_header_keeps_block_hold(): void {
		$this->add_release(
			self::HELD_TAG,
			self::RENAMED_VERSION,
			array(
				'release_block' => array(
					'scan_id'    => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
					'risk_score' => 9.8,
					'blocked_at' => time(),
				),
			)
		);

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$this->assertSame( self::SERVED_VERSION, $this->served_version() );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * A header renamed to another, clean release's version still resolves the
	 * hold from the stable tag serving the package.
	 */
	public function test_header_renamed_to_other_release_version_keeps_hold(): void {
		$this->add_release( '2.0', '2.0', array( 'release_delay' => 0 ) );
		$this->add_release(
			self::HELD_TAG,
			self::RENAMED_VERSION,
			array(
				'release_block' => array( 'blocked_at' => time() ),
			)
		);
		update_post_meta( $this->plugin->ID, 'version', '2.0' );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$this->assertSame( self::SERVED_VERSION, $this->served_version() );
	}

	/**
	 * A scan-driven block lands on the stable tag's release despite the rename.
	 */
	public function test_block_release_records_block_on_stable_tag_release(): void {
		$this->add_release( self::HELD_TAG, self::RENAMED_VERSION );

		$this->assertTrue( API_Update_Updater::block_release( $this->plugin->post_name, array( 'risk_score' => 9.8 ) ) );

		$release = Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::HELD_TAG );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $release ) );
	}

	/**
	 * A reviewer force-release lifts the hold on the stable tag's release and
	 * serves the version despite the rename.
	 */
	public function test_force_release_serves_renamed_version(): void {
		$this->add_release(
			self::HELD_TAG,
			self::RENAMED_VERSION,
			array(
				'release_block' => array( 'blocked_at' => time() ),
			)
		);

		$this->assertTrue( API_Update_Updater::force_release( $this->plugin->post_name, 'Reviewed; findings are a false positive.' ) );

		$this->assertSame( self::RENAMED_VERSION, $this->served_version() );
	}

	/**
	 * Trunk releases keep resolving by version: their rows are keyed
	 * `trunk@{version}` and there is no stable tag release to prefer.
	 */
	public function test_trunk_release_still_resolves_by_version(): void {
		update_post_meta( $this->plugin->ID, 'stable_tag', 'trunk' );
		$this->add_release( 'trunk@' . self::RENAMED_VERSION, self::RENAMED_VERSION );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$this->assertSame( self::SERVED_VERSION, $this->served_version() );
		$this->assertNotFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * A tagged plugin resolves strictly by its stable tag: a release row that
	 * exists only for the header version is not a fallback.
	 */
	public function test_tagged_plugin_ignores_version_release_row(): void {
		$this->add_release( self::RENAMED_VERSION, self::RENAMED_VERSION, array( 'release_block' => array( 'blocked_at' => time() ) ) );

		$this->assertFalse( API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) ) );
	}

	/**
	 * The block's already-served guard compares the resolved release's version,
	 * not the header: a header renamed to equal the served version cannot make
	 * a different, unserved release look already-live and refuse the block.
	 */
	public function test_block_release_guard_uses_resolved_release_version(): void {
		update_post_meta( $this->plugin->ID, 'version', self::SERVED_VERSION );
		$this->add_release( self::HELD_TAG, '2.0' );

		$this->assertTrue( API_Update_Updater::block_release( $this->plugin->post_name, array( 'risk_score' => 9.8 ) ) );

		$release = Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::HELD_TAG );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $release ) );
	}

	/**
	 * The force-release audit note names the release actually unblocked — the
	 * stable-tag-resolved one — not the plugin's Version header.
	 */
	public function test_force_release_audit_log_names_resolved_release(): void {
		update_post_meta( $this->plugin->ID, 'version', '9.9.9' );
		$this->add_release( self::HELD_TAG, '7.7.7', array( 'release_block' => array( 'blocked_at' => time() ) ) );

		$this->assertTrue( API_Update_Updater::force_release( $this->plugin->post_name, 'Reviewed.' ) );

		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);
		$this->assertNotEmpty( $notes );
		$this->assertStringContainsString( 'version 7.7.7', $notes[0]->comment_content );
		$this->assertStringNotContainsString( '9.9.9', $notes[0]->comment_content );
	}
}
