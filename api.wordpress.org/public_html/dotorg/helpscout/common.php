<?php

$wp_init_host = 'https://wordpress.org/';
$base_dir = dirname( dirname( __DIR__ ) );
require( $base_dir . '/wp-init.php' );

// function to verify signature from HelpScout
function isFromHelpScout($data, $signature) {
	if ( ! defined( 'HELPSCOUT_SECRET_KEY' ) ) {
		return false;
	}

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
$data = json_decode( $data );

// If this is related to a slack user, fetch their details instead.
if (
	isset ( $data->customer->email, $data->ticket->subject ) &&
	false !== stripos( $data->customer->email, 'slack' ) &&
	preg_match( '/(\S+)@chat.wordpress.org/i', $data->ticket->subject, $m )
) {
	$user = get_user_by( 'slug', $m[1] );
	if ( $user ) {
		$data->customer->email = $user->user_email;
	}
}

return $data;