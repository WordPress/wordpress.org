<?php

namespace Dotorg\Slack\Announce;
use Dotorg\Slack\Send;

require_once __DIR__ . '/config.php';

function get_whitelist_for_channel( $channel ) {
	$whitelist = get_whitelist();
	if ( isset( $whitelist[ $channel ] ) ) {
		return $whitelist[ $channel ];
	}
	return array();
}

function get_whitelisted_channels_for_user( $user ) {
	$whitelist = get_whitelist();
	$whitelisted = array();
	foreach ( $whitelist as $channel => $users ) {
		if ( in_array( $user, $users, true ) ) {
			$whitelisted[] = $channel;
		}
	}

	return $whitelisted;
}

function is_user_whitelisted( $user, $channel ) {
	if ( $channel === 'privategroup' ) {
		// 'privategroup' is special on Slack's end.
		// Let's assume anyone in a private group can send to private groups.
		return true;
	}

	$whitelist = get_whitelist_for_channel( $channel );
	return in_array( $user, $whitelist, true );
}

function show_authorization( $user, $channel ) {
	$channels = get_whitelisted_channels_for_user( $user ) ;
	if ( $channel === 'privategroup' ) {
		echo "Any private group members can use @channel in this group.";
		# return;
	} elseif ( empty( $channels ) ) {
		echo "You are not allowed to use @channel.";
	} elseif ( in_array( $channel, $channels ) ) {
		$channels = array_filter( $channels, function( $c ) use ( $channel ) { return $c !== $channel; } );
		if ( $channels ) {
			printf( "You are allowed to use @channel in #%s (also %s).", $channel, '#' . implode( ' #', $channels ) );
		} else {
			echo "You are allowed to use @channel in #$channel.";
		}
	} else {
		printf( "You are not allowed to use @channel in #%s, but you are in #%s.", $channel, implode( ' #', $channels ) );
	}

	printf( " If you are a team lead and need to be whitelisted, contact an admin in <#%s|%s> for assistance.", SLACKHELP_CHANNEL_ID, SLACKHELP_CHANNEL_NAME );
}

function run( $data ) {
	$user = $data['user_name'];
	$channel = $data['channel_name'];

	if ( empty( $data['text'] ) ) {
		show_authorization( $user, $channel );
		return;
	}

	if ( ! is_user_whitelisted( $user, $channel ) ) {
		return;
	}

	$send = new Send( \Dotorg\Slack\Send\WEBHOOK );
	$send->set_username( $user );
	$send->set_text( sprintf( "<!channel> %s", $data['text'] ) );

	$get_avatar = __NAMESPACE__ . '\\' . 'get_avatar';
	if ( function_exists( $get_avatar ) ) {
		$send->set_icon( call_user_func( $get_avatar, $data['user_name'], $data['user_id'], $data['team_id'] ) );
	}
	
	// By sending the channel ID, we can post to private groups.
	$send->send( $data['channel_id'] );
}

