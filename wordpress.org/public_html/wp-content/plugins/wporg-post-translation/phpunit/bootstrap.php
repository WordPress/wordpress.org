<?php
/**
 * PHPUnit bootstrap file for wporg-post-translation.
 */

ini_set( 'display_errors', 'on' );
error_reporting( E_ALL );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Check if installed in a src checkout.
if ( ! $_tests_dir && false !== ( $pos = stripos( __FILE__, '/src/wp-content/plugins/' ) ) ) {
	$_tests_dir = substr( __FILE__, 0, $pos ) . '/tests/phpunit/';
}
// Check for wp-env test directory.
elseif ( ! $_tests_dir && file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
	$_tests_dir = '/wordpress-phpunit/';
}
// Elseif no path yet, assume a temp directory path.
elseif ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	exit( 1 );
}

// Set polyfills path if available.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

define( 'WPORG_POST_TRANSLATION_PLUGIN_DIR', dirname( __DIR__ ) );

/**
 * Manually load the plugins being tested.
 */
function _manually_load_plugins() {
	// Load the bridge dependency.
	$bridge_path = WPORG_POST_TRANSLATION_PLUGIN_DIR . '/../glotpress-translate-bridge/glotpress-translate-bridge.php';
	if ( file_exists( $bridge_path ) ) {
		require $bridge_path;
	}

	require WPORG_POST_TRANSLATION_PLUGIN_DIR . '/wporg-post-translation.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
