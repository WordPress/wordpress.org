<?php
/**
 * Base test case for the WordPress.org SSO.
 *
 * WordPress ships `WP_UnitTestCase`, but its `set_up()` calls
 * `PHPUnit\Util\Test::parseTestMethodAnnotations()`, which PHPUnit 10 removed.
 * WordPress has no PHPUnit 10+ compatible release, so extending it fails every
 * test before a single assertion runs. This mirrors the approach the o2 Posting
 * Access and handbook suites take.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Provides WordPress fixtures and the request state the SSO reads.
 */
abstract class WPOrg_SSO_TestCase extends TestCase {

	/**
	 * Shared fixture factory.
	 *
	 * @var WP_UnitTest_Factory|null
	 */
	protected static ?WP_UnitTest_Factory $factory_instance = null;

	/**
	 * Hook globals captured before the first test, restored after each test.
	 *
	 * @var array
	 */
	protected static array $hooks_saved = array();

	/**
	 * Fixture factory.
	 *
	 * @var WP_UnitTest_Factory
	 */
	protected WP_UnitTest_Factory $factory;

	/**
	 * Superglobals captured before each test, restored after it.
	 *
	 * @var array
	 */
	protected array $superglobals_saved = array();

	/**
	 * Returns the fixture factory, creating it on first use.
	 *
	 * @return WP_UnitTest_Factory
	 */
	protected static function factory(): WP_UnitTest_Factory {
		if ( ! self::$factory_instance ) {
			self::$factory_instance = new WP_UnitTest_Factory();
		}

		return self::$factory_instance;
	}

	/**
	 * Prepares a clean WordPress state before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->factory = static::factory();

		if ( ! self::$hooks_saved ) {
			$this->backup_hooks();
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Snapshotting the request, not reading it.
		$this->superglobals_saved = array(
			'GET'     => $_GET,
			'POST'    => $_POST,
			'REQUEST' => $_REQUEST,
			'SERVER'  => $_SERVER,
			'COOKIE'  => $_COOKIE,
		);
		// phpcs:enable WordPress.Security.NonceVerification

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		$_COOKIE  = array();

		// The SSO reads both; a value left over from the host environment would steer the tests.
		unset( $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'] );

		// wordpress.org is HTTPS-only, and the scheme decides what the SSO rebuilds URLs as.
		$_SERVER['HTTPS'] = 'on';

		unset( $GLOBALS['nologin_accounts'] );

		// Static, and only reset once the dispatcher reaches the SSO host, so a stale route would read as this test's.
		WP_WPOrg_SSO::$matched_route        = false;
		WP_WPOrg_SSO::$matched_route_regex  = false;
		WP_WPOrg_SSO::$matched_route_params = array();

		WPOrg_SSO_Two_Factor_State::reset();

		wp_cache_flush();

		// The binding-reject logger raises an E_USER_WARNING; leave it off unless the test is about it.
		add_filter( 'wporg_sso_log_binding_rejects', '__return_false' );

		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );

		$this->start_transaction();
	}

	/**
	 * Rolls back database and global state after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control has no caching or API equivalent.
		$wpdb->query( 'ROLLBACK' );

		remove_filter( 'query', array( $this, 'create_temporary_tables' ) );
		remove_filter( 'query', array( $this, 'drop_temporary_tables' ) );

		$this->restore_hooks();

		$_GET     = $this->superglobals_saved['GET'];
		$_POST    = $this->superglobals_saved['POST'];
		$_REQUEST = $this->superglobals_saved['REQUEST'];
		$_SERVER  = $this->superglobals_saved['SERVER'];
		$_COOKIE  = $this->superglobals_saved['COOKIE'];

		unset( $GLOBALS['nologin_accounts'] );

		wp_set_current_user( 0 );

		/*
		 * Again, because the reset above fires `set_current_user` and so bumps
		 * the action counters past the snapshot the line above restored. Done in
		 * this order so the reset itself runs against baseline hooks rather than
		 * whatever the test registered.
		 */
		$this->restore_hooks();

		parent::tearDown();
	}

	/**
	 * Returns the `wp_die()` handler that throws instead of halting the run.
	 *
	 * @return callable
	 */
	public function get_wp_die_handler(): callable {
		return array( $this, 'throw_on_wp_die' );
	}

	/**
	 * Stands in for `wp_die()`.
	 *
	 * @throws WPOrg_SSO_Die_Exception Always.
	 *
	 * @param string|WP_Error $message The message passed to `wp_die()`.
	 */
	public function throw_on_wp_die( $message ): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by the test, never rendered.
		throw new WPOrg_SSO_Die_Exception( is_wp_error( $message ) ? $message->get_error_message() : (string) $message );
	}

	/**
	 * Builds an SSO instance serving a given request.
	 *
	 * The SSO reads the host and script from `$_SERVER` in its constructor, so
	 * the request has to be in place before the instance exists.
	 *
	 * @param string $host   The host the request arrived on.
	 * @param string $script The script handling the request, e.g. `/wp-login.php`.
	 * @param string $uri    The request URI.
	 * @param string $double The double to build, for the states the plain one cannot reach.
	 * @return WPOrg_SSO_Test_Double
	 */
	protected function make_sso( string $host, string $script = '/index.php', string $uri = '/', string $double = WPOrg_SSO_Test_Double::class ): WPOrg_SSO_Test_Double {
		$_SERVER['HTTP_HOST']   = $host;
		$_SERVER['SCRIPT_NAME'] = $script;
		$_SERVER['REQUEST_URI'] = $uri;

		return new $double();
	}

	/**
	 * Runs a callback that is expected to end in a redirect, and returns it.
	 *
	 * @param callable $callback The callback to run.
	 * @return WPOrg_SSO_Redirect_Exception
	 */
	protected function catch_redirect( callable $callback ): WPOrg_SSO_Redirect_Exception {
		try {
			$callback();
		} catch ( WPOrg_SSO_Redirect_Exception $redirect ) {
			return $redirect;
		}

		$this->fail( 'Expected a redirect, but none was attempted.' );
	}

	/**
	 * Asserts that a callback completes without the instance redirecting.
	 *
	 * @param WPOrg_SSO_Test_Double|BB_WPOrg_SSO_Test_Double $sso      The instance that must stay put.
	 * @param callable                                       $callback The callback to run.
	 * @return void
	 */
	protected function assert_no_redirect( $sso, callable $callback ): void {
		try {
			$callback();
		} catch ( WPOrg_SSO_Redirect_Exception $redirect ) {
			// Swallowed so the assertion below reports it, with the target it was headed for.
			unset( $redirect );
		}

		$this->assertSame( array(), $sso->redirects, 'The request was redirected.' );
	}

	/**
	 * Runs a callback that sets cookies, without PHP's complaint about it.
	 *
	 * PHPUnit has already written to stdout by the time a test runs, so every
	 * `setcookie()` the SSO makes warns. Only that warning is swallowed; any
	 * other one still surfaces.
	 *
	 * @param callable $callback The callback to run.
	 * @return mixed Whatever the callback returned.
	 */
	protected function without_header_warnings( callable $callback ) {
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Silencing an artefact of running under CLI.
			static function ( $errno, $errstr ) {
				return str_contains( (string) $errstr, 'Cannot modify header information' );
			},
			E_WARNING
		);

		try {
			return $callback();
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * The bounce ticket a browser holds during a hand-off.
	 *
	 * @var string
	 */
	protected const BOUNCE_TICKET = 'a-bounce-ticket';

	/**
	 * The fingerprint the SSO host mints a token against.
	 *
	 * @return string
	 */
	protected function bounce_fingerprint(): string {
		return hash( 'sha256', static::BOUNCE_TICKET );
	}

	/**
	 * Gives the browser the bounce ticket a login on the destination leaves it.
	 *
	 * @return string The ticket's fingerprint.
	 */
	protected function hold_a_bounce_ticket(): string {
		$_COOKIE[ WPOrg_SSO::REMOTE_BOUNCE_COOKIE ] = static::BOUNCE_TICKET;

		return $this->bounce_fingerprint();
	}

	/**
	 * Returns the value of a query argument in a URL.
	 *
	 * @param string $url The URL to read.
	 * @param string $arg The query argument to return.
	 * @return string|null The decoded value, or null when the argument is absent.
	 */
	protected function query_arg( string $url, string $arg ): ?string {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );

		parse_str( $query, $args );

		return isset( $args[ $arg ] ) ? (string) $args[ $arg ] : null;
	}

	/**
	 * Wraps the test in a transaction so database writes never persist.
	 *
	 * @return void
	 */
	protected function start_transaction(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control has no caching or API equivalent.
		$wpdb->query( 'SET autocommit = 0;' );
		$wpdb->query( 'START TRANSACTION;' );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		add_filter( 'query', array( $this, 'create_temporary_tables' ) );
		add_filter( 'query', array( $this, 'drop_temporary_tables' ) );
	}

	/**
	 * Rewrites CREATE TABLE statements to create temporary tables.
	 *
	 * @param string $query The query to filter.
	 * @return string
	 */
	public function create_temporary_tables( $query ): string {
		if ( str_starts_with( trim( $query ), 'CREATE TABLE' ) ) {
			return substr_replace( trim( $query ), 'CREATE TEMPORARY TABLE', 0, 12 );
		}

		return $query;
	}

	/**
	 * Rewrites DROP TABLE statements to drop temporary tables.
	 *
	 * @param string $query The query to filter.
	 * @return string
	 */
	public function drop_temporary_tables( $query ): string {
		if ( str_starts_with( trim( $query ), 'DROP TABLE' ) ) {
			return substr_replace( trim( $query ), 'DROP TEMPORARY TABLE', 0, 10 );
		}

		return $query;
	}

	/**
	 * Snapshots the hook globals.
	 *
	 * @return void
	 */
	protected function backup_hooks(): void {
		self::$hooks_saved['wp_filter'] = array();

		foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook_object ) {
			self::$hooks_saved['wp_filter'][ $hook_name ] = clone $hook_object;
		}

		foreach ( array( 'wp_actions', 'wp_filters', 'wp_current_filter' ) as $key ) {
			self::$hooks_saved[ $key ] = $GLOBALS[ $key ] ?? array();
		}
	}

	/**
	 * Restores the hook globals from the snapshot.
	 *
	 * @return void
	 */
	protected function restore_hooks(): void {
		if ( ! isset( self::$hooks_saved['wp_filter'] ) ) {
			return;
		}

		$GLOBALS['wp_filter'] = array();

		foreach ( self::$hooks_saved['wp_filter'] as $hook_name => $hook_object ) {
			$GLOBALS['wp_filter'][ $hook_name ] = clone $hook_object;
		}

		foreach ( array( 'wp_actions', 'wp_filters', 'wp_current_filter' ) as $key ) {
			if ( isset( self::$hooks_saved[ $key ] ) ) {
				$GLOBALS[ $key ] = self::$hooks_saved[ $key ];
			}
		}
	}
}
