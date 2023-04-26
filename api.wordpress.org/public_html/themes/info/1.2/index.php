<?php

// Version 1.2+ only accepts GET requests
if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	header( $_SERVER['SERVER_PROTOCOL'] . ' 405 Method not allowed' );
	header( 'Allow: GET' );
	header( 'Content-Type: text/plain' );

	die( 'This API only serves GET requests.' );
}

if ( ! defined( 'THEMES_API_VERSION' ) ) {
	define( 'THEMES_API_VERSION', '1.2' );
}

// Support "flat" requests, ie. no '?request[slug]=..` needed, just '?slug=...'
if ( ! isset( $_GET['request'] ) ) {
	$_GET = $_REQUEST = array(
		'action'  => $_GET['action'] ?? '', // 1.2 only supports GET requests
		'request' => array_diff_key( $_GET, [ 'action' => false, 'callback' => false ] ),
	);
}

require dirname( __DIR__ ) . '/1.1/index.php';
