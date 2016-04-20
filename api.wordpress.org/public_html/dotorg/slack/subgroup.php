<?php

namespace Dotorg\Slack\Subgroup;

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

// Confirm it came from Slack.
if ( $_POST['token'] !== WEBHOOK_TOKEN ) {
	die;
}

$current_group_id = $_POST['channel_id'];

// If it didn't come from a group, die.
if ( $current_group_id[0] !== 'G' ) {
	die;
}

// Get a list of all groups @wordpressdotorg is in.
$groups = api_call( 'groups.list' )['groups'];

// Find the group that we are in right now.
foreach ( $groups as $group ) {
	if ( $group['id'] === $current_group_id ) {
		$found = true;
		break;
	}
}

// Sorry - @wordpressdotorg isn't in this group.
if ( empty( $found ) ) {
	die( "@wordpressdotorg isn't here." );
}

// Can't call this from a subgroup.
if ( $pos = strpos( $group['name'], '-' ) ) {
	die( sprintf( 'Call this from the main `%s` group.', substr( $group['name'], 0, $pos ) ) );
}

// Ensure the calling user is in the main group.
if ( ! in_array( $_POST['user_id'], $group['members'], true ) ) {
	die;
}

// Commands: create, join, list (default)
list( $command, $subcommand ) = explode( ' ', $_POST['text'] );

switch ( $command ) {
	case 'create':
		if ( 0 !== strpos( $subcommand, $group['name'] . '-' ) ) {
			die( "Must create a group that starts with `{$group['name']}-`." );
		}
	
		$new_group = api_call( 'groups.create', array( 'name' => $subcommand ) );
		if ( empty( $new_group['ok'] ) ) {
			die( "Group creation failed. Does it already exist?" );
		}
	
		foreach ( $group['members'] as $member ) {
			api_call( 'groups.invite', array( 'channel' => $new_group['group']['id'], 'user' => $member ) );
		}
	
		api_call( 'chat.postMessage', array(
			'channel' => $group['id'],
			'text'    => sprintf( 'Group %s created by <@%s>.', $new_group['group']['name'], $_POST['user_id'] ),
			'as_user' => true,
		) );

		die;
	
	case 'invite':
	case 'join':
		foreach ( $groups as $group ) {
			if ( $group['name'] === $subcommand ) {
				api_call( 'groups.invite', array( 'channel' => $group['id'], 'user' => $_POST['user_id'] ) );
				die( "Invited to {$group['name']}." );
			}
		}
	
		die( "$subcommand group not found." );
	
	case 'list':
	default:
		$groups_to_add = array();
	
		$parent_group = $group;
		foreach ( $groups as $group ) {
			if ( strpos( $group['name'], $parent_group['name'] . '-' ) === 0 ) {
				if ( ! in_array( $_POST['user_id'], $group['members'], true ) ) {
					$groups_to_add[] = $group['name'];
				}
			}
		}
	
		if ( $groups_to_add ) {
			echo "You may join any of these groups:\n -- `" . implode( "`\n -- `", $groups_to_add ) . "`\n\n\n";
		} else {
			echo "You are in all {$parent_group['name']} subgroups that the @wordpressdotorg user knows about.\n\n";
		}
	
		die( "*Help:*\n`/subgroup list` - display this message\n`/subgroup join {name}` - join a subgroup\n`/subgroup create {name}` - create a subgroup" );
}
	
