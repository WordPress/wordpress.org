<?php
/**
 * Tests for Import::record_release().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that an import records the release its stable ref now serves, so the
 * update-source writer has a release, carrying a fresh date, to hold. The case
 * that matters is a stable tag flipped to trunk at an unchanged version: it
 * used to record no `trunk@{version}` release, so the current version resolved
 * through the old tag's stale row and the cooldown read as expired.
 *
 * Extends the plain PHPUnit TestCase for the same reasons as
 * Current_Release_Resolution_Test: WP_UnitTestCase is incompatible with the
 * PHPUnit 11 runner, and per-test isolation comes from a unique plugin post.
 *
 * @group jobs
 */
#[Group( 'jobs' )]
class Import_Record_Release_Test extends TestCase {

	/** The version and tag the update_source row fixture serves. */
	private const SERVED_VERSION = '1.0.0';

	/** The committer recorded on the release. */
	private const COMMITTER = 'committer';

	/** The revision recorded on the release. */
	private const REVISION = 42;

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
	 * Create a published plugin served from a tag, with the tag's release a week old.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'record-release-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Record Release Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( \WP_Post::class, $plugin );
		$this->plugin = $plugin;

		// The stub update_source table survives across runs; clear leftovers that would collide with this plugin.
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

		update_post_meta( $this->plugin->ID, 'version', self::SERVED_VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', self::SERVED_VERSION );
		update_post_meta( $this->plugin->ID, 'version_date', gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) );

		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'                      => self::SERVED_VERSION,
				'version'                  => self::SERVED_VERSION,
				'date'                     => time() - WEEK_IN_SECONDS,
				'zips_built'               => true,
				'zips_built_from_revision' => 0,
				'confirmed'                => true,
				'confirmations_required'   => 0,
				'release_delay'            => DAY_IN_SECONDS,
			)
		);
	}

	/**
	 * Record a release as the importer would, with the previous stable tag still in meta.
	 *
	 * @param string $stable_tag          The stable tag being imported.
	 * @param string $version             The Version header being imported.
	 * @param string $previous_stable_tag The stable tag before this import.
	 */
	private function record( string $stable_tag, string $version, string $previous_stable_tag ): void {
		Import::record_release( $this->plugin, $stable_tag, $version, $previous_stable_tag, self::COMMITTER, self::REVISION );
	}

	/**
	 * Fetch a release by its exact tag.
	 *
	 * @param string $tag The release tag.
	 * @return array|false The release, or false when none exists.
	 */
	private function release( string $tag ): array|false {
		return Plugin_Directory::get_release( get_post( $this->plugin->ID ), $tag );
	}

	/**
	 * A stable tag flipped to trunk at an unchanged version records a fresh
	 * trunk release: the served code changes even though the version doesn't.
	 */
	public function test_trunk_flip_at_unchanged_version_records_trunk_release(): void {
		$this->record( 'trunk', self::SERVED_VERSION, self::SERVED_VERSION );

		$release = $this->release( 'trunk@' . self::SERVED_VERSION );
		$this->assertIsArray( $release );
		$this->assertSame( self::SERVED_VERSION, $release['version'] );
		$this->assertSame( array( self::COMMITTER ), $release['committer'] );
		$this->assertSame( array( self::REVISION ), $release['revision'] );
		$this->assertEqualsWithDelta( time(), $release['date'], 5 );
	}

	/**
	 * A trunk re-import at an unchanged version records nothing: trunk was
	 * already stable, so there is no new release to serve.
	 */
	public function test_trunk_reimport_at_unchanged_version_records_nothing(): void {
		$this->record( 'trunk', self::SERVED_VERSION, 'trunk' );

		$this->assertFalse( $this->release( 'trunk@' . self::SERVED_VERSION ) );
	}

	/**
	 * A version bump released from trunk still records its trunk release.
	 */
	public function test_trunk_version_bump_records_trunk_release(): void {
		$this->record( 'trunk', '1.1.0', 'trunk' );

		$release = $this->release( 'trunk@1.1.0' );
		$this->assertIsArray( $release );
		$this->assertSame( '1.1.0', $release['version'] );
	}

	/**
	 * A tagged stable release still records the tag.
	 */
	public function test_tag_records_tag_release(): void {
		$this->record( '1.4.4', '1.4.4', self::SERVED_VERSION );

		$release = $this->release( '1.4.4' );
		$this->assertIsArray( $release );
		$this->assertSame( '1.4.4', $release['version'] );
		$this->assertSame( array( self::COMMITTER ), $release['committer'] );
	}

	/**
	 * End to end: the flip's fresh trunk release is what the update-source
	 * writer holds, despite the stale plugin-wide version_date.
	 */
	public function test_trunk_flip_at_unchanged_version_is_held(): void {
		$cooldown = static function (): int {
			return DAY_IN_SECONDS;
		};
		add_filter( 'wporg_plugins_release_cooldown_delay', $cooldown );

		$this->record( 'trunk', self::SERVED_VERSION, self::SERVED_VERSION );

		remove_filter( 'wporg_plugins_release_cooldown_delay', $cooldown );

		// The importer sets the new stable tag live after recording the release.
		update_post_meta( $this->plugin->ID, 'stable_tag', 'trunk' );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$served = API_Update_Updater::get_served_release( $this->plugin->post_name );
		$this->assertSame( self::SERVED_VERSION, $served->stable_tag );
		$this->assertNotFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}
}
