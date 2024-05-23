<?php

namespace Dotorg\Slack\Announce;
use Dotorg\Slack\Send;

require_once __DIR__ . '/config.php';

function api_call( $method, $content = array() ) {
	$content['token'] = SLACK_TOKEN;
	$content = http_build_query( $content );
	$context = stream_context_create( array(
	    'http' => array(
		'method'  => 'POST',
		'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
		'content' => $content,
	    ),
	) );

	$response = file_get_contents( 'https://slack.com/api/' . $method, false, $context );
	return json_decode( $response, true );
}

function get_channel_info( $channel_id ) {
	$channel_info = api_call(
		'conversations.info',
		array(
			'channel' => $channel_id,
		)
	);

	return $channel_info['channel'] ?? false;
}

/**
 * Get the list of whitelisted users for a channel.
 * Includes parent channel whitelisted users.
 *
 * @param string $channel The channel to get the whitelist for.
 * @return array
 */
function get_whitelist_for_channel( $channel ) {
	$whitelist       = get_whitelist();
	$users           = $whitelist[ $channel ] ?? [];
	$parent_channels = get_parent_channels( $channel );

	foreach ( (array) $parent_channels as $parent ) {
		// Avoid any circular references.
		if ( ! $parent || $parent === $channel ) {
			continue;
		}

		$users = array_merge( $users, $whitelist[ $parent ] ?? [] );
	}

	// Some users are listed twice, due to array_merge() in config & parent channel above.
	return array_unique( $users );
}

/**
 * Get the whitelisted channels for a user.
 * 
 * @param $user           string The user to get the list of channels for.
 * @param $known_channels array  An optional list of channels which are known, so messaging can correctly identify a channel that's not whitelisted. Optional.
 */
function get_whitelisted_channels_for_user( $user, $known_channels = array() ) {
	// Note: Due to inherited whitelisting from parent channels, only channels which are known by config.php will be listed.
	// although the user might have access to other #parent-xxxx channels that are unknown to the API.
	// $known_channels attempts to help with this, ensuring that the authorization message has the context of the current channel.

	$whitelist   = get_whitelist();
	$whitelisted = array();

	$channels = array_unique( array_merge( array_keys( $whitelist ), (array) $known_channels ) );

	foreach ( $channels as $channel ) {
		if ( is_user_whitelisted( $user, $channel ) ) {
			$whitelisted[] = $channel;
		}
	}

	sort( $whitelisted );

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

	echo "Valid commands are /at-channel for an @channel, and /announce or /here to perform an @here.\n";

	$channels = get_whitelisted_channels_for_user( $user, $channel );
	if ( $channel === 'privategroup' ) {
		echo "Any private group members can use these commands in this group.";
		return;
	} elseif ( empty( $channels ) ) {
		echo "You are not allowed to use these commands.";
	} elseif ( in_array( $channel, $channels ) ) {
		$channels = array_filter( $channels, function( $c ) use ( $channel ) { return $c !== $channel; } );
		if ( $channels ) {
			printf( "You are allowed to use these commands in #%s (also %s).", $channel, '#' . implode( ' #', $channels ) );
		} else {
			echo "You are allowed to use these commands in in #$channel.";
		}
	} else {
		printf( "You are not allowed to use these commands in #%s, but you are in #%s.", $channel, implode( ' #', $channels ) );
	}

	echo "\n";

	printf( "If you are a team lead and need to be granted access, contact an admin in <#%s|%s> for assistance.\n", SLACKHELP_CHANNEL_ID, SLACKHELP_CHANNEL_NAME );
	printf( "Your linked WordPress.org account that needs to be granted access is '%s'.", $user );
}

/**
 * Return the parent channels for a channel.
 *
 * @param string $channel The channel to get the parent channel for. eg. 'foobar-example'
 * @return array|false The parent channels, or false if there is no parent channel.
 */
function get_parent_channels( $channel ) {
	// Private groups are not actually channels.
	if ( 'privategroup' === $channel ) {
		return false;
	}

	list( $root, ) = explode( '-', $channel, 2 );

	// Some channels parents are not a 1:1 match.
	switch ( $root ) {
		case 'design':
		case 'feature':
		case 'performance':
		case 'tide':
		case 'core':
			$root = 'core';
			break;
		case 'mentorship': // Such as #mentorship-cohort-july-2023
			$root = 'contributor-mentorship';
			break;
		case 'community':
			$root = 'community-team';
			break;
	}

	// Such as #6-4-release-leads, or #6-1-site-editor-merge
	if ( preg_match( '!^\d-\d-!i', $channel ) ) {
		$root = 'core';
	}

	$parent_channels = [];

	// For when a channel has multiple parents.

	// Accessibility is a sub-team of Core, but is a parent channel itself.
	if ( 'accessibility' === $root ) {
		$parent_channels[] = 'core';
	}

	// Learn is a sub-team of Training, plus of #meta.
	if ( 'meta-learn' === $channel ) {
		$parent_channels[] = 'training';
	}

	// Is it an actual channel? Assume that there'll always be at least one whitelisted user for the parent channel.
	if (
		$root !== $channel &&
		get_whitelist_for_channel( $root )
	) {
		$parent_channels[] = $root;
	}

	return array_unique( $parent_channels ) ?: false;
}

function run( $data ) {
	global $wpdb;

	/* Respond with a 200 ASAP.
	 * The inline API calls might delay this more than 3s, which will cause Slack to error (or retry).
	 * We don't need to respond with the body until later, but the 200 header must make it back within 3s.
	 */
	http_response_code( 200 );
	ignore_user_abort( true );
	flush();

	$channel           = $data['channel_name'];
	$channel_id        = $data['channel_id'];
	$user              = false;
	$slack_profiledata = false;
	$channel_info      = false;

	// Slack sends the channel_name as 'privategroup' for old private channels, but the actual private channel name for newer private channels.
	if ( 'privategroup' !== $channel ) {
		$channel_info = get_channel_info( $channel_id );

		if (
			$channel_info &&
			( $channel_info['is_private'] || $channel_info['is_group'] || $channel_info['is_mpim'] )
		) {
			$channel = 'privategroup';
		}
	}

	// Find the user_login for the Slack user_id
	if ( isset( $data['user_id'] ) ) {
		$db_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT user_login, profiledata
			FROM slack_users
				JOIN {$wpdb->users} ON slack_users.user_id = {$wpdb->users}.id
			WHERE slack_id = %s",
			$data['user_id']
		) );

		$user = $db_row->user_login ?? false;
		$slack_profiledata = json_decode( ($db_row->profiledata ?? '{}'), true );
	}

	// Default back to the historical 'user_name' Slack field.
	if ( ! $user ) {
		$user = $data['user_name'];
	}

	if ( empty( $data['text'] ) ) {
		show_authorization( $user, $channel );
		return;
	}

	if ( ! is_user_whitelisted( $user, $channel ) ) {
		show_authorization( $user, $channel );
		return;
	}

	if ( str_word_count( $data['text'] ) <= 2 ) {
		printf( "When making announcements, please use a descriptive message for notifications. %s is too short.", $data['text'] );
		return;
	}

	// Default to an @here, unless explicitely an @channel OR it's a private group.
	$command = 'here';
	if ( $data['command'] === '/at-channel' ) {
		$command = 'channel';
	} elseif ( $channel === 'privategroup' ) {
		// @channel and @group are interchangeable.
		$command = 'group';
	}

	// Use their Slack Display name, falling back to their WordPress.org login if that's not available.
	$display_name = $user;
	if ( ! empty( $slack_profiledata['profile']['display_name'] ) ) {
		$display_name = $slack_profiledata['profile']['display_name'];
	}

	$avatar = false;
	// Respect the avatar set in Slack, and prefer it over their Gravatar.
	if ( ! empty( $slack_profiledata['profile']['image_192'] ) ) {
		$avatar = $slack_profiledata['profile']['image_192'];
	}
	$get_avatar = __NAMESPACE__ . '\\' . 'get_avatar';
	if ( ! $avatar && function_exists( $get_avatar ) ) {
		$avatar = call_user_func( $get_avatar, $data['user_name'], $data['user_id'], $data['team_id'] );
	}

	$text = sprintf( "<!%s> %s", $command, $data['text'] );

	$send = new Send( \Dotorg\Slack\Send\WEBHOOK );
	$send->set_username( $display_name );
	$send->set_text( $text );
	$send->set_link_names( true );
	if ( $avatar ) {
		$send->set_icon( $avatar );
	}

	// By sending the channel ID, we can post to private groups.
	$send->send( $channel_id );

	// Broadcast this message as a non-@here to the "parent" channel too.
	$parent_channels = get_parent_channels( $channel );

	// Validate the parent channel exists.
	if ( ! $parent_channels ) {
		return;
	}

	// Don't send to these parent channels.
	$dont_send_to = [
		'contributor-mentorship',
	];

	$text = $data['text'];
	// Remove any @here or @channel
	$text = str_ireplace( [ '@here', '@channel', '@group' ], '', $text );
	if ( mb_strlen( $text ) > 103 ) {
		$text = mb_substr( $text, 0, 100 ) . '...';
	}

	foreach ( $parent_channels as $parent_channel ) {
		if ( in_array( $parent_channel, $dont_send_to, true ) ) {
			continue;
		}

		$send->set_text( 'In #' . $channel . ': ' . $text );
		$send->send( '#' . $parent_channel );
	}
}

