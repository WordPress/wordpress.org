<?php
/**
 * PHPUnit bootstrap file for the WordPress.org SSO suite.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

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

// Required by the WP test suite.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $_tests_dir . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_tests_dir . '/vendor/yoast/phpunit-polyfills' );
}

/*
 * Constants from the private dotorg config; the blocking and salt filters are
 * no-ops without them.
 *
 * The blog ID is load-bearing for the account-blocking tests and cannot do its
 * job here: this suite runs single-site, where `WP_User::for_site()` resolves to
 * the same capability row whatever ID it is given. Those tests therefore verify
 * that a `bbp_blocked` capability is honoured, not that it is honoured *on the
 * support forums blog*. Proving the latter needs a multisite bootstrap and a
 * second blog to hold the capability.
 */
define( 'WPORG_SUPPORT_FORUMS_BLOGID', 1 );
define( 'WPORG_SSO_SALT', 'wporg-sso-phpunit-salt' );

require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the SSO classes and the stubs standing in for the two-factor stack.
 *
 * The SSO classes only register hooks when `$_SERVER['HTTP_HOST']` is set, so
 * loading them under CLI leaves the global hook state untouched. Every test
 * instantiates its own copy against a host of its choosing instead.
 *
 * @return void
 */
function wporg_sso_manually_load_plugin(): void {
	require_once __DIR__ . '/includes/class-wporg-sso-two-factor-state.php';
	require_once __DIR__ . '/includes/class-two-factor-core.php';
	require_once __DIR__ . '/includes/class-two-factor-backup-codes.php';
	require_once __DIR__ . '/includes/two-factor-function-stubs.php';

	require_once dirname( __DIR__ ) . '/wp-plugin.php';
	require_once dirname( __DIR__ ) . '/bb-plugin.php';

	// Registered by the constructor on production; the redemption records are keyless without it.
	wp_cache_add_global_groups( WPOrg_SSO::REMOTE_TOKEN_CACHE_GROUP );
}
tests_add_filter( 'muplugins_loaded', 'wporg_sso_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// Must follow the bootstrap: the doubles extend the SSO classes it loads.
require __DIR__ . '/includes/class-wporg-sso-redirect-exception.php';
require __DIR__ . '/includes/class-wporg-sso-die-exception.php';
require __DIR__ . '/includes/trait-wporg-sso-captures-redirects.php';
require __DIR__ . '/includes/class-wporg-sso-test-double.php';
require __DIR__ . '/includes/class-bb-wporg-sso-test-double.php';
require __DIR__ . '/includes/class-wporg-sso-tos-pending-double.php';
require __DIR__ . '/includes/class-wporg-sso-live-redirect-double.php';
require __DIR__ . '/includes/class-wporg-sso-unreachable-cache.php';
require __DIR__ . '/includes/testcase.php';
