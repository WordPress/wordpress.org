<?php

use Wporg\TranslationEvents\Upgrade;

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

function _glotpress_path( string $path ): string {
	$plugins_dir = dirname( __DIR__, 2 );

	// GlotPress may be installed as 'glotpress' or 'GlotPress' depending on the environment.
	foreach ( array( 'glotpress', 'GlotPress' ) as $dir_name ) {
		if ( is_dir( $plugins_dir . '/' . $dir_name ) ) {
			return $plugins_dir . '/' . $dir_name . '/' . $path;
		}
	}

	// Fallback to lowercase.
	return $plugins_dir . '/glotpress/' . $path;
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

// Fall back to the polyfills installed into the test suite itself, as CI does.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

if ( ! file_exists( "$_tests_dir/includes/functions.php" ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, are you running the tests inside wp-env?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "$_tests_dir/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require_once _glotpress_path( '/tests/phpunit/includes/loader.php' );
	require_once dirname( __DIR__ ) . '/wporg-gp-translation-events.php';
	Upgrade::upgrade_if_needed();
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

global $wp_tests_options;
$wp_tests_options['permalink_structure'] = '/%postname%';

// Start up the WP testing environment.
require "$_tests_dir/includes/bootstrap.php";

// Require GlotPress test code.
require_once _glotpress_path( '/tests/phpunit/lib/testcase.php' );
require_once _glotpress_path( '/tests/phpunit/lib/testcase-route.php' );
require_once _glotpress_path( '/tests/phpunit/lib/testcase-request.php' );

// Require our own test code.
require_once __DIR__ . '/base-test.php';
require_once __DIR__ . '/lib/event-factory.php';
require_once __DIR__ . '/lib/stats-factory.php';
require_once __DIR__ . '/lib/translation-factory.php';
