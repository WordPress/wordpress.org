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

		$links = array();

		if ( $user ) {
			$forums_status = '';
			if ( ! empty( $user->wporg_419_capabilities['bbp_blocked'] ) ) {
				$forums_status = render_badge( 'Forums: Blocked', 'error' );
			} elseif ( ! empty( $user->wporg_419_capabilities['bbp_spectator'] ) ) {
				$forums_status = render_badge( 'Forums: Spectator', 'warning' );
			}

			$html .= sprintf(
				'<p class="wporg-sidebar-lead"><a href="%s">%s</a> %s</p>',
				esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ),
				esc_html( $user->user_nicename ),
				$forums_status
			);

			// When the account email doesn't match the sender's, show the account email too.
			if ( $sender_email && strcasecmp( $sender_email, $user->user_email ) ) {
				$html .= '<p class="wporg-sidebar-meta">Account email: ' . esc_html( $user->user_email ) . '</p>';
			}

			$links['Account & Security'] = 'https://profiles.wordpress.org/' . $user->user_nicename . '/profile/edit/group/3/';
			$links['Forum Profile']      = 'https://wordpress.org/support/users/' . $user->user_nicename . '/';
		} else {
			$html .= '<p class="wporg-sidebar-empty">No profile found</p>';
		}

		$links['Search pending signups'] = add_query_arg( 's', rawurlencode( $sender_email ), 'https://login.wordpress.org/wp-admin/admin.php?page=user-registrations' );

		$html .= '<ul class="wporg-sidebar-links">';
		foreach ( $links as $label => $url ) {
			$html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
		}
		$html .= '</ul>';

		$html .= render_pending_signups( $sender_email, $email );
	}

	// If this is related to a slack user, include the details of the slack account.
	if ( $user || preg_match( '/(\S+@chat.wordpress.org)/i', (string) ( $request->conversation->subject ?? '' ), $m ) ) {
		// Someone can have several Slack accounts over the years; active ones first.
		if ( $user ) {
			$slack_users = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM slack_users WHERE user_id = %d ORDER BY deactivated ASC', $user->ID ) );
		} else {
			$slack_users = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM slack_users WHERE profiledata LIKE %s ORDER BY deactivated ASC',
					'%' . $wpdb->esc_like( '"email":"' . $m[1] . '"' ) . '%'
				)
			);
		}

		$html .= render_slack_users( $slack_users );
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
		$html .= '<h5 class="wporg-sidebar-heading">Signups</h5>';
		$html .= '<ul class="wporg-sidebar-items">';

		foreach ( $records as $record ) {
			$status = render_badge( 'Pending', 'warning' );
			if ( $record->created ) {
				$status = render_badge( 'Created', 'success' );
			} elseif ( ! $record->cleared ) {
				$status = render_badge( 'Caught in Spam', 'error' );
			}

			$html .= sprintf(
				'<li class="wporg-sidebar-item"><a class="wporg-sidebar-item-title" href="%s">%s</a> %s%s</li>',
				esc_url( add_query_arg( 's', rawurlencode( $record->user_email ), 'https://login.wordpress.org/wp-admin/admin.php?page=user-registrations' ) ),
				esc_html( $record->user_login ),
				$status,
				strcasecmp( $sender_email, $record->user_email ) ? '<div class="wporg-sidebar-item-meta">' . esc_html( $record->user_email ) . '</div>' : ''
			);
		}

		$html .= '</ul>';
	}

	return $html;
}

/**
 * Renders the status of someone's Slack accounts.
 *
 * @param object[] $slack_users Rows from the slack_users table.
 * @return string
 */
function render_slack_users( array $slack_users ): string {
	if ( ! $slack_users ) {
		return '';
	}

	$html = '<h5 class="wporg-sidebar-heading">Slack</h5><ul class="wporg-sidebar-items">';

	foreach ( $slack_users as $slack_user ) {
		$slack_data = json_decode( (string) $slack_user->profiledata );
		if ( ! is_object( $slack_data ) || ! isset( $slack_data->updated ) ) {
			$html .= '<li class="wporg-sidebar-item wporg-sidebar-meta">Clicked a signup link, but likely didn’t finish signing up.</li>';
			continue;
		}

		$html .= sprintf(
			'<li class="wporg-sidebar-item"><a class="wporg-sidebar-item-title" href="%s">%s</a> %s<div class="wporg-sidebar-item-meta">Updated %s</div></li>',
			esc_url( 'https://wordpress.slack.com/archives/' . $slack_user->dm_id ),
			esc_html( $slack_data->profile->display_name_normalized ?? $slack_data->profile->display_name ?? '' ),
			! empty( $slack_data->deleted ) ? render_badge( 'Deactivated', 'error' ) : render_badge( 'Active', 'success' ),
			esc_html( gmdate( 'Y-m-d', (int) $slack_data->updated ) )
		);
	}

	return $html . '</ul>';
}

send_html( render_profile( get_request() ) );
