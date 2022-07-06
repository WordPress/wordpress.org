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
	$req = wp_remote_get(
		$url,
		[
			'headers' => [
				'Authorization' => 'BEARER ' . COMMUNITY_CALENDLY_TOKEN
			]
		]
	);
	return json_decode( wp_remote_retrieve_body( $req ) );
}

// Check the request is valid.
if ( $_GET['secret'] !== COMMUNITY_CALENDLY_SECRET ) {
	die();
}

$request_body_raw    = file_get_contents( 'php://input' );
$request_body_parsed = json_decode( $request_body_raw );
$event               = $request_body_parsed->event ?? '';
if ( ! $event ) {
	die();
}

// Get the event details.
$event_details = api_request( $request_body_parsed->payload->event )->resource ?? null;
$event_name    = $event_details->name ?? '';

// Check it's a valid and expected meeting type..
$valid_meeting_names = [
	'wordcamp orientation',
	'wordcamp budget review',
];
if ( ! in_array( strtolower( $event_name ), $valid_meeting_names ) ) {
	die();
}

// Fetch the routing form questions..
$routing_form_submission = api_request( $request_body_parsed->payload->routing_form_submission )->resource ?? null;

// And who it's assigned to..
$assigned_to = api_request( $event_details->event_memberships[0]->user )->resource->name ?? 'unknown';

// Compile the questions..
$questions_and_answers = array_merge(
	wp_list_pluck( $routing_form_submission->questions_and_answers, 'answer', 'question' ),
	wp_list_pluck( $request_body_parsed->payload->questions_and_answers, 'answer', 'question' ),
);

// Finally, localize the date and let Slack know.
$timezone      = $request_body_parsed->payload->timezone;
$date_time     = new DateTime( "now", new DateTimeZone( $timezone ) );
$date_time->setTimestamp( strtotime( $event_details->start_time ) );
$localized_time = $date_time->format( 'g:ia l, F jS, Y' ); // 10:30am Thursday, July 7th, 2022.

$send = new Send( SLACK_MESSAGE_WEBHOOK_URL );
$send->set_icon( ':calendar:' );
$send->set_username( 'Community Calendar' );

if ( 'invitee.created' === $event ) {
	$send->set_text(
		"*{$questions_and_answers['Your WordCamp Location']} {$event_name} is scheduled!*\n" .
		"Assigned to {$assigned_to}. Starts at {$localized_time} ({$timezone})."
	);
} elseif ( 'invitee.canceled' === $event ) {
	$reason_given = $request_body_parsed->payload->cancellation->reason ?: 'No reason provided';
	$send->set_text(
		"*{$questions_and_answers['Your WordCamp Location']} {$event_name} has been canceled.*\n" .
		"Canceled by {$request_body_parsed->payload->cancellation->canceled_by}: {$reason_given}, was scheduled for {$localized_time} ($timezone)."
	);
} else {
	// Unhandled event type.
	die();
}

$send->send( COMMUNITY_DEPUTIES_SLACK_CHANNEL_ID );
