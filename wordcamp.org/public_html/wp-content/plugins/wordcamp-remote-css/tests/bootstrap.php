<?php

namespace WordCamp\RemoteCSS;

$core_tests_directory = getenv( 'WP_TESTS_DIR' );

if ( ! $core_tests_directory ) {
	echo "\nPlease set the WP_TESTS_DIR environment variable to the folder where WordPress' PHPUnit tests live --";
	echo "\ne.g., export WP_TESTS_DIR=/srv/www/wordpress-develop/tests/phpunit\n";

	return;
}

require_once( $core_tests_directory . '/includes/functions.php' );

/**
 * Load the plugins that we'll need to be active for the tests
 */
function manually_load_plugin() {
	$_SERVER['PHP_SELF'] = admin_url( 'themes.php?page=remote-css' );

	/*
	 * Defining WP_ADMIN is so that wordcamp-remote-css/bootstrap.php will load the app/*.php files.
	 * It may need to be refactored if we add tests for output-cached-css.php.
	 * We shouldn't really be using PHPUnit for functional tests, though, so it'd be better to switch those tests
	 * to Codeception or Behat.
	 */
 	define( 'WP_ADMIN',          true );
	define( 'JETPACK_DEV_DEBUG', true );

	require_once( dirname(                   __DIR__ )     . '/bootstrap.php'                 );
	require_once( dirname( dirname(          __DIR__ ) )   . '/jetpack/jetpack.php'           );

	// Some of the sanitization lives here because it runs for both Custom CSS and Remote CSS
	require_once( dirname( dirname( dirname( __DIR__ ) ) ) . '/mu-plugins/jetpack-tweaks/css-sanitization.php' );
}
tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\manually_load_plugin' );

require $core_tests_directory . '/includes/bootstrap.php';

\Jetpack::activate_module( 'custom-css', false, false );
