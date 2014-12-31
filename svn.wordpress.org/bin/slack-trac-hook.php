#!/usr/bin/env php
<?php
/**
 * This script receives mail directly from Trac and sends tickets and commits to Slack.
 *
 * New tickets are merely identified here. It is then passed off to the API.
 * That will change when the libraries are merged.
 *
 * New comments are processed here and sent to Slack.
 */

define( 'INC', dirname( __DIR__ ) . '/includes/slack-trac-hooks' );
// This is a shared secret so the Trac server can ping the API server for processing tickets.
define( 'NEW_TICKET_API', 'https://api.wordpress.org/dotorg/slack/newticket.php?token=...' );
// Comments are processed and posted directly from here.
define( 'SLACK_SENDING_HOOK', 'https://hooks.slack.com/services/...' );

$lines = array();
while ( $line = fgets( STDIN ) ) {
	$lines[] = $line;
}

foreach ( $lines as $line ) {
	if ( 0 !== strpos( $line, 'X-Trac-Ticket-URL: ' ) ) {
		continue;
	}
	preg_match( '~X-Trac-Ticket-URL: ((([^#]+)/(\d+))(#comment:\d+)?)~', $line, $matches );
	$comment_url = $matches[1];
	$ticket_url = $matches[2];
	$ticket_base_url = $matches[3];
	$ticket = $matches[4];
	$type = isset( $matches[5] ) ? 'comment' : 'ticket';
	preg_match( '~^https?://([^.]+)\.trac~i', $ticket_base_url, $matches );
	$trac = $matches[1];
	break;
}

if ( empty( $type ) ) {
	exit( 1 );
}

if ( $type === 'ticket' ) {
	slack_ticket_hook( $trac, $ticket );
} else {
	require INC . '/comments.php';
	require INC . '/trac.php';
	require INC . '/config.php';
	$args = Dotorg\SlackTracHook\Comments\process_message( $lines );
	$args['trac']   = $trac;
	$args['ticket'] = $ticket;
	$args['ticket_url']  = $ticket_url;
	$args['comment_url'] = $comment_url;
	Dotorg\SlackTracHook\Comments\send( SLACK_SENDING_HOOK, $args );
}

function slack_ticket_hook( $trac, $ticket ) {
	$payload = array(
		'token' => SLACK_TICKET_HOOK_API_TOKEN,
		'trac' => $trac,
		'ticket' => $ticket,
	);

	$context = stream_context_create( array(
		'http' => array(
			'method'  => 'POST',
			'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
			'content' => http_build_query( $payload ),
		),
	) );

	file_get_contents( NEW_TICKET_API, false, $context );
}

