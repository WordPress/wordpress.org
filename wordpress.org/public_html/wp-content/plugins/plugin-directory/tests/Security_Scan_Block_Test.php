<?php
/**
 * Tests for the security scan callback release-block policy.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that completed security scan callbacks block high-risk releases.
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
class Security_Scan_Block_Test extends TestCase {

	/** The scan ID used for the pending scan fixture. */
	private const SCAN_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

	/** The version and release ref used for the pending scan fixture. */
	private const VERSION = '1.4.4';

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
	 * The block threshold filter pinning the suite to 8.0, removed on teardown.
	 *
	 * @var callable
	 */
	private $threshold_filter;

	/**
	 * Create a published plugin with a pending security scan.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		// Pin the block threshold the suite was written against; the shipped default disables blocking.
		$this->threshold_filter = static function (): float {
			return 8.0;
		};
		add_filter( 'wporg_plugins_security_scan_block_risk_score', $this->threshold_filter );

		// Tools::audit_log() reads it unguarded.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'block-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Scan Block Test Plugin',
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
		update_post_meta( $this->plugin->ID, 'stable_tag', self::VERSION );
		$this->add_pending_scan( self::SCAN_ID );
	}

	/**
	 * Remove the threshold filter the tests installed.
	 */
	protected function tearDown(): void {
		remove_filter( 'wporg_plugins_security_scan_block_risk_score', $this->threshold_filter );

		parent::tearDown();
	}

	/**
	 * Register a pending scan on the plugin fixture.
	 *
	 * @param string $scan_id The scan ID to register.
	 */
	private function add_pending_scan( string $scan_id ): void {
		$pending = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );
		$pending = is_array( $pending ) ? $pending : array();

		$pending[ $scan_id ] = array(
			'version'      => self::VERSION,
			'release_ref'  => self::VERSION,
			'requested_at' => time(),
		);

		update_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, $pending );
	}

	/**
	 * Register a release for the scanned version, still inside its cooldown window.
	 */
	private function stage_release(): void {
		update_post_meta(
			$this->plugin->ID,
			'releases',
			array(
				array(
					'date'                     => time(),
					'tag'                      => self::VERSION,
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
			)
		);
	}

	/**
	 * Stage an update_source row for a served version alongside the cooldown release.
	 *
	 * @param string $served_version The version the row serves.
	 */
	private function stage_cooldown_release( string $served_version = '1.0.0' ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'        => $this->plugin->ID,
				'plugin_slug'      => $this->plugin->post_name,
				'available'        => 1,
				'version'          => $served_version,
				'stable_tag'       => $served_version,
				'plugin_name'      => $this->plugin->post_title,
				'requires_plugins' => '',
				'last_updated'     => $this->plugin->post_modified,
			)
		);

		$this->stage_release();
	}

	/**
	 * Build a finding entry matching the callback contract.
	 *
	 * @param float $risk_score The finding risk score.
	 * @param array $overrides  Fields to override.
	 * @return array The finding.
	 */
	private function finding( float $risk_score, array $overrides = array() ): array {
		return array_merge(
			array(
				'id'            => 'finding-' . md5( (string) $risk_score ),
				'ref'           => 'prompt-security.supply_chain.remote_controlled_code',
				'title'         => 'Remote response controls a PHP callable <script>alert(1)</script>',
				'severity'      => 'error',
				'file_path'     => 'includes/class-admin.php',
				'line'          => 688,
				'code_snippet'  => '$clean = $this->write;',
				'explanation'   => 'The response body reaches a callable.',
				'risk_score'    => $risk_score,
				'investigation' => array(
					'status'  => 'completed',
					'result'  => 'reproduced',
					'summary' => 'The unauthenticated probe reached the sink.',
				),
			),
			$overrides
		);
	}

	/**
	 * Build a completed callback matching the pending scan fixture.
	 *
	 * @param array $overrides Fields to override.
	 * @return array The callback data.
	 */
	private function completed_callback( array $overrides = array() ): array {
		$defaults = array(
			'status'          => 'completed',
			'scan_id'         => self::SCAN_ID,
			'subject_type'    => 'plugin',
			'slug'            => $this->plugin->post_name,
			'version'         => self::VERSION,
			'release_ref'     => self::VERSION,
			'completed_at'    => time(),
			'verdict_hash'    => 'f71c3d944050095a4e2e20f9ee8a7c9a',
			'findings_count'  => 2,
			'findings'        => array( $this->finding( 9.8 ), $this->finding( 5.2 ) ),
			'max_risk_score'  => 9.8,
			'severity_counts' => array( 'error' => 2 ),
			'scanner_version' => '0.3.0',
			'report_url'      => 'https://scanner.example/runs/' . self::SCAN_ID,
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Build a failed callback matching the pending scan fixture.
	 *
	 * @param array $overrides Fields to override.
	 * @return array The callback data.
	 */
	private function failed_callback( array $overrides = array() ): array {
		$defaults = array(
			'status'       => 'failed',
			'scan_id'      => self::SCAN_ID,
			'subject_type' => 'plugin',
			'slug'         => $this->plugin->post_name,
			'version'      => self::VERSION,
			'release_ref'  => self::VERSION,
			'completed_at' => time(),
			'report_url'   => 'https://scanner.example/runs/' . self::SCAN_ID,
			'error'        => array(
				'kind'    => 'timeout',
				'message' => 'Scan exceeded the runtime deadline.',
			),
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Fetch the internal notes recorded on the plugin fixture.
	 *
	 * @return array The internal note comments.
	 */
	private function get_internal_notes(): array {
		return get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);
	}

	/**
	 * Fetch the release record for the scanned version.
	 *
	 * @return array|false The release, or false when none exists.
	 */
	private function get_release() {
		return Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::VERSION );
	}

	/**
	 * The version currently served from `update_source`.
	 *
	 * @return string The served version, or an empty string when none is served.
	 */
	private function get_served_version(): string {
		return (string) ( API_Update_Updater::get_served_release( $this->plugin->post_name )->version ?? '' );
	}

	/**
	 * A completed scan at the block threshold holds the release, not the plugin.
	 */
	public function test_high_risk_scan_blocks_release(): void {
		$this->stage_release();

		$result = Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() );

		$this->assertTrue( $result );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );
		$this->assertSame( 'publish', get_post( $this->plugin->ID )->post_status );

		$block = $this->get_release()['release_block'];
		$this->assertSame( self::SCAN_ID, $block['scan_id'] );
		$this->assertSame( 9.8, $block['risk_score'] );
		$this->assertNotEmpty( $block['blocked_at'] );

		$consumed = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::CONSUMED_META_KEY, true );
		$this->assertArrayHasKey( self::SCAN_ID, $consumed );

		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true ) );
	}

	/**
	 * A block leaves an internal note with the escaped findings for reviewers.
	 */
	public function test_block_leaves_findings_note(): void {
		$this->stage_release();

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() );

		$notes = $this->get_internal_notes();
		$this->assertCount( 1, $notes );

		$note = $notes[0]->comment_content;
		$this->assertStringContainsString( self::SCAN_ID, $note );
		$this->assertStringContainsString( 'Automatically blocked version ' . self::VERSION, $note );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $note );
		$this->assertStringNotContainsString( '<script>', $note );
		$this->assertStringContainsString( '<strong>9.8</strong>', $note );
		$this->assertStringContainsString( '<br>&nbsp;&nbsp;includes/class-admin.php:688', $note );
		$this->assertStringContainsString( 'https://scanner.example/runs/' . self::SCAN_ID, $note );
	}

	/**
	 * The block threshold is inclusive.
	 */
	public function test_threshold_boundary_blocks(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings_count'  => 1,
				'findings'        => array( $this->finding( 8.0 ) ),
				'max_risk_score'  => 8.0,
				'severity_counts' => array( 'error' => 1 ),
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );
	}

	/**
	 * Scans below the threshold stay advisory.
	 */
	public function test_below_threshold_is_advisory(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings_count'  => 1,
				'findings'        => array( $this->finding( 7.9 ) ),
				'max_risk_score'  => 7.9,
				'severity_counts' => array( 'error' => 1 ),
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );
		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
		$this->assertCount( 0, $this->get_internal_notes() );
	}

	/**
	 * The install base does not exempt a release from being blocked.
	 */
	public function test_high_install_count_still_blocks(): void {
		$this->stage_release();
		update_post_meta( $this->plugin->ID, 'active_installs', 50000 );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );
	}

	/**
	 * A verdict for a version that is already being served can't un-ship it.
	 */
	public function test_served_version_is_not_blocked(): void {
		$this->stage_cooldown_release( self::VERSION );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );
		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
	}

	/**
	 * A verdict for a release superseded by a newer one is moot.
	 */
	public function test_superseded_release_is_not_blocked(): void {
		$this->stage_release();

		$releases   = get_post_meta( $this->plugin->ID, 'releases', true );
		$releases[] = array_merge(
			$releases[0],
			array(
				'tag'     => '1.5.0',
				'version' => '1.5.0',
			)
		);
		update_post_meta( $this->plugin->ID, 'releases', $releases );
		update_post_meta( $this->plugin->ID, 'stable_tag', '1.5.0' );
		update_post_meta( $this->plugin->ID, 'version', '1.5.0' );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );
		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
	}

	/**
	 * Renaming the version header inside the scanned tag does not dodge the block.
	 *
	 * The header is author-controlled and the hold follows the stable tag, so a
	 * rename must not read as a release the verdict no longer applies to.
	 */
	public function test_renamed_version_header_still_blocks(): void {
		$this->stage_release();
		update_post_meta( $this->plugin->ID, 'version', '1.5.0' );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );
	}

	/**
	 * An identical retry of a consumed callback is acknowledged without repeating effects.
	 */
	public function test_identical_replay_is_acknowledged_once(): void {
		$this->stage_release();
		$callback = $this->completed_callback();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );
		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( get_post( $this->plugin->ID ), $callback ) );

		$this->assertCount( 1, $this->get_internal_notes() );
	}

	/**
	 * A different callback body for a consumed scan is rejected as a conflict.
	 */
	public function test_conflicting_replay_is_rejected(): void {
		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$conflicting = $this->completed_callback( array( 'report_url' => 'https://scanner.example/runs/other' ) );
		$result      = Plugin_Scan_Gandalf::handle_callback( get_post( $this->plugin->ID ), $conflicting );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'security_scan_conflict', $result->get_error_code() );
	}

	/**
	 * A retry that re-marshals the same callback with a different key order is
	 * acknowledged as an identical retry.
	 */
	public function test_reordered_replay_is_acknowledged(): void {
		$this->stage_release();
		$callback = $this->completed_callback();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );

		$reordered             = array_reverse( $callback, true );
		$reordered['findings'] = array_map(
			static function ( array $finding ): array {
				$finding['investigation'] = array_reverse( $finding['investigation'], true );
				return array_reverse( $finding, true );
			},
			$reordered['findings']
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( get_post( $this->plugin->ID ), $reordered ) );
		$this->assertCount( 1, $this->get_internal_notes() );
	}

	/**
	 * A completed verdict supersedes an earlier failure report for the same scan.
	 */
	public function test_completed_verdict_supersedes_failed_report(): void {
		$this->stage_release();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->failed_callback() ) );
		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( get_post( $this->plugin->ID ), $this->completed_callback() ) );

		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );

		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true ) );
	}

	/**
	 * The review note lists the ten highest-risk findings, highest first.
	 */
	public function test_note_lists_only_top_findings(): void {
		$this->stage_release();

		$findings = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$findings[] = $this->finding( round( 9.8 - ( $i / 10 ), 1 ) );
		}

		$callback = $this->completed_callback(
			array(
				'findings_count'  => 12,
				'findings'        => $findings,
				'severity_counts' => array( 'error' => 12 ),
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );

		$note = $this->get_internal_notes()[0]->comment_content;
		$this->assertSame( 10, substr_count( $note, '&#8226;' ) );
		$this->assertStringContainsString( '<strong>9.8</strong>', $note );
		$this->assertStringNotContainsString( '<strong>8.7</strong>', $note );
	}

	/**
	 * A finding carrying only the required risk_score still blocks and renders.
	 *
	 * The callback contract requires nothing else per finding; the note and
	 * alert must tolerate missing descriptive fields.
	 */
	public function test_minimal_finding_is_processed(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings_count'  => 1,
				'findings'        => array( array( 'risk_score' => 9.9 ) ),
				'max_risk_score'  => 9.9,
				'severity_counts' => array( 'error' => 1 ),
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );

		$notes = $this->get_internal_notes();
		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( '<strong>9.9</strong>', $notes[0]->comment_content );
	}

	/**
	 * The reported risk score is trusted even without findings backing it.
	 */
	public function test_score_without_findings_blocks(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings_count'  => 0,
				'findings'        => array(),
				'max_risk_score'  => 9,
				'severity_counts' => array(),
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );
		$this->assertSame( 9.0, $this->get_release()['release_block']['risk_score'] );
	}

	/**
	 * A verdict is refused when the stable tag now resolves to another release.
	 *
	 * The hold lands on whatever the stable tag points at, so a moved tag can
	 * carry the scanned version while naming a release the scan never saw.
	 */
	public function test_moved_stable_tag_is_not_blocked(): void {
		$this->stage_release();

		$releases   = get_post_meta( $this->plugin->ID, 'releases', true );
		$hotfix     = $releases[0];
		$hotfix     = array_merge( $hotfix, array( 'tag' => self::VERSION . '-hotfix' ) );
		$releases[] = $hotfix;
		update_post_meta( $this->plugin->ID, 'releases', $releases );
		update_post_meta( $this->plugin->ID, 'stable_tag', self::VERSION . '-hotfix' );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
		$this->assertFalse( API_Update_Updater::is_release_blocked( Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::VERSION . '-hotfix' ) ) );
		$this->assertCount( 0, $this->get_internal_notes() );
	}

	/**
	 * Backslashes in finding text survive the review note.
	 */
	public function test_note_keeps_backslashes_in_findings(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings_count'  => 1,
				'findings'        => array( $this->finding( 9.8, array( 'title' => 'Regex \e escape reaches preg_replace' ) ) ),
				'severity_counts' => array( 'error' => 1 ),
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );

		$this->assertStringContainsString( 'Regex \e escape reaches preg_replace', $this->get_internal_notes()[0]->comment_content );
	}

	/**
	 * The review note reports the risk score with the same precision as the alert.
	 */
	public function test_note_formats_risk_score_to_one_decimal(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings_count'  => 1,
				'findings'        => array( $this->finding( 8.0 ) ),
				'max_risk_score'  => 8.0,
				'severity_counts' => array( 'error' => 1 ),
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );

		$this->assertStringContainsString( 'maximum risk score of 8.0', $this->get_internal_notes()[0]->comment_content );
	}

	/**
	 * A callback for a different version than the pending scan is rejected.
	 */
	public function test_mismatched_version_is_rejected(): void {
		$result = Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback( array( 'version' => '9.9.9' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_gandalf_scan', $result->get_error_code() );
	}

	/**
	 * Failed scans record the error without touching the plugin.
	 */
	public function test_failed_scan_records_error(): void {
		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->failed_callback() ) );
		$this->assertSame( 'publish', get_post( $this->plugin->ID )->post_status );

		$last_error = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::LAST_ERROR_META_KEY, true );
		$this->assertSame( 'timeout', $last_error['kind'] );

		$consumed = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::CONSUMED_META_KEY, true );
		$this->assertArrayHasKey( self::SCAN_ID, $consumed );

		// A completed verdict may still arrive; the pending entry survives the failure.
		$pending = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );
		$this->assertArrayHasKey( self::SCAN_ID, $pending );
	}

	/**
	 * A block holds the update API row on the served version and cancels the
	 * serve deferred to cooldown-end.
	 */
	public function test_block_holds_update_source_on_served_version(): void {
		global $wpdb;

		$this->stage_cooldown_release();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT available, version FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s", $this->plugin->post_name ) );

		$this->assertSame( '1', $row->available );
		$this->assertSame( '1.0.0', $row->version );
		$this->assertFalse( wp_next_scheduled( "release_to_update_api:{$this->plugin->post_name}" ) );

		// The block, not the cooldown clock, holds the version: a later write changes nothing.
		API_Update_Updater::update_single_plugin( $this->plugin->post_name );
		$this->assertSame( '1.0.0', $this->get_served_version() );
	}

	/**
	 * A reviewer force-release clears the block and serves the version.
	 */
	public function test_force_release_serves_blocked_version(): void {
		$this->stage_cooldown_release();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $this->get_release() ) );

		$this->assertTrue( API_Update_Updater::force_release( $this->plugin->post_name, 'Reviewed; findings are a false positive.' ) );

		$this->assertFalse( API_Update_Updater::is_release_blocked( $this->get_release() ) );
		$this->assertSame( self::VERSION, $this->get_served_version() );
	}

	/**
	 * Closing a plugin during the cooldown reaches its update API row
	 * immediately, and reopening restores it; only the version bump keeps
	 * waiting for the cooldown.
	 */
	public function test_status_change_during_cooldown_syncs_update_source(): void {
		global $wpdb;

		$this->stage_cooldown_release();

		wp_update_post(
			array(
				'ID'          => $this->plugin->ID,
				'post_status' => 'closed',
			)
		);
		update_post_meta( $this->plugin->ID, '_close_reason', 'security-issue' );
		update_post_meta( $this->plugin->ID, 'plugin_closed_date', current_time( 'mysql' ) );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT available, version, meta FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s", $this->plugin->post_name ) );
		$this->assertSame( '0', $row->available );
		$this->assertStringContainsString( 'closed_at', (string) $row->meta );
		$this->assertSame( '1.0.0', $row->version );

		wp_update_post(
			array(
				'ID'          => $this->plugin->ID,
				'post_status' => 'publish',
			)
		);
		delete_post_meta( $this->plugin->ID, '_close_reason' );
		delete_post_meta( $this->plugin->ID, 'plugin_closed_date' );

		$this->assertTrue( API_Update_Updater::update_single_plugin( $this->plugin->post_name ) );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT available, version, meta FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s", $this->plugin->post_name ) );
		$this->assertSame( '1', $row->available );
		$this->assertStringNotContainsString( 'closed_at', (string) $row->meta );

		// The staged version stays unreleased until the cooldown expires.
		$this->assertSame( '1.0.0', $row->version );
	}
}
