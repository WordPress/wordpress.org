<?php
/**
 * Base test case for the Trac Notifications plugin.
 *
 * WordPress ships `WP_UnitTestCase`, but its `set_up()` calls
 * `PHPUnit\Util\Test::parseTestMethodAnnotations()`, which PHPUnit 10 removed.
 * This mirrors the approach the Trac Watcher and o2 Posting Access suites take.
 *
 * @package trac-notifications
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Provides WordPress fixtures and per-test isolation.
 */
abstract class WPorg_Trac_Components_TestCase extends TestCase {

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
	 * The component pages instance under test.
	 *
	 * @var Make_Core_Trac_Components
	 */
	protected Make_Core_Trac_Components $components;

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
	 */
	public function setUp(): void {
		parent::setUp();

		$this->factory    = static::factory();
		$this->components = $GLOBALS['wporg_trac_components'];

		if ( ! self::$hooks_saved ) {
			$this->backup_hooks();
		}

		wp_cache_flush();

		$this->start_transaction();
	}

	/**
	 * Rolls back database and global state after each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control has no caching or API equivalent.
		$wpdb->query( 'ROLLBACK' );

		$this->restore_hooks();

		wp_set_current_user( 0 );

		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();

		parent::tearDown();
	}

	/**
	 * Wraps the test in a transaction so database writes never persist.
	 */
	protected function start_transaction(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control has no caching or API equivalent.
		$wpdb->query( 'SET autocommit = 0;' );
		$wpdb->query( 'START TRANSACTION;' );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Snapshots the hook globals.
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
