<?php
/**
 * PHPUnit bootstrap file for wporg-post-translation.
 */

ini_set( 'display_errors', 'on' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
error_reporting( E_ALL );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Check if installed in a src checkout.
if ( ! $_tests_dir && false !== ( $pos = stripos( __FILE__, '/src/wp-content/plugins/' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
	$_tests_dir = substr( __FILE__, 0, $pos ) . '/tests/phpunit/';
} elseif ( ! $_tests_dir && file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
	// Check for wp-env test directory.
	$_tests_dir = '/wordpress-phpunit/';
} elseif ( ! $_tests_dir ) {
	// Assume a temp directory path.
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
 * GlotPress requires pretty permalinks. Set them before WP fully loads.
 */
tests_add_filter(
	'pre_option_permalink_structure',
	function () {
		return '/%postname%/';
	}
);

/**
 * Manually load the plugins being tested.
 */
function _manually_load_plugins() {
	// Load GlotPress if available.
	foreach ( [ 'GlotPress/glotpress.php', 'glotpress/glotpress.php' ] as $gp_file ) {
		if ( file_exists( WP_PLUGIN_DIR . '/' . $gp_file ) ) {
			require WP_PLUGIN_DIR . '/' . $gp_file;
			break;
		}
	}

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

// If GlotPress is loaded, install its database tables for tests.
if ( class_exists( 'GP' ) && function_exists( 'gp_schema_get' ) ) {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( gp_schema_get() );
}
