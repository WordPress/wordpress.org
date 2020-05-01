<?php
// Simple profile lookup for HelpScout sidebar. Returns w.org links to HS for emails received.

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
$signature = $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'];
if ( ! isFromHelpScout( $data, $signature ) ) {
	// failure = no response
	exit;
}

// get the info from HS
$request = json_decode( $data );

// default empty output
$html = '';

// Look up a user based on email address
if ( isset ( $request->customer->email ) ) {

	// look up profile url by email
	$user = get_user_by( 'email', $request->customer->email );
	if ( isset( $user->user_nicename ) ) {
		$html .= '<p>Profile: <a href="https://profiles.wordpress.org/' . $user->user_nicename . '">'. $user->user_nicename .'</a></p>';
	} else {
		$html .= '<p>No profile found</p>';
	}

}

// response to HS is just HTML to display in the sidebar
$response = array ('html' => $html);

echo json_encode( $response );

