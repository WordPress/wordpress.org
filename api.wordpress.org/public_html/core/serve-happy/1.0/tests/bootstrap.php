<?php
namespace WordPressdotorg\API\Serve_Happy;

if ( function_exists( 'xdebug_disable' ) ) {
	xdebug_disable();
}

// PHP 6+ Compatibility.
if ( class_exists( '\PHPUnit\Runner\Version' ) && version_compare( \PHPUnit\Runner\Version::id(), '6.0', '>=' ) ) {
	class_alias( '\PHPUnit\Framework\TestCase', 'PHPUnit_Framework_TestCase' );
}

// Error Output handler for the API.
function bail( $code, $message, $status = 400, $http_code_text = '' ) {
	return compact( 'code', 'message', 'status' );
}

// When running on WordPress.org, pull in the global defines, required for WPORG_* constants.
$api_init_file = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/init.php';
if ( file_exists( $api_init_file ) ) {
	include $api_init_file;
}

require dirname( __DIR__ ) . '/include.php';
if ( defined( 'WPORGPATH' ) ) {
	// Running on WordPress.org, include the main mu-plugins config
	require WPORGPATH . 'wp-content/mu-plugins/pub/servehappy-config.php';
} else {
	// Assume meta-environment.
	require dirname( dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) ) ) . '/wordpress.org/public_html/wp-content/mu-plugins/pub/servehappy-config.php';
}
