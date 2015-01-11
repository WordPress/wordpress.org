<?php

namespace Dotorg\Slack\Trac;
use Dotorg\Slack\Send;

require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

if ( 'cli' === PHP_SAPI ) {
	if ( $argc !== 3 ) {
		echo "Usage: <trac> <ticket>\n";
		exit( 1 );
	}

	list( , $trac_slug, $ticket ) = $argv;
} else {
	if ( $_REQUEST['token'] !== URL_SECRET__NEW_TICKET ) {
		return;
	}
	$trac_slug = preg_replace( '/[^a-z]/', '', $_POST['trac'] );
	$ticket = (int) $_POST['ticket'];
}

$trac = Trac::get( $trac_slug );
$new_ticket = new New_Ticket( $trac, $ticket );

foreach ( $trac->get_ticket_channels( $new_ticket ) as $channel ) {
	$send = new Send( \Dotorg\Slack\Send\WEBHOOK );
	$send->set_user( $new_ticket );

	if ( 'title' === $trac->get_ticket_format( $channel ) ) {
		$send->set_text( $new_ticket->get_text() );
	} else {
		$send->add_attachment( $new_ticket->get_attachment() );
	}

	$send->send( $channel );
}
