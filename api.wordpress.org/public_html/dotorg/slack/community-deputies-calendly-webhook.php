<?php
namespace WordPressdotorg\API\Slack\Community_Deputy_Webhook;
use Dotorg\Slack\Send;
use DateTime, DateTimeZone;

/*
 * This endpoint receives webhooks from the Community Calendar and pushes it to a Slack channel.
 */
require dirname( __DIR__, 2 ) . '/wp-init.php';
require dirname( __DIR__, 2 ) . '/includes/slack-config.php';

/**
 * Quick API wrapper for the Calendly API.
 */
function api_request( $url ) {
	if ( ! $url ) {
		return false;
	}

	$req = wp_remote_get(
		$url,
		[
			'headers' => [
				'Authorization' => 'Bearer ' . COMMUNITY_CALENDLY_TOKEN
			]
		]
	);

	// The token was probably revoked, so we need to update it.
	if ( 401 === wp_remote_retrieve_response_code( $req ) ) {
		trigger_error(
			'The Calendly token has probably been revoked, the password was probably changed.' .
			'Please update the COMMUNITY_CALENDLY_TOKEN secrets constant with a new PAT created on https://calendly.com/integrations/api_webhooks from the WordCamp Calendly account.' .
			wp_remote_retrieve_body( $req ),
			E_USER_WARNING
		);
	}

	return json_decode( wp_remote_retrieve_body( $req ) );
}

// Check the request is valid.
if ( empty( $_GET['secret'] ) || $_GET['secret'] !== COMMUNITY_CALENDLY_SECRET ) {
	die();
}

$HTTP_RAW_POST_DATA  = file_get_contents( 'php://input' );
$request_body_parsed = json_decode( $HTTP_RAW_POST_DATA );
$event               = $request_body_parsed->event ?? '';
if ( ! $event ) {
	die();
}

// Get the event details.
$event_details = api_request( $request_body_parsed->payload->event )->resource ?? null;
$event_name    = $event_details->name ?? '';

// Check it's a valid and expected meeting type..
$valid               = false;
$valid_meeting_names = [
	'Meetup',
	'WordCamp',
	'do_action',
	'Orientation',
	'Budget Review',
];

foreach ( $valid_meeting_names as $name ) { 
	if ( false !== stripos( $event_name, $name ) ) {
		$valid = true;
		break;
	}
}

if ( ! $valid ) {
	die();
}

// Fetch the routing form questions..
$routing_form_submission = api_request( $request_body_parsed->payload->routing_form_submission )->resource ?? null;

// And who it's assigned to..
$assigned_to = api_request( $event_details->event_memberships[0]->user )->resource->name ?? 'unknown';

// Compile the questions..
$questions_and_answers = array_merge(
	wp_list_pluck( $routing_form_submission->questions_and_answers ?? [], 'answer', 'question' ),
	wp_list_pluck( $request_body_parsed->payload->questions_and_answers ?? [], 'answer', 'question' ),
);

// Finally, compile some phrases needed, localised dates, event names, etc.
$timezone      = $request_body_parsed->payload->timezone;
$date_time     = new DateTime( "now", new DateTimeZone( $timezone ) );
$date_time->setTimestamp( strtotime( $event_details->start_time ) );
$localized_time = $date_time->format( 'g:ia l, F jS, Y' ); // 10:30am Thursday, July 7th, 2022.

// Suffix the timezone, as the localized time is timezone dependant.
$localized_time .= " ({$timezone})";

$location = '';
// Use the first question found that contains 'location' or 'WordCamp'.
// Forms use 'Location', 'Your WordCamp Location', and 'WordCamp Name'.
foreach ( $questions_and_answers as $question => $answer ) {
	if (
		false !== stripos( $question, 'Location' ) ||
		false !== stripos( $question, 'WordCamp' )
	) {
		$location = $answer;
		break;
	}
}

// If the location isn't specified, use their Name instead, as it's likely an individual-specific meeting.
$location       = $location ?: ( $request_body_parsed->payload->name ?: '' );
$event_with_loc = $location ? "{$location} {$event_name}" : $event_name;

$send = new Send( SLACK_MESSAGE_WEBHOOK_URL );
$send->set_icon( ':calendar:' );
$send->set_username( 'Community Calendar' );

if ( 'invitee.created' === $event ) {
	$send->set_text(
		"*{$event_with_loc} is scheduled!*\n" .
		"Assigned to {$assigned_to}. Starts at {$localized_time}."
	);
} elseif ( 'invitee.canceled' === $event ) {
	$reason_given = $request_body_parsed->payload->cancellation->reason ?: 'No reason provided';
	$send->set_text(
		"*{$event_with_loc} has been canceled.*\n" .
		"Canceled by {$request_body_parsed->payload->cancellation->canceled_by}: {$reason_given}, was scheduled for {$localized_time}."
	);
} else {
	// Unhandled event type.
	die();
}

$send->send( COMMUNITY_DEPUTIES_SLACK_CHANNEL_ID );
