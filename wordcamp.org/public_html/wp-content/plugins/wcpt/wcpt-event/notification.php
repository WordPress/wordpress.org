<?php
/**
 * Provides helper methods for sending slack notifications in response to status changes in application.
 */

if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
	// If this is sandbox and then send notification of owner of sandbox (as long as sandbox username and slack username matches).
	if ( defined( 'SANDBOX_SLACK_USERNAME' ) ) {
		$slack_username = SANDBOX_SLACK_USERNAME;
	} else {
		$slack_username = '@' . str_replace( array( '.dev.ord', '.dev' ), '', WPORG_SANDBOXED );
	}
	define( 'COMMUNITY_TEAM_SLACK', $slack_username );
	define( 'COMMUNITY_EVENTS_SLACK', $slack_username );
} else {
	define( 'COMMUNITY_TEAM_SLACK', '#community-team' );
	define( 'COMMUNITY_EVENTS_SLACK', '#community-events' );
}

/**
 * Send attachment to Make WordPress slack. Will be used to send event notifications to community channels
 *
 * @param string $channel Name of the channel we want to send the notification to.
 * @param array  $attachment Attachment object.
 *
 * @return bool|string
 */
function wcpt_slack_notify( $channel, $attachment ) {
	if ( ! class_exists( 'Dotorg\Slack\Send' ) ) {
		return false;
	}

	if ( ! defined( 'SLACK_ERROR_REPORT_URL' ) ) {
		return false;
	}

	$send = new \Dotorg\Slack\Send( SLACK_ERROR_REPORT_URL );
	$send->add_attachment( $attachment );
	return $send->send( $channel );
}

/**
 * Creates attachment that can be sent using slack notification.
 * See the structure of attachment here: https://api.slack.com/docs/message-attachments
 *
 * @param string $message Main text to send in the notification.
 * @param string $title   Title of the notification.
 *
 * @return array
 */
function create_event_attachment( $message, $title ) {
	// Not translating because this will be send to Slack.
	return array(
		'title' => $title,
		'text'  => $message,
	);
}

/**
 * Returns an attachment object to customize notification for slack.
 * See https://api.slack.com/docs/message-attachments
 *
 * @param string $message  Text that should be in the attachment.
 * @param int    $event_id Post ID of the event. Will be used to gather props.
 * @param string $title    TItle of the message.
 *
 * @return array
 */
function create_event_status_attachment( $message, $event_id, $title ) {
	$props = get_props_for_event( $event_id );

	$props_string = implode( ', ', $props );

	return array(
		'title'  => $title,
		'text'   => $message,
		'fields' => array(
			array(
				"title" => "Application processed by",
				"value" => $props_string,
			),
		),
	);
}

/**
 * Get and array of usernames for everyone contributed to vetting an event application.
 * This includes people who have changed statuses, added notes etc.
 * Currently supports WordCamp and Meetups
 *
 * @param int $event_id Id of the event to fetch props for.
 *
 * @return array Array of usernames of people who have participated in vetting this application
 */
function get_props_for_event( $event_id ) {
	$user_ids = array();

	$status_change_logs = get_post_meta( $event_id, '_status_change' ) ?? array();

	$user_ids = array_merge( $user_ids, wp_list_pluck( $status_change_logs, 'user_id' ) );

	$notes = get_post_meta( $event_id, '_note' ) ?? array();

	$user_ids = array_merge( $user_ids, wp_list_pluck( $notes, 'user_id' ) );

	$user_ids = array_unique( $user_ids );

	$user_nicenames = get_user_nicenames_from_ids( $user_ids );

	// remove bot user `wordcamp`.
	$user_nicenames = array_diff( $user_nicenames, array( 'wordcamp' ) );
	return $user_nicenames;
}

/**
 * Return user names for list of user ids provided in the function
 *
 * @param array $user_ids List of user_ids.
 *
 * @return array List of user nicenames
 */
function get_user_nicenames_from_ids( $user_ids ) {
	if ( empty( $user_ids ) ) {
		return array();
	}

	$user_query = new WP_User_Query(
		array(
			'include' => $user_ids,
			'fields'  => array( 'user_nicename' ),
		)
	);

	$users = $user_query->get_results();
	$user_display_names = array();
	foreach ( $users as $user ) {
		$user_display_names[] = $user->user_nicename;
	}

	return $user_display_names;
}
