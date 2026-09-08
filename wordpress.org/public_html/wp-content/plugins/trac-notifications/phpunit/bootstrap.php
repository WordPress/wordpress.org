<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package trac-notifications
 */

declare( strict_types = 1 );

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
 * Loads the component pages class as it runs on make.wordpress.org/core.
 *
 * The plugin's main file needs a Trac API key and only does anything on the
 * Make network, so the class under test is loaded on its own and told which
 * site it is on for the duration of its constructor.
 */
function wporg_trac_components_manually_load_plugin() {
	require_once dirname( __DIR__ ) . '/trac-components.php';

	$make_core = function () {
		return 'https://make.wordpress.org/core';
	};

	add_filter( 'home_url', $make_core );
	$GLOBALS['wporg_trac_components'] = new Make_Core_Trac_Components( null );
	remove_filter( 'home_url', $make_core );
}
tests_add_filter( 'muplugins_loaded', 'wporg_trac_components_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// The save path under test is wp-admin's edit_post().
require_once ABSPATH . 'wp-admin/includes/admin.php';

// Include the base test case.
require __DIR__ . '/includes/testcase.php';
