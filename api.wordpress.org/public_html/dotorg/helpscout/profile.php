<?php
// Simple profile lookup for HelpScout sidebar. Returns w.org links to HS for emails received.

// $request is the validated HelpScout request.
$request = include __DIR__ . '/common.php';

// default empty output
$html = '';

// Look up a user based on email address
if ( isset ( $request->customer->email ) ) {

	// look up profile url by email
	$user = get_user_by( 'email', $request->customer->email );
	if ( isset( $user->user_nicename ) ) {
		$html .= '<p>Profile: <a href="https://profiles.wordpress.org/' . $user->user_nicename . '">'. $user->user_nicename .'</a></p>';
		$html .= '<p>Forums: <a href="https://wordpress.org/support/users/'. $user->user_nicename . '">'. $user->user_nicename .'</a></p>';
	} else {
		$html .= '<p>No profile found</p>';
	}

}

// response to HS is just HTML to display in the sidebar
$response = array( 'html' => $html );

echo json_encode( $response );
