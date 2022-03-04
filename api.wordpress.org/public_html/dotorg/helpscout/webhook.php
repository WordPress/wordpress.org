<?php
// Webhook fired when an event is fired for any inbox.
// Events: Conversation Created (convo.created)

// $request is the validated HelpScout request.
$request = include __DIR__ . '/common.php';
$event   = $_SERVER['HTTP_X_HELPSCOUT_EVENT'] ?? '';

if (
	defined( 'HELPSCOUT_OPENVERSE_WEBHOOK' ) && HELPSCOUT_OPENVERSE_WEBHOOK &&
	defined( 'HELPSCOUT_OPENVERSE_MAILBOXID' ) && HELPSCOUT_OPENVERSE_MAILBOXID &&
	'convo.created' === $event &&
	isset( $request->mailboxId ) && HELPSCOUT_OPENVERSE_MAILBOXID === $request->mailboxId
) {
	$subject = $request->subject;
	$url    = $request->_links->web->href;

	wp_safe_remote_post(
		HELPSCOUT_OPENVERSE_WEBHOOK,
		[
			'body' => wp_json_encode( compact( 'subject', 'url' ) )
		]
	);
}
