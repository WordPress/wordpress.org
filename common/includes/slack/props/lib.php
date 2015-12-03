<?php

namespace Dotorg\Slack\Props;
use Dotorg\Slack\Send;

require_once __DIR__ . '/config.php';

function show_error( $user ) {
	if ( ! in_array( $user, get_whitelist() ) ) {
		echo "You are not allowed to use /props.\n\n";
		printf( "If you are a team lead and need to be whitelisted, contact an admin in <#%s|%s> for assistance.", SLACKHELP_CHANNEL_ID, SLACKHELP_CHANNEL_NAME );
		return;
	}

	echo 'Please use `/props SLACK_USERNAME MESSAGE` to give props.';
}

function run( $data, $force_test = false ) {
	$sender = $data['user_name'];

	if ( $data['command'] !== '/props' ) {
		echo '???';
		return;
	}

	if ( empty( $data['text'] ) ) {
		show_error( $sender );
		return;
	}

	if ( ! in_array( $sender, get_whitelist() ) ) {
		show_error( $sender );
		return;
	}

	list( $receiver, $message ) = @preg_split( '/\s+/', trim( $data['text'] ), 2 );

	$receiver = ltrim( $receiver, '@' );

	if ( ! strlen( $receiver ) || ! strlen( $message ) ) {
		show_error( $sender );
		return;
	}

	// TODO: Add WordPress.org username to $text if different than Slack username.
	$text = sprintf( "Props to @%s: %s", $receiver, $message );

	$send = new Send( \Dotorg\Slack\Send\WEBHOOK );
	$send->set_username( $sender );
	$send->set_text( $text );

	$get_avatar = __NAMESPACE__ . '\\' . 'get_avatar';

	if ( function_exists( $get_avatar ) ) {
		$send->set_icon( call_user_func( $get_avatar, $sender, $data['user_id'], $data['team_id'] ) );
	}
	
	if ( $force_test ) {
		$send->testing( true );
	}

	$send->send( '#props' );
}
