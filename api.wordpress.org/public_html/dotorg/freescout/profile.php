<?php
/**
 * FreeScout sidebar: the sender's WordPress.org profile, pending signups, and Slack account.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

require __DIR__ . '/common.php';

// Provides wporg_sanitize_email_for_search().
require_once WP_CONTENT_DIR . '/themes/pub/wporg-login/functions-registration.php';

/**
 * Renders the profile panel.
 *
 * @param object $request Request payload.
 * @return string
 */
function render_profile( object $request ): string {
	global $wpdb;

	$html         = '';
	$user         = false;
	$sender_email = (string) ( $request->sender->email ?? '' );
	$email        = get_user_email_for_email( $request );

	if ( $email ) {
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			$html .= '<p>Profile: <a href="' . esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ) . '">' . esc_html( $user->user_nicename ) . '</a></p>';
			$html .= '<p><a href="' . esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/profile/edit/group/3/' ) . '">Account &amp; Security</a></p>';
			$html .= '<p><a href="' . esc_url( 'https://wordpress.org/support/users/' . $user->user_nicename . '/' ) . '">Forum Profile</a></p>';

			// When the account email doesn't match the sender's, show the account email too.
			if ( $sender_email && strcasecmp( $sender_email, $user->user_email ) ) {
				$html .= '<p>Account Email: ' . esc_html( $user->user_email ) . '</p>';
			}

			if ( ! empty( $user->wporg_419_capabilities['bbp_blocked'] ) ) {
				$html .= '<p><strong>Forums Status: BLOCKED</strong></p>';
			} elseif ( ! empty( $user->wporg_419_capabilities['bbp_spectator'] ) ) {
				$html .= '<p><strong>Forums Status: Spectator</strong></p>';
			}
		} else {
			$html .= '<p>No profile found</p>';
		}

		$html .= render_pending_signups( $sender_email, $email );
	}

	// If this is related to a slack user, include the details of the slack account.
	if ( $user || preg_match( '/(\S+@chat.wordpress.org)/i', (string) ( $request->conversation->subject ?? '' ), $m ) ) {
		if ( $user ) {
			$slack_user = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM slack_users WHERE user_id = %d', $user->ID ) );
		} else {
			$slack_user = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM slack_users WHERE profiledata LIKE %s',
					'%' . $wpdb->esc_like( '"email":"' . $m[1] . '"' ) . '%'
				)
			);
		}

		$html .= render_slack_user( $slack_user );
	}

	return $html;
}

/**
 * Renders pending signups for the sender and the matched user.
 *
 * @param string $sender_email Sender's email address.
 * @param string $email        Email address of the matched user, or the sender's.
 * @return string
 */
function render_pending_signups( string $sender_email, string $email ): string {
	global $wpdb;

	$html    = '';
	$records = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM %i WHERE ( user_email = %s OR user_email_san = %s OR user_email = %s OR user_email_san = %s )',
			"{$wpdb->base_prefix}user_pending_registrations",
			$sender_email,
			wporg_sanitize_email_for_search( $sender_email ),
			$email,
			wporg_sanitize_email_for_search( $email )
		)
	);

	if ( $records ) {
		$html .= '<p>Signups found:</p>';
		$html .= '<ul>';

		foreach ( $records as $record ) {
			$status = 'Pending';
			if ( $record->created ) {
				$status = 'Created';
			} elseif ( ! $record->cleared ) {
				$status = 'Caught in Spam';
			}

			$html .= sprintf(
				'<li><a href="%s">%s <strong>%s</strong></a></li>',
				esc_url( add_query_arg( 's', rawurlencode( $record->user_email ), 'https://login.wordpress.org/wp-admin/admin.php?page=user-registrations' ) ),
				esc_html( $record->user_login . ( strcasecmp( $sender_email, $record->user_email ) ? ' (' . $record->user_email . ')' : '' ) ),
				esc_html( $status )
			);
		}

		$html .= '</ul>';
	}

	$html .= sprintf(
		'<p><a href="%s">Search pending signups</a></p>',
		esc_url( add_query_arg( 's', rawurlencode( $sender_email ), 'https://login.wordpress.org/wp-admin/admin.php?page=user-registrations' ) )
	);

	return $html;
}

/**
 * Renders a Slack account's status.
 *
 * @param object|null $slack_user Row from the slack_users table, if any.
 * @return string
 */
function render_slack_user( ?object $slack_user ): string {
	if ( ! $slack_user ) {
		return '';
	}

	$slack_data = json_decode( (string) $slack_user->profiledata );
	if ( ! $slack_data ) {
		return '<hr/><ul><li>Slack: Has clicked signup link, but likely not finalised Slack signup flow.</li></ul>';
	}

	$html  = '<hr/>';
	$html .= '<ul>';
	$html .= '<li>Slack: <a href="' . esc_url( 'https://wordpress.slack.com/archives/' . $slack_user->dm_id ) . '">' . esc_html( $slack_data->profile->display_name_normalized ?? $slack_data->profile->display_name ) . '</a></li>';
	$html .= '<li>Account ' . ( ! empty( $slack_data->deleted ) ? 'Deactivated' : 'Enabled' ) . '</li>';
	$html .= '<li>Last Updated: ' . esc_html( gmdate( 'Y-m-d H:i:s', (int) $slack_data->updated ) ) . '</li>';
	$html .= '</ul>';

	return $html;
}

send_html( render_profile( get_request() ) );
