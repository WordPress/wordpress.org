<?php

namespace Dotorg\Slack\Props;
use Dotorg\Slack\Send;
use function Dotorg\Profiles\{ post as profiles_post };

function show_error( $user ) {
	return "Please use `/props SLACK_USERNAME MESSAGE` to give props.\n";
}

/**
 * Receive `/props` request and send to `#props`.
 *
 * This is being deprecated in favor of `handle_props_message()`.
 *
 * @param array $data
 * @param bool  $force_test Send to test channel instead of #props
 *
 * @return string
 */
function run( $data, $force_test = false ) {
	$sender = $data['user_name'];

	if ( $data['command'] !== '/props' ) {
		return "???\n";
	}

	if ( empty( $data['text'] ) ) {
		return show_error( $sender );
	}

	list( $receiver, $message ) = @preg_split( '/\s+/', trim( $data['text'] ), 2 );

	$receiver = ltrim( $receiver, '@' );

	if ( ! strlen( $receiver ) || ! strlen( $message ) ) {
		return show_error( $sender );
	}

	// TODO: Add WordPress.org username to $text if different than Slack username.
	$text = sprintf( "Props to @%s: %s", $receiver, $message );

	$send = new Send( \Dotorg\Slack\Send\WEBHOOK );
	$send->set_username( $sender );
	$send->set_text( $text );
	$send->set_link_names( true ); // We want to the person getting props!

	$get_avatar = __NAMESPACE__ . '\\' . 'get_avatar';

	if ( function_exists( $get_avatar ) ) {
		$send->set_icon( call_user_func( $get_avatar, $sender, $data['user_id'], $data['team_id'] ) );
	}

	if ( $force_test ) {
		$send->testing( true );
	}

	$send->send( '#props' );

	return sprintf( "Your props to @%s have been sent.\n", $receiver );
}

/**
 * Adds props in Slack to w.org profiles.
 *
 * Receives webhook notifications for all new messages in `#props`,
 */
function handle_props_message( object $request ) : string {
	if ( ! is_valid_props( $request->event ) ) {
		return 'Invalid props';
	}

	$giver_user          = map_slack_users_to_wporg( array( $request->event->user ) )[0];
	$recipient_slack_ids = get_recipient_slack_ids( $request->event->blocks );

	if ( empty( $recipient_slack_ids ) ) {
		return 'Nobody was mentioned';
	}

	$recipient_users = map_slack_users_to_wporg( $recipient_slack_ids );
	$recipient_ids   = array_column( $recipient_users, 'id' );

	$url = sprintf(
		'https://wordpress.slack.com/archives/%s/p%s',
		$request->event->channel,
		$request->event->ts
	);
	$url = filter_var( $url, FILTER_SANITIZE_URL );

	if ( empty( $recipient_ids ) ) {
		return 'No recipients';
	}

	// This is Slack's unintuitive way of giving messages a unique ID :|
	// https://api.slack.com/messaging/retrieving#individual_messages
	$message_id = sprintf( '%s-%s', $request->event->channel, $request->event->ts );
	$message    = prepare_message( $request->event->text, $recipient_users );

	add_activity_to_profile( compact( 'giver_user', 'recipient_ids', 'url', 'message_id', 'message' ) );

	// The request was successful from Slack's perspective as long as we received and validated it. Any errors
	// that occurred when pushing to Profiles are only significant to us.
	return 'Success';
}

/**
 * Determine if this is an event that we should handle.
 */
function is_valid_props( object $event ) : bool {
	$valid_channels = array(
		'C0FRG66LR'  #props
	);

	if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
		$valid_channels[] = 'C03AKLN7P9U'; #iandunn-testing
	}

	$has_required_params = isset( $event->channel, $event->blocks, $event->type ) && is_array( $event->blocks );
	$in_valid_channel    = in_array( $event->channel, $valid_channels, true );

	$is_correct_type =
		'message' === $event->type &&
		empty( $event->subtype ) && // e.g., `message.deleted`, `message.changed`.
		empty( $event->hidden ) &&
		empty( $event->thread_ts );

	if ( $is_correct_type && $has_required_params && $in_valid_channel ) {
		return true;
	}

	return false;
}

/**
 * Parse the mentioned Slack user IDs from a message event.
 *
 * This assumes that the app is configured to escape usernames.
 */
function get_recipient_slack_ids( array $blocks ) : array {
	$ids = array();

	foreach ( $blocks as $block ) {
		foreach ( $block->elements as $element ) {
			foreach ( $element->elements as $inner_element ) {
				if ( 'user' !== $inner_element->type ) {
					continue;
				}

				$ids[] = $inner_element->user_id;
			}
		}
	}

	return $ids;
}

/**
 * Find the w.org users associated with the given slack accounts.
 */
function map_slack_users_to_wporg( array $slack_ids ) : array {
	global $wpdb;

	if ( empty( $slack_ids ) ) {
		return array();
	}

	$wporg_users     = array();
	$id_placeholders = implode( ', ', array_fill( 0, count( $slack_ids ), '%s' ) );

	$query = $wpdb->prepare( "
		SELECT
			su.slack_id, su.user_id AS wporg_id,
			mu.user_login
		FROM `slack_users` su
			JOIN `minibb_users` mu ON su.user_id = mu.ID
		WHERE `slack_id` IN( $id_placeholders )",
		$slack_ids
	);

	$results = $wpdb->get_results( $query, ARRAY_A );

	foreach ( $results as $user ) {
		$wporg_users[ $user['slack_id'] ] = array(
			'id'         => (int) $user['wporg_id'],
			'user_login' => $user['user_login'],
		);
	}

	return $wporg_users;
}

/**
 * Replace Slack IDs with w.org usernames, to better fit w.org profiles.
 */
function prepare_message( string $original, array $user_map ) : string {
	$search  = array();
	$replace = array();

	foreach ( $user_map as $slack_id => $wporg_user ) {
		$search[]  = sprintf( '<@%s>', $slack_id );
		$replace[] = '@' . $wporg_user['user_login'];
	}

	return str_replace( $search, $replace, $original );
}

/**
 * Send a request to Profiles to add the activity.
 *
 * See `handle_slack_activity()` in `buddypress.org/.../wporg-profiles-activity-handler.php` for the needed args.
 */
function add_activity_to_profile( array $request_args ) : bool {
	require_once dirname( __DIR__, 2 ) . '/profiles/profiles.php';

	$request_args = array_merge(
		$request_args,
		array(
			'action'   => 'wporg_handle_activity',
			'source'   => 'slack',
			'activity' => "props_given",
		)
	);

	$response_body = profiles_post( $request_args );

	if ( is_numeric( $response_body ) && (int) $response_body > 0 ) {
		$success = true;

	} else {
		$success = false;

		trigger_error( 'Adding activity failed with error: ' . $response_body, E_USER_WARNING );
	}

	return $success;
}
