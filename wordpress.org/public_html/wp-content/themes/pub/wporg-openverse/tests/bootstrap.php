<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WordPressdotorg\Openverse\Theme\Tests
 */

if ( 'cli' !== php_sapi_name() ) {
	return;
}

// phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed -- WP_DEBUG_DISPLAY is not available before the test suite boots.
ini_set( 'display_errors', 'on' );
error_reporting( E_ALL );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_src_position = stripos( __FILE__, '/src/wp-content/themes/' );

	if ( false !== $_src_position ) {
		// Installed in a wordpress-develop src checkout.
		$_tests_dir = substr( __FILE__, 0, $_src_position ) . '/tests/phpunit/';
	} elseif ( file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
		// Running in the wp-env test container.
		$_tests_dir = '/wordpress-phpunit/';
	} else {
		// Otherwise assume the conventional temp directory path.
		$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
	}
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find ' . $_tests_dir . "/includes/functions.php\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Terminal output, not markup.
	exit( 1 );
}

// Set polyfills path if available (required by WP test suite).
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the theme under test.
 *
 * The file is required rather than the theme activated. The functions under
 * test are plain functions, and switching to the child theme would pull in the
 * wporg parent, which this environment does not install.
 */
function wporg_openverse_load_theme() {
	require_once __DIR__ . '/locales-stub.php';
	require_once dirname( __DIR__ ) . '/functions.php';
}
tests_add_filter( 'muplugins_loaded', 'wporg_openverse_load_theme' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
