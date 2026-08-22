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
use WordPressdotorg\Plugin_Directory\Shortcodes\Release_Confirmation;

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
		return (string) ( API_Update_Updater::get_served_release( $this->plugin->post_name )->version ?? '' );
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
	 * A release row for the header version is a fallback only: it resolves when
	 * the stable tag has no row (the gates would otherwise fail open), and never
	 * overrides the stable tag's own release.
	 */
	public function test_version_release_row_is_fallback_only(): void {
		$this->add_release( self::RENAMED_VERSION, self::RENAMED_VERSION, array( 'release_block' => array( 'blocked_at' => time() ) ) );

		$release = API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) );
		$this->assertSame( self::RENAMED_VERSION, $release['tag'] );

		$this->add_release( self::HELD_TAG, self::RENAMED_VERSION );

		$release = API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) );
		$this->assertSame( self::HELD_TAG, $release['tag'] );
	}

	/**
	 * Flipping the stable tag to trunk at an unchanged version creates no
	 * trunk@{version} release row; the hold on the version-named release must
	 * keep the gates closed rather than orphan into a fail-open miss.
	 */
	public function test_trunk_flip_at_same_version_keeps_hold(): void {
		update_post_meta( $this->plugin->ID, 'version', self::RENAMED_VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', 'trunk' );
		$this->add_release( self::RENAMED_VERSION, self::RENAMED_VERSION, array( 'release_block' => array( 'blocked_at' => time() ) ) );

		$release = API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) );
		$this->assertSame( self::RENAMED_VERSION, $release['tag'] );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $release ) );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );
		$this->assertSame( self::SERVED_VERSION, $this->served_version() );
	}

	/**
	 * The fallback also survives the combination of both evasions: a renamed
	 * header inside the held tag, then a stable-tag flip to trunk.
	 */
	public function test_trunk_flip_with_renamed_header_keeps_hold(): void {
		update_post_meta( $this->plugin->ID, 'stable_tag', 'trunk' );
		$this->add_release( self::HELD_TAG, self::RENAMED_VERSION, array( 'release_block' => array( 'blocked_at' => time() ) ) );

		$release = API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) );
		$this->assertSame( self::HELD_TAG, $release['tag'] );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $release ) );
	}

	/**
	 * The block's already-served guard keys on the resolved release's identity,
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
	 * A header renamed down to the already-served version cannot write the
	 * blocked tag into update_source: the block gate no longer keys on the
	 * header-derived is_new_version proxy.
	 */
	public function test_block_held_when_header_matches_served_version(): void {
		global $wpdb;

		update_post_meta( $this->plugin->ID, 'version', self::SERVED_VERSION );
		$this->add_release( self::HELD_TAG, self::SERVED_VERSION, array( 'release_block' => array( 'blocked_at' => time() ) ) );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT version, stable_tag FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s", $this->plugin->post_name ) );
		$this->assertSame( self::SERVED_VERSION, $row->version );
		$this->assertSame( self::SERVED_VERSION, $row->stable_tag );
	}

	/**
	 * The already-served guard doesn't read an empty served version and an empty
	 * resolved version as a match, which would wrongly refuse the block.
	 */
	public function test_block_not_refused_for_empty_versions(): void {
		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_slug' => $this->plugin->post_name ) );
		$this->add_release( self::HELD_TAG, '' );

		$this->assertTrue( API_Update_Updater::block_release( $this->plugin->post_name, array( 'risk_score' => 9.8 ) ) );
		$this->assertTrue( API_Update_Updater::is_release_blocked( Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::HELD_TAG ) ) );
	}

	/**
	 * A tag that is numerically equal but textually different to the stable tag
	 * does not resolve: the match is strict, not wp_list_filter's loose ==.
	 */
	public function test_numeric_collision_tag_not_matched(): void {
		update_post_meta( $this->plugin->ID, 'stable_tag', '1.4' );
		$this->add_release( '1.40', '1.40', array( 'release_block' => array( 'blocked_at' => time() ) ) );

		$this->assertFalse( API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) ) );
	}

	/**
	 * A dormant SVN tag named like the trunk version does not shadow the
	 * `trunk@{version}` release the trunk plugin actually serves.
	 */
	public function test_trunk_version_not_shadowed_by_like_named_tag(): void {
		update_post_meta( $this->plugin->ID, 'stable_tag', 'trunk' );
		update_post_meta( $this->plugin->ID, 'version', '1.2.3' );
		$this->add_release( '1.2.3', '1.2.3', array( 'release_block' => array( 'blocked_at' => time() ) ) );
		$this->add_release( 'trunk@1.2.3', '1.2.3' );

		$release = API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) );
		$this->assertSame( 'trunk@1.2.3', $release['tag'] );
		$this->assertFalse( API_Update_Updater::is_release_blocked( $release ) );
	}

	/**
	 * A re-commit into the already-served tag cannot be blocked, even when it
	 * bumps the header: the guard compares the ref the row serves, not the
	 * version label, so it can't claim to hold a package that already shipped.
	 */
	public function test_block_refused_for_recommit_into_served_tag(): void {
		update_post_meta( $this->plugin->ID, 'stable_tag', self::SERVED_VERSION );
		update_post_meta( $this->plugin->ID, 'version', '1.0.1' );
		$this->add_release( self::SERVED_VERSION, '1.0.1' );

		$this->assertFalse( API_Update_Updater::block_release( $this->plugin->post_name, array( 'risk_score' => 9.8 ) ) );
		$this->assertFalse( API_Update_Updater::is_release_blocked( Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::SERVED_VERSION ) ) );
	}

	/**
	 * Trunk-stable rows carry no per-release ref, so the already-served guard
	 * falls back to their identity — the version — and still refuses the block.
	 */
	public function test_block_refused_for_served_trunk_release(): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'update_source',
			array( 'stable_tag' => 'trunk' ),
			array( 'plugin_slug' => $this->plugin->post_name )
		);
		update_post_meta( $this->plugin->ID, 'stable_tag', 'trunk' );
		update_post_meta( $this->plugin->ID, 'version', self::SERVED_VERSION );
		$this->add_release( 'trunk@' . self::SERVED_VERSION, self::SERVED_VERSION );

		$this->assertFalse( API_Update_Updater::block_release( $this->plugin->post_name, array( 'risk_score' => 9.8 ) ) );
		$this->assertFalse( API_Update_Updater::is_release_blocked( Plugin_Directory::get_release( get_post( $this->plugin->ID ), 'trunk@' . self::SERVED_VERSION ) ) );
	}

	/**
	 * The block's write lands on the exact tag: add_release() must not merge it
	 * onto a numerically-equal different release ('1.4' == '1.40') and delete
	 * the genuine row in the process.
	 */
	public function test_block_write_lands_on_exact_tag_not_numeric_equal(): void {
		update_post_meta( $this->plugin->ID, 'stable_tag', '1.4' );
		$this->add_release( '1.40', '1.40', array( 'date' => time() ) );
		$this->add_release( '1.4', '1.4', array( 'date' => time() - DAY_IN_SECONDS ) );

		$this->assertTrue( API_Update_Updater::block_release( $this->plugin->post_name, array( 'risk_score' => 9.8 ) ) );

		$releases = array_column( Plugin_Directory::get_releases( $this->plugin ), null, 'tag' );
		$this->assertSame( '1.4', $releases['1.4']['version'] );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $releases['1.4'] ) );
		$this->assertSame( '1.40', $releases['1.40']['version'] );
		$this->assertFalse( API_Update_Updater::is_release_blocked( $releases['1.40'] ) );
	}

	/**
	 * Without a version_date, the release clock anchors on the release row's own
	 * date: post_modified would slide on unrelated post edits and re-arm cooldown
	 * displays for an already-served release.
	 */
	public function test_release_time_anchors_on_release_date_without_version_date(): void {
		$release = array(
			'date'                   => time() - WEEK_IN_SECONDS,
			'confirmations_required' => 0,
			'confirmations'          => array(),
		);

		$this->assertSame( $release['date'], API_Update_Updater::compute_release_time( get_post( $this->plugin->ID ), $release ) );
	}

	/**
	 * A version_date on the plugin outranks the release row's date as the clock anchor.
	 */
	public function test_release_time_prefers_version_date(): void {
		$version_time = time() - DAY_IN_SECONDS;
		update_post_meta( $this->plugin->ID, 'version_date', gmdate( 'Y-m-d H:i:s', $version_time ) );

		$release = array(
			'date'                   => time() - WEEK_IN_SECONDS,
			'confirmations_required' => 0,
			'confirmations'          => array(),
		);

		$this->assertSame( $version_time, API_Update_Updater::compute_release_time( get_post( $this->plugin->ID ), $release ) );
	}

	/**
	 * The releases listing shows the cooldown line only for the current release:
	 * a superseded row is never served, so the plugin-wide version_date anchor
	 * must not re-display a pending serve time on old rows after a new commit.
	 */
	public function test_cooldown_line_only_for_current_release(): void {
		update_post_meta( $this->plugin->ID, 'version_date', gmdate( 'Y-m-d H:i:s' ) );
		$this->add_release( self::HELD_TAG, self::RENAMED_VERSION );
		$this->add_release( '1.0', '1.0', array( 'date' => time() - WEEK_IN_SECONDS ) );

		$releases = array_column( Plugin_Directory::get_releases( $this->plugin ), null, 'tag' );
		$plugin   = get_post( $this->plugin->ID );

		$this->assertStringContainsString( 'Will be served', Release_Confirmation::get_approval_text( $plugin, $releases[ self::HELD_TAG ] ) );
		$this->assertStringNotContainsString( 'Will be served', Release_Confirmation::get_approval_text( $plugin, $releases['1.0'] ) );
	}

	/**
	 * The listing's cooldown line follows the same resolution as the rest of the
	 * UI: with the stable tag flipped to trunk at an unchanged version, the
	 * fallback-resolved release is the current one and keeps its line.
	 */
	public function test_cooldown_line_follows_fallback_resolution(): void {
		update_post_meta( $this->plugin->ID, 'version', self::RENAMED_VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', 'trunk' );
		$this->add_release( self::RENAMED_VERSION, self::RENAMED_VERSION );

		$releases = array_column( Plugin_Directory::get_releases( $this->plugin ), null, 'tag' );

		$this->assertStringContainsString( 'Will be served', Release_Confirmation::get_approval_text( get_post( $this->plugin->ID ), $releases[ self::RENAMED_VERSION ] ) );
	}

	/**
	 * Empty version and stable_tag metas resolve no release and must not make
	 * the row write error or defer.
	 */
	public function test_empty_metas_resolve_false_and_still_update(): void {
		update_post_meta( $this->plugin->ID, 'version', '' );
		update_post_meta( $this->plugin->ID, 'stable_tag', '' );
		$this->add_release( '1.0', '1.0', array( 'date' => time() - WEEK_IN_SECONDS ) );

		$this->assertFalse( API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) ) );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * A legacy plugin without releases meta gets it prefilled from the tags
	 * meta, resolves by stable tag, and its old release doesn't defer the row.
	 */
	public function test_prefilled_legacy_releases_resolve_by_stable_tag(): void {
		update_post_meta( $this->plugin->ID, 'version', '1.0' );
		update_post_meta( $this->plugin->ID, 'stable_tag', '1.0' );
		delete_post_meta( $this->plugin->ID, 'releases' );
		update_post_meta(
			$this->plugin->ID,
			'tags',
			array(
				'1.0' => array(
					'tag'    => '1.0',
					'date'   => '2020-01-01',
					'author' => 'testuser',
				),
			)
		);

		$release = API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) );
		$this->assertSame( '1.0', $release['tag'] );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );
		$this->assertSame( '1.0', $this->served_version() );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * An old release whose cooldown window has long elapsed doesn't defer the
	 * row write: resolving more releases than before must not re-hold legacy
	 * version bumps.
	 */
	public function test_elapsed_cooldown_on_old_release_does_not_defer(): void {
		$this->add_release( self::HELD_TAG, self::RENAMED_VERSION, array( 'date' => time() - WEEK_IN_SECONDS ) );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$this->assertSame( self::RENAMED_VERSION, $this->served_version() );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );
	}

	/**
	 * Legacy rows can carry integer-typed tags (written before the string
	 * cast); resolution and the strict add_release() merge handle them.
	 */
	public function test_legacy_integer_tag_resolves_and_merges_strictly(): void {
		update_post_meta( $this->plugin->ID, 'version', '2.0' );
		update_post_meta( $this->plugin->ID, 'stable_tag', '2' );
		update_post_meta(
			$this->plugin->ID,
			'releases',
			array(
				array(
					'date'                     => time() - WEEK_IN_SECONDS,
					'tag'                      => 2,
					'version'                  => '2.0',
					'zips_built'               => true,
					'zips_built_from_revision' => 0,
					'confirmations'            => array(),
					'confirmed'                => true,
					'confirmations_required'   => 0,
					'committer'                => array(),
					'revision'                 => array(),
					'release_delay'            => 0,
				),
			)
		);

		$release = API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) );
		$this->assertSame( '2.0', $release['version'] );

		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'           => '2',
				'release_block' => array( 'blocked_at' => time() ),
			)
		);

		$releases = Plugin_Directory::get_releases( $this->plugin );
		$this->assertCount( 1, $releases );
		$this->assertTrue( API_Update_Updater::is_release_blocked( reset( $releases ) ) );
	}

	/**
	 * A tag that isn't the version string (e.g. `v2.0`) resolves by the stable
	 * tag; the old version-keyed lookup couldn't find these at all.
	 */
	public function test_non_version_tag_name_resolves(): void {
		update_post_meta( $this->plugin->ID, 'version', '2.0' );
		update_post_meta( $this->plugin->ID, 'stable_tag', 'v2.0' );
		$this->add_release( 'v2.0', '2.0' );

		$release = API_Update_Updater::get_current_release( get_post( $this->plugin->ID ) );
		$this->assertSame( 'v2.0', $release['tag'] );
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
