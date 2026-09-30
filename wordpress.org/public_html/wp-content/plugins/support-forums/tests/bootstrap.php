<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Forums\Tests;

if ( 'cli' !== php_sapi_name() ) {
	return;
}

// phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed -- Test harness console output, not a web request.
ini_set( 'display_errors', 'on' );
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting,WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- Test harness; failures must be visible.
error_reporting( E_ALL );

/**
 * Locate the WordPress PHPUnit suite.
 *
 * Honours WP_TESTS_DIR, then a src checkout, then the path wp-env mounts, and
 * finally the conventional temporary directory.
 *
 * @return string Path to the test suite, with a trailing slash.
 */
function locate_tests_dir(): string {
	// No WordPress function is available yet; this runs before its bootstrap.
	$tests_dir = getenv( 'WP_TESTS_DIR' );
	if ( $tests_dir ) {
		return rtrim( $tests_dir, '/\\' ) . '/';
	}

	$src_position = stripos( __FILE__, '/src/wp-content/plugins/' );
	if ( false !== $src_position ) {
		return substr( __FILE__, 0, $src_position ) . '/tests/phpunit/';
	}

	if ( file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
		return '/wordpress-phpunit/';
	}

	return rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
}

/*
 * The suite runs single-site, while WPORG_SUPPORT_FORUMS_BLOGID is set to 1 in
 * .wp-env.test.json. Anything guarded on that constant together with
 * is_multisite() therefore takes the single-site path here, so a test can only
 * prove the behaviour, never that it is scoped to the forums blog. Proving the
 * latter needs a multisite bootstrap and a second blog.
 */

$_tests_dir = locate_tests_dir();

if ( ! file_exists( $_tests_dir . 'includes/functions.php' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test harness console output, not HTML.
	echo "Could not find {$_tests_dir}includes/functions.php\n";
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . 'vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . 'vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . 'includes/functions.php';

/**
 * Load bbPress and then the plugin under test.
 *
 * The plugin returns early unless bbPress is already loaded, so the order
 * matters here.
 *
 * @return void
 */
function manually_load_plugin(): void {
	$bbpress = WP_PLUGIN_DIR . '/bbpress/bbpress.php';
	if ( file_exists( $bbpress ) ) {
		require_once $bbpress;
	}

	require_once dirname( __DIR__ ) . '/support-forums.php';
}
tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\manually_load_plugin' );

require $_tests_dir . 'includes/bootstrap.php';
