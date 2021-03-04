<?php
/**
 * PHPUnit bootstrap file
 *
 * @package handbook
 */

ini_set( 'display_errors', 'on' );
error_reporting( E_ALL );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Check if installed in a src checkout.
if ( ! $_tests_dir && false !== ( $pos = stripos( __FILE__, '/src/wp-content/plugins/' ) ) ) {
	$_tests_dir = substr( __FILE__, 0, $pos ) . '/tests/phpunit/';
}
// Elseif no path yet, assume a temp directory path.
elseif ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

define( 'WPORG_HANDBOOK_PLUGIN_DIR', dirname( __DIR__ ) );

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require WPORG_HANDBOOK_PLUGIN_DIR . '/handbook.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Include utility functions.
require __DIR__ . '/includes/utils.php';