<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package wporg-badge-management
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir && file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
	// wp-env mounts the WordPress test suite here.
	$_tests_dir = '/wordpress-phpunit/';
} elseif ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find the WordPress test suite. Set WP_TESTS_DIR to its path.' . PHP_EOL;
	exit( 1 );
}

// Set polyfills path if available (required by WP test suite).
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

/*
 * The capability mapping under test turns on site membership, and
 * is_user_member_of_blog() returns true unconditionally when ! is_multisite().
 */
define( 'WP_TESTS_MULTISITE', true );

/*
 * Load o2 Posting Access alongside the plugin, so its capability grant for
 * non-members is in play during the tests. Its init() bails without this
 * constant; nothing it does calls into o2.
 */
define( 'O2__PLUGIN_LOADED', true );

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually loads the plugin being tested, and o2 Posting Access.
 */
function wporg_badge_management_manually_load_plugins() {
	require_once dirname( __DIR__, 2 ) . '/wporg-o2-posting-access/wporg-o2-posting-access.php';
	require_once dirname( __DIR__ ) . '/index.php';
}
tests_add_filter( 'muplugins_loaded', 'wporg_badge_management_manually_load_plugins' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Include the base test case.
require __DIR__ . '/includes/testcase.php';
