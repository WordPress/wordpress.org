<?php

namespace WordPressdotorg\Plugin_Directory\Tests;

// Require composer dependencies.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( 'cli' !== php_sapi_name() ) {
	return;
}

$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';


/**
 * Manually load the plugin being tested.
 */
function manually_load_plugin() {
	require_once dirname( __FILE__ ) . '/../plugin-directory.php';
	// Also load Jetpack, needed to test some features such as search
	require_once dirname( __FILE__ ) . '/../../jetpack/jetpack.php';
}
if ( function_exists( 'tests_add_filter' ) ) {
	tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\manually_load_plugin' );
}

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
