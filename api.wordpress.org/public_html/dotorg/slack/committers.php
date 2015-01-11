<?php

// Allow committers to publicly mention other committers via @committers.

namespace Dotorg\Slack\Committers;

require dirname( __DIR__ ) . '/slack-config.php';

if ( $_POST['token'] !== WEBHOOK_TOKEN ) {
	return;
}

// These should be Slack usernames.
switch ( $_POST['channel_name'] ) {
	case 'bbpress' :
	case 'buddypress' :
	case 'glotpress' :
		return;
	default:
		$committers = array(
			'westi', 'azaozz', 'nb', 'josephscott', 'dd32', 'nacin', 'koop', 'duck_',
			'helen', 'sergeybiryukov', 'ocean90', 'wonderboymusic', 'drew', 'mark',
			'johnbillion', 'kovshenin', 'jorbin', 'boone', 'jeremyfelt', 'pento',
		);
		break;
}

if ( in_array( $_POST['user_name'], $committers, true ) ) {
	$notify = array_diff( $committers, array( $_POST['user_name'] ) );
	$notify = '(cc: @' . implode( ' @', $notify ) . ')';
	echo json_encode( array( 'text' => $notify, 'username' => $_POST['user_name'], 'link_names' => 1 ) );
}
