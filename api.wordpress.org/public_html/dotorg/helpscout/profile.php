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
		$html .= '<p>Profile: <a href="https://profiles.wordpress.org/' . $user->user_nicename . '/">'. $user->user_nicename .'</a></p>';
		$html .= '<p>Forums: <a href="https://wordpress.org/support/users/'. $user->user_nicename . '/">'. $user->user_nicename .'</a></p>';
	} else {
		$html .= '<p>No profile found</p>';
	}

	// See if they have a pending user account.
	$records = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->base_prefix}user_pending_registrations
		WHERE user_email = %s OR user_email LIKE %s",
		$request->customer->email,
		str_replace( '@', '+%@', $wpdb->esc_like( $request->customer->email ) ) // Handle plus addressing.
	) );
	if ( $records ) {
		$html .= '<p>Signups found:</p>';
		$html .= '<ul>';

		$match_status = function( $u ) {
			if ( $u->created ) {
				return 'Created';
			}
			if ( ! $u->cleared ) {
				return 'Caught in Spam';
			}
			return 'Pending';
		};

		foreach ( $records as $u ) {
			$html .= sprintf(
				'<li><a href="%s">%s <strong>%s</strong></a></li>',
				esc_url( add_query_arg( 's', urlencode( $u->user_email ), 'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&s=' ) ),
				esc_html( $u->user_login ) . (
					$request->customer->email == $u->user_email ? '' : ' (' . esc_html( $u->user_email ) . ')'
				),
				esc_html( $match_status( $u ) )
			);
		}

		$html .= '</ul>';
	}

	$html .= sprintf(
		'<p><a href="%s">Search pending signups</a></p>',
		esc_url( add_query_arg( 's', urlencode( $request->customer->email ), 'https://login.wordpress.org/wp-admin/index.php?page=user-registrations&s=' ) ),
	);

}

// response to HS is just HTML to display in the sidebar
$response = array( 'html' => $html );

echo json_encode( $response );
