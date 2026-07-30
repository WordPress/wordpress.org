<?php
/**
 * Base test case for the Trac Watcher plugin.
 *
 * WordPress ships `WP_UnitTestCase`, but its `set_up()` calls
 * `PHPUnit\Util\Test::parseTestMethodAnnotations()`, which PHPUnit 10 removed.
 * WordPress has no PHPUnit 10+ compatible release, so extending it fails every
 * test before a single assertion runs. This mirrors the approach the o2 Posting
 * Access and handbook suites take.
 *
 * @package wporg-trac-watcher
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use function WordPressdotorg\Trac\Watcher\SVN\get_svns;

/**
 * Provides WordPress fixtures plus revision and props seeding helpers.
 */
abstract class WPorg_Trac_Watcher_TestCase extends TestCase {

	/**
	 * Revision the seeding helpers operate on.
	 */
	const REVISION = 60001;

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
	 * Details for the core SVN, which every test uses.
	 *
	 * @var array
	 */
	protected array $svn;

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

		$this->factory = static::factory();
		$this->svn     = get_svns()['core'];

		if ( ! self::$hooks_saved ) {
			$this->backup_hooks();
		}

		wp_cache_flush();

		$this->start_transaction();

		/*
		 * The list table reads per_page off the current screen, and constructing
		 * it at all needs a screen to convert. Without the option registered the
		 * per_page lookup returns null and prepare_items() builds an invalid LIMIT.
		 */
		set_current_screen( 'toplevel_page_props-edit-core' );
		get_current_screen()->add_option( 'per_page', array( 'default' => 100 ) );
	}

	/**
	 * Rolls back database and global state after each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control has no caching or API equivalent.
		$wpdb->query( 'ROLLBACK' );

		$this->restore_hooks();

		set_current_screen( 'front' );
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

	/**
	 * Inserts a revision for the tests to hang props off.
	 *
	 * @param string $message The commit message.
	 * @return void
	 */
	protected function seed_revision( string $message ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Seeding the plugin's own table, which has no API or caching layer.
		$wpdb->insert(
			$this->svn['rev_table'],
			array(
				'id'      => self::REVISION,
				'author'  => 'admin',
				'date'    => '2026-07-01 12:00:00',
				'summary' => 'Seeded revision.',
				'message' => $message,
				'branch'  => 'trunk',
				'version' => '7.1',
			)
		);
	}

	/**
	 * Inserts props against the seeded revision.
	 *
	 * @param array $props Map of prop name to user ID, or null when unresolved.
	 * @return void
	 */
	protected function seed_props( array $props ): void {
		global $wpdb;

		foreach ( $props as $prop_name => $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Seeding the plugin's own table, which has no API or caching layer.
			$wpdb->insert(
				$this->svn['props_table'],
				array(
					'revision'  => self::REVISION,
					'prop_name' => $prop_name,
					'user_id'   => $user_id,
				)
			);
		}
	}

	/**
	 * Builds the item the list table renders, without going through the database.
	 *
	 * @param string $message The commit message.
	 * @param array  $props   Map of prop name to user ID, or null when unresolved.
	 * @return object
	 */
	protected function make_item( string $message, array $props ): object {
		return (object) array(
			'id'      => self::REVISION,
			'author'  => 'admin',
			'date'    => '2026-07-01 12:00:00',
			'summary' => 'Seeded revision.',
			'message' => $message,
			'branch'  => 'trunk',
			'version' => '7.1',
			'props'   => $props,
		);
	}

	/**
	 * Renders a single column of the commits list table.
	 *
	 * @param object $item   The revision being rendered.
	 * @param string $column The column name.
	 * @return string
	 */
	protected function render_column( object $item, string $column ): string {
		$table = new WordPressdotorg\Trac\Watcher\Commits_List_Table( $this->svn );

		return $table->column_default( $item, $column );
	}

	/**
	 * Asserts that a value appears in the output as text rather than as live markup.
	 *
	 * Escaping only rewrites the angle brackets and the ampersand, so asserting on
	 * the payload as a whole would match the escaped form too and pass either way.
	 *
	 * @param string $html The rendered output.
	 * @return void
	 */
	protected function assertMarkupIsInert( string $html ): void {
		$this->assertStringNotContainsString( '<img', $html, 'The stored value rendered as a live tag.' );
		$this->assertStringContainsString( '&lt;img', $html, 'The stored value is missing, so the escaping assertion above would pass vacuously.' );
	}
}
