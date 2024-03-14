<?php

namespace Dotorg\Slack\Subgroup;
use const Dotorg\Slack\WORDPRESSORG_USER_ID as WORDPRESSORG_SLACK_USER_ID;

require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

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

// WARNING: Can only be called FIVE times per request.
function update_text( $text ) {
	if ( empty( $_POST['response_url'] ) ) {
		die( "response_url missing." );
	}

	$payload = [
		'response_type'    => 'ephemeral',
		'replace_original' => 'true', // Doesn't work.
		'text'             => $text,
	];

	$context = stream_context_create( [ 'http' => [
		'method'  => 'POST',
		'header'  => 'Content-Type: application/json',
		'content' => json_encode( $payload ),
	] ] );

	file_get_contents( $_POST['response_url'], false, $context );
}

function die_text( $text ) {
	update_text( $text );
	die();
}

// Confirm it came from Slack.
if ( $_POST['token'] !== WEBHOOK_TOKEN ) {
	die( "Invalid Token" );
}

// This API needs to return a successful 200 ASAP, within 3 seconds, and most of the API calls below will take longer than.
header( 'HTTP/1.0 200 OK' );
flush();
if ( function_exists( 'fastcgi_finish_request' ) ) {
	fastcgi_finish_request();
}

update_text( 'Please wait.. This could take a moment..' );

$current_group_id = $_POST['channel_id'];

// Note: We no longer check to see if the calling channel is a group, as the below list command will limit it to private channels.
// Groups can begin with `G` (old created groups) or `C` (private channel), public channels also begin with `C` so the below check is safer.

// Get a list of all groups @wordpressdotorg is in.
$groups = api_call(
	// https://api.slack.com/methods/conversations.list
	'conversations.list',
	[
		'exclude_archived' => true,
		'types'            => 'private_channel',
		'limit'            => 999,
	]
)['channels'];

// Find the group that we are in right now.
foreach ( $groups as $group ) {
	if ( $group['id'] === $current_group_id ) {
		$found = true;
		break;
	}
}

// Sorry - @wordpressdotorg isn't in this group.
if ( empty( $found ) ) {
	die_text( "@wordpressdotorg isn't here." );
}

// Can't call this from a subgroup.
if ( $pos = strpos( $group['name'], '-' ) ) {
	die_text( sprintf( 'Call this from the main `%s` group.', substr( $group['name'], 0, $pos ) ) );
}

// Get the current groups members.
$members = api_call(
	// https://api.slack.com/methods/conversations.members
	'conversations.members',
	[
		'channel' => $group['id'],
		'limit'   => 999,
	]
)['members'];

// Ensure the calling user is in the main group.
if ( ! in_array( $_POST['user_id'], $members, true ) ) {
	die_text( 'Unauthorized' );
}

// Commands: create, join, list (default)
list( $command, $subcommand ) = explode( ' ', $_POST['text'] );

switch ( $command ) {
	case 'create':
	case 'create-empty':
		if ( 0 !== strpos( $subcommand, $group['name'] . '-' ) ) {
			die_text( "Must create a group that starts with `{$group['name']}-`." );
		}

		// Determine who to invite to the new channel.
		if ( 'create-empty' === $command ) {
			// Just the requester should be invited to the channel. WordPress.org will be added automatically as the group creator.
			$members_to_invite = [
				$_POST['user_id']
			];
		} else {
			// Invite all members.
			$members_to_invite = $members;
		}

		// Create the new private channel.
		$new_group = api_call(
			'conversations.create',
			[
				'name'       => $subcommand,
				'is_private' => true,
			]
		);
		if ( empty( $new_group['ok'] ) ) {
			die_text( "Group creation failed. Does it already exist?" );
		}

		// Post a message to the parent channel about this channels creation.
		api_call(
			// https://api.slack.com/methods/chat.postMessage
			'chat.postMessage',
			[
				'channel' => $group['id'],
				'text'    => sprintf(
					'Group %s created by <@%s>.',
					$new_group['channel']['name'],
					$_POST['user_id']
				),
				'as_user' => true,
			]
		);

		// Cannot invite self to group.
		$members_to_invite = array_diff( $members_to_invite, [ WORDPRESSORG_SLACK_USER_ID ] );

		// Invite users to the new channel.
		api_call(
			// https://api.slack.com/methods/conversations.invite
			'conversations.invite',
			[
				'channel' => $new_group['channel']['id'],
				'users'   => implode( ',', $members_to_invite ),
			]
		);

		die_text( sprintf( "Group %s created.", $new_group['group']['name'] ) );

	case 'invite':
	case 'join':
		foreach ( $groups as $group ) {
			if ( $group['name'] === $subcommand ) {
				api_call(
					// https://api.slack.com/methods/conversations.invite
					'conversations.invite',
					[
						'channel' => $group['id'],
						'users'   => $_POST['user_id'],
					]
				);

				die_text( "Invited to {$group['name']}." );
			}
		}

		die_text( "$subcommand group not found." );

	case 'list':
	default:
		$groups_to_add = array();

		$parent_group = $group;
		foreach ( $groups as $group ) {
			if ( strpos( $group['name'], $parent_group['name'] . '-' ) === 0 ) {

				$group_members = api_call(
					// https://api.slack.com/methods/conversations.members
					'conversations.members',
					[
						'channel' => $group['id'],
						'limit'   => 999,
					]
				);

				if ( ! in_array( $_POST['user_id'], $group_members['members'], true ) ) {
					$groups_to_add[] = $group['name'];
				}
			}
		}

		if ( $groups_to_add ) {
			$text = "You may join any of these groups:\n -- `" . implode( "`\n -- `", $groups_to_add ) . "`\n\n\n";
		} else {
			$text = "You are in all {$parent_group['name']} subgroups that the @wordpressdotorg user knows about.\n\n";
		}

		die_text(
			$text . 
			"*Help:*\n" .
			"`/subgroup list` - display this message\n" .
			"`/subgroup join {name}` - join a subgroup\n" .
			"`/subgroup create {name}` - create a subgroup with all current group members invited\n" .
			"`/subgroup create-empty {name}` - create a subgroup with no members invited"
		);
}

