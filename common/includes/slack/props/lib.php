<?php

namespace Dotorg\Slack\Props;
use Dotorg\Slack\Send;

function show_error( $user ) {
	return "Please use `/props SLACK_USERNAME MESSAGE` to give props.\n";
}

/**
 * Receive `/props` request and send to `#props`.
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
