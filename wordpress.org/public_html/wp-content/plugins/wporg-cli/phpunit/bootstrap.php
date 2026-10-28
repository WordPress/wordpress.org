<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package wporg-cli
 */

declare( strict_types = 1 );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
$_src_pos   = stripos( __FILE__, '/src/wp-content/plugins/' );

if ( ! $_tests_dir && false !== $_src_pos ) {
	// Installed in a src checkout.
	$_tests_dir = substr( __FILE__, 0, $_src_pos ) . '/tests/phpunit/';
} elseif ( ! $_tests_dir && file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
	// The wp-env test directory.
	$_tests_dir = '/wordpress-phpunit/';
} elseif ( ! $_tests_dir ) {
	// No path yet, so assume a temp directory path.
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib/tests/phpunit/';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WordPress is not loaded yet, so WP_Filesystem is unavailable.
	fwrite( STDERR, "Could not find {$_tests_dir}/includes/functions.php\n" );
	exit( 1 );
}

// Set polyfills path if available, as the WP test suite requires it.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

// Give access to the tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

define( 'WPORG_CLI_PLUGIN_DIR', dirname( __DIR__ ) );

/**
 * Manually loads the plugin being tested.
 */
function _manually_load_plugin(): void {
	require WPORG_CLI_PLUGIN_DIR . '/wporg-cli.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Registers the post type the importer operates on.
 *
 * The handbook post type is owned by the separate handbook plugin. Registering a
 * minimal stand-in keeps these tests independent of it, since the importer only
 * cares that the post type is named `handbook`.
 */
function _register_handbook_post_type(): void {
	register_post_type(
		'handbook',
		array(
			'public'   => true,
			'supports' => array( 'title', 'editor', 'author', 'custom-fields' ),
		)
	);
}
tests_add_filter( 'init', '_register_handbook_post_type' );

require_once __DIR__ . '/stubs/class-wpcom-ghf-markdown-parser.php';

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// wp_delete_user() lives in the admin includes, which tests do not load by default.
require_once ABSPATH . 'wp-admin/includes/user.php';
