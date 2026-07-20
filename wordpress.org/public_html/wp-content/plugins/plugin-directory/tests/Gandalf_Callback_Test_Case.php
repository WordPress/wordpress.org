<?php
/**
 * Shared harness for full-chain tests of the Gandalf scan callback route,
 * `plugins/v1/plugin/{slug}/gandalf-scan`.
 *
 * Seeds a plugin serving one version with a newer one waiting out its release delay and a scan
 * in flight against it, then drives callbacks through the REST route the way Gandalf does —
 * authentication, routing, and the write to `update_source`. Subclasses assert what a
 * particular verdict does; the route-level authentication is covered here, once.
 *
 * Contains no tests of its own, so it's excluded from the suite in phpunit.xml and loaded by
 * the test bootstrap (by its `*_Test_Case.php` name) for its subclasses to extend.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * @group jobs
 */
abstract class Gandalf_Callback_Test_Case extends TestCase {

	/**
	 * The shared secret both directions authenticate with.
	 */
	const SECRET = 'gandalf-callback-testcase-secret';

	/**
	 * The delay captured on the release under test.
	 */
	const DELAY = DAY_IN_SECONDS;

	/**
	 * The plugin slug under test.
	 */
	const SLUG = 'gandalf-callback-testcase';

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
	const SCAN_ID = '9a7b6c5d-0000-4000-8000-0123456789ab';

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
			define( 'WP_GANDALF_SCAN_SHARED_SECRET', static::SECRET );
		}
	}

	/**
	 * A plugin serving SERVED_VERSION, with NEW_VERSION inside its delay and a scan in flight.
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
				'post_name'         => static::SLUG,
				'post_title'        => 'Gandalf Callback Test',
				'post_status'       => 'publish',
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		$this->assertNotInstanceOf( WP_Error::class, $plugin_id );

		$this->plugin = get_post( $plugin_id );

		update_post_meta( $plugin_id, 'version', static::NEW_VERSION );
		update_post_meta( $plugin_id, 'stable_tag', static::NEW_VERSION );
		update_post_meta( $plugin_id, 'header_name', 'Gandalf Callback Test' );
		update_post_meta( $plugin_id, 'header_author', 'WordPress' );
		update_post_meta( $plugin_id, 'version_date', current_time( 'mysql', 1 ) );

		update_post_meta( $plugin_id, 'releases', array( $this->release() ) );

		$this->set_pending_scan();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}update_source`" );
		$this->serve( static::SERVED_VERSION );
	}

	/**
	 * Remove the plugin, its meta, any audit-log notes, and any deferred cron event.
	 */
	protected function tearDown(): void {
		wp_clear_scheduled_hook( 'release_to_update_api:' . static::SLUG );

		foreach ( get_comments( array( 'post_id' => $this->plugin->ID ) ) as $note ) {
			wp_delete_comment( $note->comment_ID, true );
		}

		wp_delete_post( $this->plugin->ID, true );

		parent::tearDown();
	}

	/**
	 * The release row seeded onto the plugin: NEW_VERSION, confirmed, inside its delay.
	 *
	 * @param array $overrides Values to override on the default release.
	 * @return array
	 */
	protected function release( $overrides = array() ) {
		return array_merge(
			array(
				'date'                   => time(),
				'tag'                    => static::NEW_VERSION,
				'version'                => static::NEW_VERSION,
				'zips_built'             => true,
				'confirmations'          => array(),
				'confirmed'              => true,
				'confirmations_required' => 0,
				'committer'              => array(),
				'revision'               => array(),
				'release_delay'          => static::DELAY,
			),
			$overrides
		);
	}

	/**
	 * Record the in-flight scan the callback is correlated against.
	 */
	protected function set_pending_scan() {
		update_post_meta(
			$this->plugin->ID,
			Plugin_Scan_Gandalf::PENDING_META_KEY,
			array(
				static::SCAN_ID => array(
					'version'      => static::NEW_VERSION,
					'release_ref'  => static::NEW_VERSION,
					'requested_at' => time(),
				),
			)
		);
	}

	/**
	 * A well-formed completed-scan body, as Gandalf would send it. The scanned version has no
	 * findings; a verdict field like risk_score is added per-test via $overrides.
	 *
	 * @param array $overrides Values to override.
	 * @return array
	 */
	protected function callback_body( $overrides = array() ) {
		return array_merge(
			array(
				'scan_id'         => static::SCAN_ID,
				'status'          => 'completed',
				'version'         => static::NEW_VERSION,
				'release_ref'     => static::NEW_VERSION,
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
	protected function post_callback( $body, $secret = null ) {
		if ( null === $secret ) {
			$secret = static::SECRET;
		}

		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/' . static::SLUG . '/gandalf-scan' );

		if ( false !== $secret ) {
			$request->set_header( 'authorization', 'Bearer ' . $secret );
		}

		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	/**
	 * Put a version into `update_source`, standing in for the currently-served release.
	 *
	 * @param string $version The version to serve.
	 */
	protected function serve( $version ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'    => $this->plugin->ID,
				'plugin_slug'  => static::SLUG,
				'available'    => 1,
				'version'      => $version,
				'last_updated' => current_time( 'mysql' ),
			)
		);
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
				static::SLUG
			)
		);
	}

	/**
	 * The pending scans still awaiting a callback.
	 *
	 * @return array
	 */
	protected function get_pending_scans() {
		$pending = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );

		return $pending ? $pending : array();
	}

	/**
	 * A callback that authenticated and matched its pending scan is processed and no longer in flight.
	 */
	public function test_a_processed_callback_clears_the_pending_scan() {
		$response = $this->post_callback( $this->callback_body() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $this->get_pending_scans() );
	}

	/**
	 * The route is authenticated: a callback without the shared secret changes nothing.
	 */
	public function test_a_callback_without_the_shared_secret_is_rejected() {
		$response = $this->post_callback( $this->callback_body(), false );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( static::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * The secret is compared, not merely required.
	 */
	public function test_a_callback_with_the_wrong_shared_secret_is_rejected() {
		$response = $this->post_callback( $this->callback_body(), 'not-the-secret' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( static::SERVED_VERSION, $this->get_served_version() );
	}
}
