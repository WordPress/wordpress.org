<?php
/**
 * Tests for the exactly-once consumption of security scan callbacks.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that security scan callbacks are consumed exactly once.
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
class Security_Scan_Consumption_Test extends TestCase {

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
	 * Create a published plugin with a pending security scan.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'consumption-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Scan Consumption Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( \WP_Post::class, $plugin );
		$this->plugin = $plugin;

		$this->add_pending_scan( self::SCAN_ID );
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
	 * Build a finding entry matching the callback contract.
	 *
	 * @param float $risk_score The finding risk score.
	 * @return array The finding.
	 */
	private function finding( float $risk_score ): array {
		return array(
			'id'            => 'finding-' . md5( (string) $risk_score ),
			'ref'           => 'prompt-security.supply_chain.remote_controlled_code',
			'title'         => 'Remote response controls a PHP callable',
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
	 * An identical retry of a consumed callback is acknowledged without
	 * repeating effects.
	 *
	 * Before consumption records existed, a retry arriving after the pending
	 * entry was cleared was rejected as an unknown scan.
	 */
	public function test_identical_replay_is_acknowledged_once(): void {
		$callback = $this->completed_callback();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );
		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true ) );

		$consumed = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::CONSUMED_META_KEY, true );
		$this->assertArrayHasKey( self::SCAN_ID, $consumed );

		/*
		 * The Slack dedup record is written even without a configured channel;
		 * clearing it exposes whether the retry re-runs the notification effects.
		 */
		delete_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::NOTIFIED_META_KEY );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( get_post( $this->plugin->ID ), $callback ) );
		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::NOTIFIED_META_KEY, true ) );
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
	}

	/**
	 * A completed verdict supersedes an earlier failure report for the same scan.
	 */
	public function test_completed_verdict_supersedes_failed_report(): void {
		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->failed_callback() ) );
		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( get_post( $this->plugin->ID ), $this->completed_callback() ) );

		// The completed verdict was processed, not swallowed by the consumed failure.
		$notified = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::NOTIFIED_META_KEY, true );
		$this->assertArrayHasKey( 'f71c3d944050095a4e2e20f9ee8a7c9a', $notified );

		$consumed = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::CONSUMED_META_KEY, true );
		$this->assertSame( 'completed', $consumed[ self::SCAN_ID ]['status'] );

		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true ) );
	}

	/**
	 * A failure report records the error and keeps the pending entry for a
	 * completed verdict that may still arrive.
	 */
	public function test_failed_scan_records_error(): void {
		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->failed_callback() ) );
		$this->assertSame( 'publish', get_post( $this->plugin->ID )->post_status );

		$last_error = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::LAST_ERROR_META_KEY, true );
		$this->assertSame( 'timeout', $last_error['kind'] );

		$consumed = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::CONSUMED_META_KEY, true );
		$this->assertArrayHasKey( self::SCAN_ID, $consumed );

		$pending = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );
		$this->assertArrayHasKey( self::SCAN_ID, $pending );
	}

	/**
	 * A callback for a different version than the pending scan is rejected
	 * and not recorded as consumed.
	 */
	public function test_mismatched_version_is_rejected(): void {
		$result = Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback( array( 'version' => '9.9.9' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_gandalf_scan', $result->get_error_code() );

		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::CONSUMED_META_KEY, true ) );
	}

	/**
	 * A callback arriving while another is being processed for the same plugin
	 * is rejected without consuming anything.
	 */
	public function test_concurrent_callback_is_rejected_while_locked(): void {
		wp_cache_add( 'gandalf-scan-callback-' . $this->plugin->ID, 1, 'plugin-scans', 5 * MINUTE_IN_SECONDS );

		$result = Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'security_scan_locked', $result->get_error_code() );
		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::CONSUMED_META_KEY, true ) );

		// Once the holder releases the lock, the retry consumes normally.
		wp_cache_delete( 'gandalf-scan-callback-' . $this->plugin->ID, 'plugin-scans' );
		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );
	}
}
