<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package theme-directory
 */

namespace WordPressdotorg\Theme_Directory\Tests;

if ( 'cli' !== php_sapi_name() ) {
	return;
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$pos = stripos( __FILE__, '/src/wp-content/plugins/' );

	if ( false !== $pos ) {
		// Installed in a src checkout.
		$_tests_dir = substr( __FILE__, 0, $pos ) . '/tests/phpunit/';
	} elseif ( file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
		// wp-env test directory.
		$_tests_dir = '/wordpress-phpunit/';
	} else {
		// Assume a temp directory path.
		$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
	}
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find {$_tests_dir}/includes/functions.php\n" );
	exit( 1 );
}

// Set polyfills path if available (required by WP test suite).
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function manually_load_plugin() {
	require_once dirname( __DIR__ ) . '/theme-directory.php';

	/*
	 * These classes are only included on demand at runtime (on upload, or when
	 * serving an API request); load them up front for the tests.
	 */
	require_once dirname( __DIR__ ) . '/class-wporg-themes-upload.php';
	require_once dirname( __DIR__ ) . '/class-themes-api.php';
}
tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
