<?php
/**
 * This script receives mail directly from Trac and sends tickets and commits to Slack.
 */

require '/home/svn/bin/includes/slack/autoload.php';

define( 'WEBHOOK', 'https://hooks.slack.com/services/...' );
define( 'MENTIONS_API_HANDLER', 'https://api.wordpress.org/dotorg/trac/mentions-handler.php' );
define( 'MENTIONS_API_KEY', '...' );

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
	return;
}

if ( $type === 'ticket' ) {
	$trac = \Dotorg\Slack\Trac\Trac::get( $trac );
	if ( ! $trac->is_public() ) {
		// Comment emails are parsed, but ticket emails are
		// only used as a trigger for an HTTP request to the
		// ticket to retrieve a CSV. Bail for non-public tickets.
		return;
	}

	$new_ticket = new \Dotorg\Slack\Trac\New_Ticket( $trac, $ticket );
	$new_ticket->fetch();

	foreach ( $trac->get_ticket_channels( $new_ticket ) as $channel ) {
		$send = new \Dotorg\Slack\Send( WEBHOOK );
		$send->set_user( $new_ticket );

		if ( 'title' === $trac->get_ticket_format( $channel ) ) {
			$send->set_text( $new_ticket->get_text() );
		} else {
			$send->add_attachment( $new_ticket->get_attachment() );
		}

		$send->send( $channel );
	}
	$payload = (array) $new_ticket->fetch();
	array_shift( $payload );
	$payload['ticket_id'] = $new_ticket->id;
	$payload['ticket_url'] = $new_ticket->get_url();
	$payload['trac'] = $trac->get_slug();
	maybe_add_ticket_cc_to_payload( $payload );
	send_mentions_payload( $payload );
} else {
	$send = new \Dotorg\Slack\Send( WEBHOOK );
	$handler = new \Dotorg\Slack\Trac\Comment_Handler( $send, $lines );
	$handler->run();

	if ( false !== strpos( $handler->comment, '#!CommitTicketReference' ) ) {
		return;
	}

	$payload = compact( 'type', 'trac' );
	$properties = array( 'author', 'comment', 'changes', 'ticket_id', 'ticket_url', 'comment_id', 'comment_url' );
	foreach ( $properties as $property ) {
		$payload[ $property ] = $handler->$property;
	}
	$payload['summary'] = $handler->title;
	maybe_add_ticket_cc_to_payload( $payload );
	send_mentions_payload( $payload );
}

function send_mentions_payload( $payload ) {
	$payload = json_encode( $payload );
	$secret  = MENTIONS_API_KEY;
	$content = http_build_query( compact( 'payload', 'secret' ) );

	$context = stream_context_create( array(
		'http' => array(
			'method'  => 'POST',
			'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
			'content' => $content,
		),
	) );

	return file_get_contents( MENTIONS_API_HANDLER, false, $context );
}

function maybe_add_ticket_cc_to_payload( &$payload ) {
	if ( $payload['trac'] === 'security' && file_exists( __DIR__ . '/security-trac-cc.sh' ) ) {
		$cc = trim( shell_exec( escapeshellcmd( __DIR__ . '/security-trac-cc.sh ' . escapeshellarg( (int) $payload['ticket_id'] ) ) ) );
		if ( $cc ) {
			$payload['cc'] = $cc;
		}
	}
}
