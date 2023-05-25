<?php

namespace Dotorg\Slack\Props;
use Exception;
use function Dotorg\Profiles\{ post as profiles_post };

/**
 * Adds props in Slack to w.org profiles.
 *
 * Receives webhook notifications for all new messages in `#props`. See `dotorg/slack/props.php` for the caller.
 */
function handle_props_message( object $request ) : string {
	if ( ! is_valid_props( $request->event ) ) {
		return 'Invalid props';
	}

	$giver_user          = map_slack_users_to_wporg( array( $request->event->user ) );
	$giver_user          = array_pop( $giver_user );
	$recipient_slack_ids = get_recipient_slack_ids( $request->event->blocks );

	// You have to have a w.org account to have a Slack account, so this should always be populated.
	// The 'Received props from @...' would be broken if the giver can't be found.
	// Don't throw if a recipient lookup fails, since we still want other recipients to get props.
	if ( empty( $giver_user ) ) {
		throw new Exception( 'w.org user lookup for slack ID '. $request->event->user .' failed' );
	}

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
	$message_id    = sprintf( '%s-%s', $request->event->channel, $request->event->ts );
	$channel_names = map_slack_channel_ids_to_names( $request->event->text );
	$message       = prepare_message( $request->event->blocks[0]->elements[0]->elements, $recipient_users, $channel_names );

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
 */
function get_recipient_slack_ids( array $blocks ) : array {
	$ids = array();

	foreach ( $blocks as $block ) {
		foreach ( $block->elements as $element ) {
			$ids = array_merge( $ids, get_user_ids_from_element( $element ) );
		}
	}

	return array_unique( $ids );
}

/**
 * Recursively parse any mentioned Slack user IDs from a message element.
 *
 * This assumes that the app is configured to escape usernames.
 */
function get_user_ids_from_element( object $element ) : array {
	$ids = array();

	if ( 'user' === $element->type ) {
		$ids[] = $element->user_id;
	}

	if ( isset( $element->elements ) ) {
		foreach ( $element->elements as $inner_element ) {
			$ids = array_merge( $ids, get_user_ids_from_element( $inner_element ) );
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
 * Parse Slack channel names and IDs out of message.
 *
 * This avoids having to make an API call to Slack to fetch the channels, like their docs recommend.
 */
function map_slack_channel_ids_to_names( string $message ) : array {
	$map = array();

	preg_match_all( '/<#(\w*\d*)\|([\w-]*)>/m', $message, $matches, PREG_SET_ORDER );

	foreach ( $matches as $match ) {
		$map[ $match[1] ] = $match[2];
	}

	return $map;
}


/**
 * Prepare message to be sent to the Profiles API.
 *
 * Replace Slack IDs with w.org usernames, to better fit w.org profiles.
 * Un-escape URLs and other things Slack has escaped.
 */
function prepare_message( array $elements, array $user_map, array $channel_map ) : string {
	$prepared = '';

	foreach ( $elements as $element ) {
		switch ( $element->type ) {
			case 'text':
				$prepared .= $element->text;
			break;

			case 'link':
				$prepared .= $element->url;
			break;

			case 'emoji':
				$prepared .= ":{$element->name}:";
			break;

			case 'user':
				$prepared .= '@' . $user_map[ $element->user_id ]['user_login'];
			break;

			case 'channel':
				$prepared .= '#' . $channel_map[ $element->channel_id ];
			break;

			default:
				// Ignore
			break;
		}
	}

	return $prepared;
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
