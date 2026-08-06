<?php
/**
 * Tests for the Gandalf scan callback route, `plugins/v1/plugin/{slug}/gandalf-scan`.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * Covers what Gandalf's verdict does to a release waiting out its delay.
 *
 * @group jobs
 */
class Gandalf_Scan_Callback_Test extends TestCase {

	/**
	 * The shared secret both directions authenticate with.
	 */
	const SECRET = 'gandalf-callback-test-secret';

	/**
	 * The delay captured on the release under test.
	 */
	const DELAY = DAY_IN_SECONDS;

	/**
	 * The plugin slug under test.
	 */
	const SLUG = 'gandalf-callback-test';

	/**
	 * The version already being served when a test starts.
	 */
	const SERVED_VERSION = '1.0';

	/**
	 * The scanned version, waiting out its delay.
	 */
	const NEW_VERSION = '2.0';

	/**
	 * The scan Gandalf is reporting on.
	 */
	const SCAN_ID = '3f8c1d2e-0000-4000-8000-abcdefabcdef';

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	protected $plugin;

	/**
	 * Both directions of the integration gate on the shared secret, so without it the
	 * route only ever answers 401.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) ) {
			define( 'WP_GANDALF_SCAN_SHARED_SECRET', self::SECRET );
		}
	}

	/**
	 * A plugin serving 1.0, with 2.0 inside its delay and a Gandalf scan in flight.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		// Tools::audit_log() reads this unconditionally; CLI has no remote address.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		// post_modified is required: Plugin_Directory::filter_wp_insert_post_data() copies it
		// from $postarr, and wp_insert_post() doesn't default it, so the insert fails without.
		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_name'         => self::SLUG,
				'post_title'        => 'Gandalf Callback Test',
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
		update_post_meta( $plugin_id, 'header_name', 'Gandalf Callback Test' );
		update_post_meta( $plugin_id, 'header_author', 'WordPress' );
		update_post_meta( $plugin_id, 'version_date', current_time( 'mysql', 1 ) );

		update_post_meta(
			$plugin_id,
			'releases',
			array(
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
			)
		);

		$this->set_pending_scan();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}update_source`" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Seeding the version this test starts out serving.
		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'    => $plugin_id,
				'plugin_slug'  => self::SLUG,
				'available'    => 1,
				'version'      => self::SERVED_VERSION,
				'last_updated' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Remove the plugin, its meta, any audit-log notes, and any deferred cron event.
	 */
	protected function tearDown(): void {
		wp_clear_scheduled_hook( 'release_to_update_api:' . self::SLUG );

		foreach ( get_comments( array( 'post_id' => $this->plugin->ID ) ) as $note ) {
			wp_delete_comment( $note->comment_ID, true );
		}

		wp_delete_post( $this->plugin->ID, true );

		parent::tearDown();
	}

	/**
	 * Record the in-flight scan the callback is correlated against.
	 */
	protected function set_pending_scan() {
		update_post_meta(
			$this->plugin->ID,
			'_gandalf_scan_pending',
			array(
				self::SCAN_ID => array(
					'version'      => self::NEW_VERSION,
					'release_ref'  => self::NEW_VERSION,
					'requested_at' => time(),
				),
			)
		);
	}

	/**
	 * A well-formed callback body, as Gandalf would send it.
	 *
	 * @param array $overrides Values to override.
	 * @return array
	 */
	protected function callback_body( $overrides = array() ) {
		return array_merge(
			array(
				'scan_id'         => self::SCAN_ID,
				'status'          => 'completed',
				'version'         => self::NEW_VERSION,
				'release_ref'     => self::NEW_VERSION,
				'findings_count'  => 0,
				'severity_counts' => array(),
				'verdict_hash'    => 'abc123',
				'report_url'      => 'https://gandalf.wordpress.org/report/abc123',
			),
			$overrides
		);
	}

	/**
	 * POST a callback to the route, as Gandalf would.
	 *
	 * @param array       $body   The callback body.
	 * @param string|null $secret The bearer secret, or null to send no Authorization header.
	 * @return \WP_REST_Response
	 */
	protected function post_callback( $body, $secret = self::SECRET ) {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/' . self::SLUG . '/gandalf-scan' );

		if ( null !== $secret ) {
			$request->set_header( 'authorization', 'Bearer ' . $secret );
		}

		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		return rest_do_request( $request );
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
	 * The pending scans still awaiting a callback.
	 *
	 * @return array
	 */
	protected function get_pending_scans() {
		$pending = get_post_meta( $this->plugin->ID, '_gandalf_scan_pending', true );

		return $pending ? $pending : array();
	}

	/**
	 * The headline behaviour: Gandalf reports a clean scan over the API, and the version
	 * it scanned stops waiting and starts being served.
	 */
	public function test_a_callback_with_no_findings_serves_the_release() {
		$response = $this->post_callback( $this->callback_body() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::NEW_VERSION, $this->get_served_version() );
	}

	/**
	 * A scan that reported back is no longer in flight.
	 */
	public function test_a_callback_with_no_findings_clears_the_pending_scan() {
		$this->post_callback( $this->callback_body() );

		$this->assertSame( array(), $this->get_pending_scans() );
	}

	/**
	 * The scan that ended the wait is named, so the release can be traced back to it.
	 */
	public function test_a_callback_with_no_findings_records_the_scan_in_the_audit_log() {
		$this->post_callback( $this->callback_body() );

		$notes = get_comments(
			array(
				'post_id' => $this->plugin->ID,
				'type'    => 'internal-note',
			)
		);

		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( self::SCAN_ID, $notes[0]->comment_content );
	}

	/**
	 * Findings are the case the delay exists for, so the release keeps waiting.
	 */
	public function test_a_callback_with_findings_leaves_the_release_waiting() {
		$response = $this->post_callback( $this->callback_body( array( 'findings_count' => 3 ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * Serving a release early is authenticated, not open to anyone who finds the URL.
	 */
	public function test_a_callback_without_the_shared_secret_is_rejected() {
		$response = $this->post_callback( $this->callback_body(), null );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * The secret is compared, not merely required.
	 */
	public function test_a_callback_with_the_wrong_shared_secret_is_rejected() {
		$response = $this->post_callback( $this->callback_body(), 'not-the-secret' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * A verdict for a scan that was never dispatched acts on nothing.
	 */
	public function test_a_callback_for_an_unknown_scan_is_rejected() {
		$response = $this->post_callback( $this->callback_body( array( 'scan_id' => 'ffffffff-0000-4000-8000-ffffffffffff' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * The callback is correlated against the pending record, so a verdict that arrives for
	 * a different version than the one dispatched can't serve anything.
	 */
	public function test_a_callback_for_a_different_version_is_rejected() {
		$response = $this->post_callback( $this->callback_body( array( 'version' => '9.9' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
		$this->assertArrayHasKey( self::SCAN_ID, $this->get_pending_scans() );
	}

	/**
	 * `(int) 'abc'` is 0, so a findings_count that isn't a number must be rejected rather
	 * than read as a clean scan.
	 *
	 * @dataProvider data_unusable_findings_counts
	 * @param mixed $findings_count The value Gandalf sent.
	 */
	public function test_a_callback_without_a_usable_findings_count_is_rejected( $findings_count ) {
		$body                   = $this->callback_body();
		$body['findings_count'] = $findings_count;

		$response = $this->post_callback( $body );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
		$this->assertArrayHasKey( self::SCAN_ID, $this->get_pending_scans() );
	}

	/**
	 * Values a findings_count could arrive as if Gandalf's payload drifted.
	 *
	 * @return array
	 */
	public static function data_unusable_findings_counts() {
		return array(
			'non-numeric string' => array( 'abc' ),
			'null'               => array( null ),
			'boolean'            => array( false ),
			'array'              => array( array() ),
		);
	}

	/**
	 * A missing findings_count is the realistic drift case: `null > 0` is false, so a plain
	 * `else` would have read it as clean.
	 */
	public function test_a_callback_with_no_findings_count_at_all_is_rejected() {
		$body = $this->callback_body();
		unset( $body['findings_count'] );

		$response = $this->post_callback( $body );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * A scan that couldn't reach a verdict leaves the delay to run its course.
	 */
	public function test_a_failed_scan_leaves_the_release_waiting_and_records_the_error() {
		$response = $this->post_callback(
			$this->callback_body(
				array(
					'status' => 'failed',
					'error'  => array(
						'kind'    => 'zip_unreadable',
						'message' => 'Could not read the plugin ZIP.',
					),
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::SERVED_VERSION, $this->get_served_version() );

		$error = get_post_meta( $this->plugin->ID, '_gandalf_scan_last_error', true );
		$this->assertSame( 'zip_unreadable', $error['kind'] );
	}
}
