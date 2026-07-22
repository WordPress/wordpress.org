<?php
/**
 * Shared harness for full-chain tests of the Gandalf scan callback route,
 * `plugins/v1/plugin/{slug}/gandalf-scan`.
 *
 * Takes the scan fixture and drives callbacks through the REST route the way Gandalf does —
 * authentication, routing, and the write to `update_source`. Subclasses assert what a
 * particular verdict does; the route-level authentication is covered here, once.
 *
 * Contains no tests of its own beyond that, so it's excluded from the suite in phpunit.xml
 * and loaded on demand by the test bootstrap's autoloader when a subclass extends it.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

/**
 * @group jobs
 */
abstract class Gandalf_Callback_Test_Case extends Gandalf_Scan_Test_Case {

	/**
	 * The shared secret to define, when nothing else has.
	 */
	const SECRET = 'gandalf-callback-testcase-secret';

	/**
	 * The plugin slug under test.
	 */
	const SLUG = 'gandalf-callback-testcase';

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
	 * The secret the route will actually accept.
	 *
	 * Read back rather than assumed to be static::SECRET: it's a constant, so anything that
	 * defined one first — a sandbox mu-plugin, another suite — wins, and the tests would then
	 * authenticate with a secret the route rejects and fail as a wall of 401s.
	 *
	 * @return string
	 */
	protected static function secret() {
		return defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) ? WP_GANDALF_SCAN_SHARED_SECRET : static::SECRET;
	}

	/**
	 * POST a callback to the route, as Gandalf would.
	 *
	 * @param array            $body   The callback body.
	 * @param string|null|bool $secret The bearer secret, null for the correct one, or false to
	 *                                 send no Authorization header at all.
	 * @return \WP_REST_Response
	 */
	protected function post_callback( $body, $secret = null ) {
		if ( null === $secret ) {
			$secret = static::secret();
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
	 * A callback that authenticated and matched its pending scan is processed and no longer in flight.
	 */
	public function test_a_processed_callback_clears_the_pending_scan() {
		$response = $this->post_callback( $this->callback_data() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $this->get_pending_scans() );
	}

	/**
	 * The route is authenticated: a callback without the shared secret changes nothing.
	 */
	public function test_a_callback_without_the_shared_secret_is_rejected() {
		$response = $this->post_callback( $this->callback_data(), false );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( static::SERVED_VERSION, $this->get_served_version() );
	}

	/**
	 * The secret is compared, not merely required.
	 */
	public function test_a_callback_with_the_wrong_shared_secret_is_rejected() {
		$response = $this->post_callback( $this->callback_data(), 'not-the-secret' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( static::SERVED_VERSION, $this->get_served_version() );
	}
}
