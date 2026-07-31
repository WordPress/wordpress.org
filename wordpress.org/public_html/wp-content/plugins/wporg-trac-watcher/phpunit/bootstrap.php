<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package wporg-trac-watcher
 */

declare( strict_types=1 );

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

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually loads the plugin being tested.
 */
function wporg_trac_watcher_manually_load_plugin() {
	require_once dirname( __DIR__ ) . '/trac-watch.php';
}
tests_add_filter( 'muplugins_loaded', 'wporg_trac_watcher_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

/*
 * trac-watch.php only pulls the admin UI in under WP_ADMIN, and list-table.php
 * needs WP_List_Table, so load the admin includes and then the UI directly.
 */
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once dirname( __DIR__ ) . '/admin/ui.php';
require_once dirname( __DIR__ ) . '/admin/reports-page.php';

/*
 * The plugin's own tables. Activation hooks don't run in the test suite, so
 * call the same function register_activation_hook() would.
 */
WordPressdotorg\Trac\Watcher\create_tables();

// Include the base test case.
require __DIR__ . '/includes/testcase.php';
