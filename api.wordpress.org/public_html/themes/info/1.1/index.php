<?php

header( 'Access-Control-Allow-Origin: *' );

if ( isset( $_GET['callback'] ) ) {
	$callback = preg_replace( '/[^a-z0-9_]/i', '', $_GET['callback'] );
} else {
	$callback = false;
}

define( 'JSON_RESPONSE', true );

if ( ! defined( 'THEMES_API_VERSION' ) ) {
	define( 'THEMES_API_VERSION', '1.1' );
}

ob_start();
require dirname( __DIR__ ) . '/1.0/index.php';
$response = ob_get_clean();

if ( $callback ) {
	header( 'Content-Type: text/javascript; charset=UTF-8' );
	echo "$callback($response);";
} else {
	header( 'Content-Type: application/json; charset=UTF-8' );
	echo $response;
}
