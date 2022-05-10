<?php
// Simple User Notes sidebar panel.

// $request is the validated HelpScout request.
$request = include __DIR__ . '/common.php';

// default empty output
$html  = '';
// look up profile url by email
$email = get_user_email_for_email( $request );
$user  = get_user_by( 'email', $email );

// Include Notes
if ( $user && $user->_wporg_bbp_user_notes ) {
	foreach ( $user->_wporg_bbp_user_notes as $note ) {
		$html .= '<p><a href="https://wordpress.org/support/users/' . $user->user_nicename . '/">' . gmdate( 'F j, Y', strtotime( $note->date ) ) . ':</a> ';
		$html .= '<em>' . wp_trim_words( esc_html( $note->text ), 15 ) . '</em>';
		$html .= ' By ' . esc_html( $note->moderator );
	}
}

// response to HS is just HTML to display in the sidebar
echo json_encode( array( 'html' => $html ) );
