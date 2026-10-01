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
 * Gets the profile panel.
 *
 * @param object $request Request payload.
 * @return array Panel blocks.
 */
function render_profile( object $request ): array {
	global $wpdb;

	$blocks       = array();
	$user         = false;
	$sender_email = (string) ( $request->sender->email ?? '' );
	$email        = get_user_email_for_email( $request );
	$related      = ! empty( $request->related );
	$slack_email  = preg_match( '/(\S+@chat.wordpress.org)/i', (string) ( $request->conversation->subject ?? '' ), $m ) ? $m[1] : '';

	if ( $email ) {
		$user = get_user_by( 'email', $email );

		$links = array();

		if ( $user ) {
			$badges = array();
			if ( ! empty( $user->wporg_419_capabilities['bbp_blocked'] ) ) {
				$badges[] = badge( 'Forums: Blocked', 'error' );
			} elseif ( ! empty( $user->wporg_419_capabilities['bbp_spectator'] ) ) {
				$badges[] = badge( 'Forums: Spectator', 'warning' );
			}

			$blocks[] = array(
				'type'   => 'lead',
				'text'   => $user->user_nicename,
				'url'    => 'https://profiles.wordpress.org/' . $user->user_nicename . '/',
				'badges' => $badges,
			);

			// When the account email doesn't match the sender's, show the account email too.
			if ( $sender_email && strcasecmp( $sender_email, $user->user_email ) ) {
				$blocks[] = array(
					'type' => 'meta',
					'text' => 'Account email: ' . $user->user_email,
				);
			}

			$links[] = panel_link( 'Account & Security', 'https://profiles.wordpress.org/' . $user->user_nicename . '/profile/edit/group/3/' );
			$links[] = panel_link( 'Forum Profile', 'https://wordpress.org/support/users/' . $user->user_nicename . '/' );
		} else {
			$blocks[] = array(
				'type' => 'empty',
				'text' => 'No profile found',
			);
		}

		$links[] = panel_link( 'Search pending signups', add_query_arg( 's', rawurlencode( $sender_email ), 'https://login.wordpress.org/wp-admin/admin.php?page=user-registrations' ) );

		$blocks[] = array(
			'type'  => 'links',
			'links' => $links,
		);

		$blocks = array_merge( $blocks, render_pending_signups( $sender_email, $email ) );
	}

	// If this is related to a slack user, include the details of the slack account; one the subject names only on request.
	if ( $user || ( $related && $slack_email ) ) {
		// Someone can have several Slack accounts over the years; active ones first.
		if ( $user ) {
			$slack_users = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM slack_users WHERE user_id = %d ORDER BY deactivated ASC', $user->ID ) );
		} else {
			$slack_users = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM slack_users WHERE profiledata LIKE %s ORDER BY deactivated ASC',
					'%' . $wpdb->esc_like( '"email":"' . $slack_email . '"' ) . '%'
				)
			);
		}

		$blocks = array_merge( $blocks, render_slack_users( $slack_users ) );
	}

	// The sender wrote whatever names someone else, so an agent decides whether it's worth a look; WPOrgSidebar asks.
	if ( $related ) {
		array_unshift(
			$blocks,
			array(
				'type'        => 'notice',
				'text'        => 'Showing the account this is about, not the sender’s.',
				'action'      => 'sender',
				'action_text' => 'Show the sender',
			)
		);
	} elseif ( get_related_user( $request ) || ( ! $user && $slack_email ) ) {
		array_unshift(
			$blocks,
			array(
				'type'        => 'notice',
				'text'        => 'This may be about someone else’s account, like a bounce.',
				'action'      => 'related',
				'action_text' => 'Show it',
			)
		);
	}

	return $blocks;
}

/**
 * Gets pending signups for the sender and the matched user.
 *
 * @param string $sender_email Sender's email address.
 * @param string $email        Email address of the matched user, or the sender's.
 * @return array Panel blocks.
 */
function render_pending_signups( string $sender_email, string $email ): array {
	global $wpdb;

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

	if ( ! $records ) {
		return array();
	}

	$items = array();
	foreach ( $records as $record ) {
		$status = badge( 'Pending', 'warning' );
		if ( $record->created ) {
			$status = badge( 'Created', 'success' );
		} elseif ( ! $record->cleared ) {
			$status = badge( 'Caught in Spam', 'error' );
		}

		$items[] = array(
			'title'  => $record->user_login,
			'url'    => add_query_arg( 's', rawurlencode( $record->user_email ), 'https://login.wordpress.org/wp-admin/admin.php?page=user-registrations' ),
			'badges' => array( $status ),
			'meta'   => strcasecmp( $sender_email, $record->user_email ) ? array( array( 'text' => $record->user_email ) ) : array(),
		);
	}

	return array(
		array(
			'type' => 'heading',
			'text' => 'Signups',
		),
		array(
			'type'  => 'items',
			'items' => $items,
		),
	);
}

/**
 * Gets the status of someone's Slack accounts.
 *
 * @param object[] $slack_users Rows from the slack_users table.
 * @return array Panel blocks.
 */
function render_slack_users( array $slack_users ): array {
	if ( ! $slack_users ) {
		return array();
	}

	$items = array();
	foreach ( $slack_users as $slack_user ) {
		$slack_data = json_decode( (string) $slack_user->profiledata );
		if ( ! is_object( $slack_data ) || ! isset( $slack_data->updated ) ) {
			$items[] = array( 'meta' => array( array( 'text' => 'Clicked a signup link, but likely didn’t finish signing up.' ) ) );
			continue;
		}

		// Slack sends empty strings for names a member never set; the username is always there.
		$names = array_filter(
			array_map( 'strval', array( $slack_data->profile->display_name_normalized ?? '', $slack_data->profile->display_name ?? '', $slack_data->profile->real_name ?? '', $slack_data->name ?? '' ) ),
			'strlen'
		);

		$items[] = array(
			'title'  => (string) reset( $names ),
			'url'    => 'https://wordpress.slack.com/archives/' . $slack_user->dm_id,
			'badges' => array( ! empty( $slack_data->deleted ) ? badge( 'Deactivated', 'error' ) : badge( 'Active', 'success' ) ),
			'meta'   => array( array( 'text' => 'Updated ' . gmdate( 'Y-m-d', (int) $slack_data->updated ) ) ),
		);
	}

	return array(
		array(
			'type' => 'heading',
			'text' => 'Slack',
		),
		array(
			'type'  => 'items',
			'items' => $items,
		),
	);
}

/**
 * Gets the avatar of the sender's WordPress.org account, which WPOrgSidebar saves as the sender's photo.
 *
 * Only for an account found by one of the sender's own addresses: for bounces and Slack notifications, the account
 * is someone else's.
 *
 * @param object $request Request payload.
 * @return string Avatar URL, which answers 404 if the account has no avatar; empty if there's no account.
 */
function get_sender_avatar_url( object $request ): string {
	$user = get_user_by( 'email', get_user_email_for_email( $request ) );
	if ( ! $user ) {
		return '';
	}

	$sender_emails = array_map(
		'strtolower',
		array_merge( array( (string) ( $request->sender->email ?? '' ) ), array_map( 'strval', (array) ( $request->sender->emails ?? array() ) ) )
	);
	if ( ! in_array( strtolower( $user->user_email ), $sender_emails, true ) ) {
		return '';
	}

	return (string) get_avatar_url(
		$user,
		array(
			'size'    => 256,
			'default' => '404',
		)
	);
}

$request = get_request( basename( __FILE__ ) );
send_panel( render_profile( $request ), array( 'avatar_url' => get_sender_avatar_url( $request ) ) );
