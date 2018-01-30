<?php
// Load WordPress, pretend we're the Theme Directory in order to avoid having to switch sites after loading.
$_SERVER['HTTP_HOST'] = 'wordpress.org';
$_SERVER['REQUEST_URI'] = '/themes/';

require dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-init.php';

// Set up action and request information.
if ( defined( 'JSON_RESPONSE' ) && JSON_RESPONSE ) {
	$request = isset( $_REQUEST['request'] ) ? (object) wp_unslash( $_REQUEST['request'] ) : '';
	$format = 'json';
} else {
	$post_request = isset( $_POST['request'] ) ? urldecode( wp_unslash( $_POST['request'] ) ) : '';
	if ( $post_request && ( preg_match( '~[;{}][OC]:\+?\d+:~', $post_request ) || 0 !== strpos( $post_request, 'O:8:"stdClass":' ) ) ) {
		die( 'error' );
	}

	$request = unserialize( $post_request );

	$format = 'php';
}

$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

// Serve an API request.
echo wporg_themes_query_api( $action, $request, $format );
