<?php
/**
 * Base test case for the handbook plugin.
 *
 * WordPress ships `WP_UnitTestCase`, but its `set_up()` calls
 * `PHPUnit\Util\Test::parseTestMethodAnnotations()`, which PHPUnit 10 removed.
 * WordPress has no PHPUnit 10+ compatible release, so extending it fails every
 * test before a single assertion runs.
 *
 * This class extends PHPUnit's own `TestCase` and reimplements only the three
 * pieces of `WP_UnitTestCase` this suite actually uses -- `factory()`,
 * `go_to()` and `assertQueryTrue()` -- plus the per-test isolation those rely
 * on. Everything else the tests call is a stock PHPUnit assertion.
 *
 * @package handbook
 */

use PHPUnit\Framework\TestCase;

/**
 * Provides WordPress fixtures and query helpers on top of PHPUnit's TestCase.
 */
abstract class WPorg_Handbook_TestCase extends TestCase {

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
	protected static $hooks_saved = [];

	/**
	 * Fixture factory, for tests that reach for `$this->factory` rather than
	 * calling `$this->factory()`. Both forms are supported.
	 *
	 * @var WP_UnitTest_Factory
	 */
	protected $factory;

	/**
	 * Registered sidebars captured at the start of each test.
	 *
	 * Registering a sidebar writes to a global that nothing else resets, so a
	 * test that re-registers one would otherwise change what later tests see.
	 *
	 * @var array
	 */
	protected $registered_sidebars = [];

	/**
	 * Returns the fixture factory, creating it on first use.
	 *
	 * Mirrors `WP_UnitTestCase::factory()` so tests can keep calling
	 * `$this->factory()->post->create()`.
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

		set_time_limit( 0 );

		$this->factory = static::factory();

		/*
		 * Captured once, before the first test, so every test is restored to
		 * the same baseline. This matches how WP_UnitTestCase behaves: the
		 * snapshot is taken a single time rather than per test.
		 */
		if ( ! self::$hooks_saved ) {
			$this->backup_hooks();
		}

		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];

		$this->registered_sidebars = $GLOBALS['wp_registered_sidebars'] ?? [];

		self::flush_cache();

		$this->start_transaction();
	}

	/**
	 * Rolls back database and global state after each test.
	 */
	public function tearDown(): void {
		global $wpdb, $wp, $wp_query, $wp_the_query;

		$wpdb->query( 'ROLLBACK' );

		remove_filter( 'query', [ $this, 'create_temporary_tables' ] );
		remove_filter( 'query', [ $this, 'drop_temporary_tables' ] );

		// Reset the query globals the way wp-settings.php first sets them up.
		$wp_the_query = new WP_Query();
		$wp_query     = $wp_the_query;
		$wp           = new WP();

		$post_globals = [ 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' ];
		foreach ( $post_globals as $global ) {
			$GLOBALS[ $global ] = null;
		}

		/*
		 * Reset the admin screen globals set by WP_Screen::set_current_screen(),
		 * so a test that calls set_current_screen() does not decide what screen
		 * the next test starts on.
		 */
		foreach ( [ 'current_screen', 'taxnow', 'typenow' ] as $global ) {
			$GLOBALS[ $global ] = null;
		}

		$GLOBALS['wp_registered_sidebars'] = $this->registered_sidebars;

		$this->restore_hooks();

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Wraps the test in a transaction so database writes never persist.
	 *
	 * The two `query` filters rewrite table creation to be temporary, so that
	 * DDL issued mid-test does not implicitly commit the transaction.
	 */
	protected function start_transaction() {
		global $wpdb;

		$wpdb->query( 'SET autocommit = 0;' );
		$wpdb->query( 'START TRANSACTION;' );

		add_filter( 'query', [ $this, 'create_temporary_tables' ] );
		add_filter( 'query', [ $this, 'drop_temporary_tables' ] );
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
		self::$hooks_saved['wp_filter'] = [];

		foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook_object ) {
			self::$hooks_saved['wp_filter'][ $hook_name ] = clone $hook_object;
		}

		foreach ( [ 'wp_actions', 'wp_filters', 'wp_current_filter' ] as $key ) {
			self::$hooks_saved[ $key ] = $GLOBALS[ $key ] ?? [];
		}
	}

	/**
	 * Restores the hook globals from the snapshot.
	 *
	 * Without this, a filter or action added during one test would still be
	 * attached when the next one runs.
	 */
	protected function restore_hooks() {
		if ( ! isset( self::$hooks_saved['wp_filter'] ) ) {
			return;
		}

		$GLOBALS['wp_filter'] = [];

		foreach ( self::$hooks_saved['wp_filter'] as $hook_name => $hook_object ) {
			$GLOBALS['wp_filter'][ $hook_name ] = clone $hook_object;
		}

		foreach ( [ 'wp_actions', 'wp_filters', 'wp_current_filter' ] as $key ) {
			if ( isset( self::$hooks_saved[ $key ] ) ) {
				$GLOBALS[ $key ] = self::$hooks_saved[ $key ];
			}
		}
	}

	/**
	 * Empties the object cache and restores its group registrations.
	 */
	public static function flush_cache() {
		global $wp_object_cache;

		wp_cache_flush_runtime();

		if ( is_object( $wp_object_cache ) && method_exists( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset();
		}

		wp_cache_flush();

		wp_cache_add_global_groups(
			[
				'blog-details',
				'blog-id-cache',
				'blog-lookup',
				'blog_meta',
				'global-posts',
				'networks',
				'network-queries',
				'sites',
				'site-details',
				'site-options',
				'site-queries',
				'site-transient',
				'theme_files',
				'rss',
				'users',
				'user-queries',
				'user_meta',
				'useremail',
				'userlogins',
				'userslugs',
			]
		);

		wp_cache_add_non_persistent_groups( [ 'counts', 'plugins', 'theme_json' ] );
	}

	/**
	 * Runs a front end request for the given URL and populates the main query.
	 *
	 * @param string $url The URL to request.
	 */
	public function go_to( $url ) {
		/*
		 * WP and WP_Query pull parameters from globals and superglobals, so
		 * everything they read has to be cleared before the request is rerun.
		 */
		$_GET  = [];
		$_POST = [];

		$globals = [ 'query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow', 'current_screen' ];
		foreach ( $globals as $global ) {
			if ( isset( $GLOBALS[ $global ] ) ) {
				unset( $GLOBALS[ $global ] );
			}
		}

		$parts = parse_url( $url );
		if ( isset( $parts['scheme'] ) ) {
			$req = $parts['path'] ?? '';
			if ( isset( $parts['query'] ) ) {
				$req .= '?' . $parts['query'];
				parse_str( $parts['query'], $_GET );
			}
		} else {
			$req = $url;
		}

		if ( ! isset( $parts['query'] ) ) {
			$parts['query'] = '';
		}

		$_SERVER['REQUEST_URI'] = $req;
		unset( $_SERVER['PATH_INFO'] );

		self::flush_cache();

		unset( $GLOBALS['wp_query'], $GLOBALS['wp_the_query'] );
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];

		$public_query_vars  = $GLOBALS['wp']->public_query_vars;
		$private_query_vars = $GLOBALS['wp']->private_query_vars;

		$GLOBALS['wp']                     = new WP();
		$GLOBALS['wp']->public_query_vars  = $public_query_vars;
		$GLOBALS['wp']->private_query_vars = $private_query_vars;

		_cleanup_query_vars();

		$GLOBALS['wp']->main( $parts['query'] );
	}

	/**
	 * Asserts that the given query conditionals are true and all others false.
	 *
	 * @param string ...$prop The conditionals expected to be true.
	 */
	public function assertQueryTrue( ...$prop ) {
		global $wp_query;

		$all = [
			'is_404',
			'is_admin',
			'is_archive',
			'is_attachment',
			'is_author',
			'is_category',
			'is_comment_feed',
			'is_date',
			'is_day',
			'is_embed',
			'is_feed',
			'is_front_page',
			'is_home',
			'is_privacy_policy',
			'is_month',
			'is_page',
			'is_paged',
			'is_post_type_archive',
			'is_posts_page',
			'is_preview',
			'is_robots',
			'is_favicon',
			'is_sitemap',
			'is_search',
			'is_single',
			'is_singular',
			'is_tag',
			'is_tax',
			'is_time',
			'is_trackback',
			'is_year',
		];

		foreach ( $prop as $true_thing ) {
			$this->assertContains( $true_thing, $all, "Unknown conditional: {$true_thing}." );
		}

		$passed  = true;
		$message = '';

		foreach ( $all as $query_thing ) {
			$result = is_callable( $query_thing ) ? call_user_func( $query_thing ) : $wp_query->$query_thing;

			if ( in_array( $query_thing, $prop, true ) ) {
				if ( ! $result ) {
					$message .= $query_thing . ' is false but is expected to be true. ' . PHP_EOL;
					$passed   = false;
				}
			} elseif ( $result ) {
				$message .= $query_thing . ' is true but is expected to be false. ' . PHP_EOL;
				$passed   = false;
			}
		}

		if ( ! $passed ) {
			$this->fail( $message );
		}
	}
}
