<?php

// Version 1.2+ only accepts GET requests
if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	header( $_SERVER['SERVER_PROTOCOL'] . ' 405 Method not allowed' );
	header( 'Allow: GET' );
	header( 'Content-Type: text/plain' );

	die( 'This API only serves GET requests.' );
}

require dirname( __DIR__ ) . '/1.1/index.php';