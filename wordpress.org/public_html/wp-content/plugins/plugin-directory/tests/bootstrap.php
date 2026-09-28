<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package plugin-directory
 */

namespace WordPressdotorg\Plugin_Directory\Tests;

if ( 'cli' !== php_sapi_name() ) {
	return;
}

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
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test harness console output, not HTML.
	echo "Could not find $_tests_dir/includes/functions.php\n";
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
	require_once dirname( __DIR__ ) . '/plugin-directory.php';
}
tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\manually_load_plugin' );

/**
 * Matches WordPress's test bcrypt cost for tests using PHPUnit's base TestCase.
 *
 * @param array  $options   Password hashing options.
 * @param string $algorithm Password hashing algorithm.
 * @return array Password hashing options.
 */
function wp_hash_password_options( array $options, string $algorithm ): array {
	if ( PASSWORD_BCRYPT === $algorithm ) {
		$options['cost'] = 5;
	}

	return $options;
}
tests_add_filter( 'wp_hash_password_options', __NAMESPACE__ . '\wp_hash_password_options', 1, 2 );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
