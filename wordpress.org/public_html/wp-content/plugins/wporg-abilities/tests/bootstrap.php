<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WordPressdotorg\Abilities
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Tests;

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

// HelpScout's client sends nothing without credentials; the tests answer its API.
define( 'HELPSCOUT_APP_ID', 'test-app' );
define( 'HELPSCOUT_APP_SECRET', 'test-secret' );

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin directory, which the plugin directory's abilities read from, and their classes.
 *
 * Not the plugin itself, which needs the MCP adapter to register anything.
 *
 * @return void
 */
function manually_load_plugins(): void {
	require_once dirname( __DIR__, 2 ) . '/plugin-directory/plugin-directory.php';
	require_once dirname( __DIR__ ) . '/class-autoloader.php';

	\WordPressdotorg\Abilities\Autoloader\register_class_path( 'WordPressdotorg\Abilities', dirname( __DIR__ ) );
}
tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\manually_load_plugins' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
