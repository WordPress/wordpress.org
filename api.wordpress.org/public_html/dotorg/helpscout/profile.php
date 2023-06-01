<?php
namespace WordPressdotorg\API\HelpScout;

// Simple profile lookup for HelpScout sidebar. Returns w.org links to HS for emails received.

include __DIR__ . '/common.php';

// $request is the validated HelpScout request.
$request = get_request();

// default empty output
$html = '';
$user = false;
$email = get_user_email_for_email( $request );

// Look up a user based on email address
if ( $email ) {

	// look up profile url by email
	$user = get_user_by( 'email', $email );

	if ( isset( $user->user_nicename ) ) {
		$html .= '<p>Profile: <a href="https://profiles.wordpress.org/' . $user->user_nicename . '/">'. $user->user_nicename .'</a></p>';
		$html .= '<p>Forums: <a href="https://wordpress.org/support/users/'. $user->user_nicename . '/">'. $user->user_nicename .'</a></p>';

		// When the Displayed account email doesn't match the email being displayed, output the user email address too.
		if ( ! empty( $request->customer->email ) && strcasecmp( $request->customer->email, $user->user_email ) ) {
			$html .= '<p>Account Email: ' . esc_html( $user->user_email ) . '</p>';
		}
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
				esc_url( add_query_arg( 's', urlencode( $u->user_email ), 'https://login.wordpress.org/wp-admin/admin.php?page=user-registrations&s=' ) ),
				esc_html( $u->user_login ) . (
					strcasecmp( $request->customer->email, $u->user_email ) ? ' (' . esc_html( $u->user_email ) . ')' : ''
				),
				esc_html( $match_status( $u ) )
			);
		}

		$html .= '</ul>';
	}

	$html .= sprintf(
		'<p><a href="%s">Search pending signups</a></p>',
		esc_url( add_query_arg( 's', urlencode( $request->customer->email ), 'https://login.wordpress.org/wp-admin/admin.php?page=user-registrations&s=' ) ),
	);

}

// If this is related to a slack user, include the details of the slack account.
if ( $user || preg_match( '/(\S+@chat.wordpress.org)/i', $request->ticket->subject ?? '', $m ) ) {

	if ( $user ) {
		$slack_user = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM slack_users WHERE user_id = %d',
			$user->ID
		) );
	} else {
		$slack_user = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM slack_users WHERE profiledata LIKE %s',
			'%' . $wpdb->esc_like( '"email":"' . $m[1] . '"' ) . '%',
		) );
	}

	$slack_data = $slack_user ? json_decode( $slack_user->profiledata ) : false;
	if ( $slack_user && ! $slack_data ) {
		$html .= '<hr/>';
		$html .= '<ul><li>Slack: Has clicked signup link, but likely not finalised Slack signup flow.</li></ul>';
	}
	if ( $slack_data ) {
		$html .= '<hr/>';
		$html .= '<ul>';
		$html .= '<li>Slack: <a href="https://wordpress.slack.com/archives/' . $slack_user->dm_id .  '">' . esc_html( $slack_data->profile->display_name_normalized ?? $slack_data->profile->display_name ) . '</a></li>';
		$html .= '<li>Account ' . ( !empty( $slack_data->deleted ) ? 'Deactivated' : 'Enabled' ) . '</li>';
		$html .= '<li>Last Updated: ' . gmdate( 'Y-m-d H:i:s', $slack_data->updated ) . '</li>';
		$html .= '</ul>';
	}
}

// response to HS is just HTML to display in the sidebar
echo json_encode( array( 'html' => $html ) );
