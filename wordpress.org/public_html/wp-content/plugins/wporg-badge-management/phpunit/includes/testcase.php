<?php
/**
 * Base test case for the Badge Management plugin.
 *
 * WordPress ships `WP_UnitTestCase`, but its `set_up()` calls
 * `PHPUnit\Util\Test::parseTestMethodAnnotations()`, which PHPUnit 10 removed.
 * WordPress has no PHPUnit 10+ compatible release, so extending it fails every
 * test before a single assertion runs. This mirrors the approach the handbook
 * plugin's suite takes.
 *
 * @package wporg-badge-management
 */

use PHPUnit\Framework\TestCase;

/**
 * Provides WordPress fixtures and multisite membership helpers.
 */
abstract class WPorg_Badge_Management_TestCase extends TestCase {

	/**
	 * Shared fixture factory.
	 *
	 * @var WP_UnitTest_Factory|null
	 */
	protected static $factory_instance = null;

	/**
	 * Hook globals captured before the first test, restored after each test.
	 *
	 * @var array
	 */
	protected static $hooks_saved = array();

	/**
	 * Fixture factory.
	 *
	 * @var WP_UnitTest_Factory
	 */
	protected $factory;

	/**
	 * Returns the fixture factory, creating it on first use.
	 *
	 * @return WP_UnitTest_Factory
	 */
	protected static function factory() {
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

		$this->factory = static::factory();

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

		remove_filter( 'query', array( $this, 'create_temporary_tables' ) );
		remove_filter( 'query', array( $this, 'drop_temporary_tables' ) );

		$this->restore_hooks();

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Wraps the test in a transaction so database writes never persist.
	 */
	protected function start_transaction() {
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
	public function create_temporary_tables( $query ) {
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
	public function drop_temporary_tables( $query ) {
		if ( str_starts_with( trim( $query ), 'DROP TABLE' ) ) {
			return substr_replace( trim( $query ), 'DROP TEMPORARY TABLE', 0, 10 );
		}

		return $query;
	}

	/**
	 * Snapshots the hook globals.
	 */
	protected function backup_hooks() {
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
	protected function restore_hooks() {
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

	/**
	 * Creates a user who is not a member of the current blog.
	 *
	 * The is_user_member_of_blog() check tests for the *presence* of the blog's
	 * capabilities user meta key, so clearing the role is not enough -- the key
	 * has to be deleted outright, which is what remove_user_from_blog() does.
	 *
	 * @param array $args Optional. Arguments to pass to the user factory.
	 * @return int The new user's ID.
	 */
	protected function create_non_member( $args = array() ) {
		$user_id = $this->factory()->user->create( array_merge( array( 'role' => 'author' ), $args ) );

		remove_user_from_blog( $user_id, get_current_blog_id() );

		wp_cache_delete( $user_id, 'user_meta' );

		/*
		 * Guard against the fixture silently becoming a member, which would
		 * make every assertion built on it pass for the wrong reason.
		 */
		if ( is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
			$this->fail( 'Fixture user is still a member of the blog; the non-member tests would be vacuous.' );
		}

		return $user_id;
	}
}
