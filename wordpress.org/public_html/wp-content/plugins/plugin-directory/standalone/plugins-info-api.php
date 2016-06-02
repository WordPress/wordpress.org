<?php
namespace WordPressdotorg\Plugin_Directory;
die();

/**
 * This is an example of how the /plugins/info/1.1/ endpoint may be implemented.
 * It makes a lot of assumptions about the environment under which WordPress might be installed in.
 */

// Assume a standard wp-content/plugins/ plugin directory.
define( 'WPORGPATH', dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/' );

$_REQUEST = array(
	'method' => 'plugin_information',
	'request' => array(
		'slug' => 'hello-dolly',
		'fields' => 'active_installs',
	)
);

$format  = 'json'; // json, jsonp, xml, or php
$method  = $_REQUEST['method'];
$request = $_REQUEST['request'];

require __DIR__ . '/class-plugins-info-api.php';
require __DIR__ . '/class-plugins-info-api-request.php';

$api = new Plugins_Info_API( $format );

if ( ! function_exists( 'wp_cache_init' ) ) {
	// This script requires an object cache to be loaded
	$api->load_wordpress();
} else {
	wp_cache_init();
	// Logic for configuring the object cache here..
}

$api->handle_request( $method, $request );

