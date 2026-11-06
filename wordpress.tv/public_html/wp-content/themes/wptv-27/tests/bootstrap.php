<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WordPressTV
 */

declare( strict_types = 1 );

namespace WordPressTV\Tests;

if ( 'cli' !== php_sapi_name() ) {
	return;
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// wp-env's test directory, else the temporary directory the core install script uses.
if ( ! $_tests_dir && file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
	$_tests_dir = '/wordpress-phpunit';
} elseif ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit';
}
$_tests_dir = rtrim( $_tests_dir, '/\\' );

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test harness console output, not HTML.
	echo "Could not find $_tests_dir/includes/functions.php\n";
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

// Run the tests with the theme active.
$GLOBALS['wp_tests_options'] = array(
	'template'            => 'wptv-27',
	'stylesheet'          => 'wptv-27',
	'permalink_structure' => '/%year%/%monthnum%/%day%/%postname%/',
);

require $_tests_dir . '/includes/bootstrap.php';
