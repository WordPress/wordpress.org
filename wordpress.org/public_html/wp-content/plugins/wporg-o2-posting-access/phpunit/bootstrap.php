<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package wporg-o2-posting-access
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
 * This suite only has meaning on multisite. is_user_member_of_blog() returns
 * true unconditionally when ! is_multisite(), which makes both the capability
 * grant and user_can_publish() no-ops, so every assertion here would pass
 * without exercising a single line of the plugin.
 */
define( 'WP_TESTS_MULTISITE', true );

/*
 * The plugin's init() bails without this. Nothing it does calls into o2, so the
 * constant is all that is needed to exercise the hooks under test.
 */
define( 'O2__PLUGIN_LOADED', true );

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually loads the plugin being tested.
 */
function wporg_o2_posting_access_manually_load_plugin() {
	require_once dirname( __DIR__ ) . '/wporg-o2-posting-access.php';
}
tests_add_filter( 'muplugins_loaded', 'wporg_o2_posting_access_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Include the base test case.
require __DIR__ . '/includes/testcase.php';
