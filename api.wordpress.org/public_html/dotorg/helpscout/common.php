<?php

$base_dir = dirname( dirname( __DIR__ ) );
require( $base_dir . '/wp-init.php' );

// function to verify signature from HelpScout
function isFromHelpScout($data, $signature) {
	$calculated = base64_encode( hash_hmac( 'sha1', $data, HELPSCOUT_SECRET_KEY, true ) );
	return hash_equals( $signature, $calculated );
}

//  HelpScout sends json data in the POST, so grab it from the input directly
$data = file_get_contents( 'php://input' );

// check the signature header
if ( ! isset( $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'] ) ) {
	exit;
}

$signature = $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'];
if ( ! isFromHelpScout( $data, $signature ) ) {
	// failure = no response
	exit;
}

// get the info from HS
return json_decode( $data );