<?php

namespace Dotorg\Slack\Subgroup;

require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

function api_call( $method, $content = array(), $token = null ) {
	$content['token'] = $token ?: SUBGROUP_BOT_TOKEN;
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

/*
 * ACK early and close the HTTP connection so slow follow-up work (API calls,
 * view refreshes) doesn't hold Slack's 3s response window open.
 */
function ack_and_finish() {
	flush();
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	}
}

function verify_slack_signature( $body ) {
	$timestamp = $_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP'] ?? '';
	$signature = $_SERVER['HTTP_X_SLACK_SIGNATURE'] ?? '';
	if ( ! $timestamp || ! $signature ) {
		return false;
	}
	// Reject stale requests to defeat replays.
	if ( abs( time() - (int) $timestamp ) > 300 ) {
		return false;
	}
	$expected = 'v0=' . hash_hmac( 'sha256', "v0:{$timestamp}:{$body}", SUBGROUP_SIGNING_SECRET );
	return hash_equals( $expected, $signature );
}

$raw_body = file_get_contents( 'php://input' );
if ( ! verify_slack_signature( $raw_body ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die( 'Invalid signature' );
}

// Dispatch: slash command vs. interactivity callback.
if ( isset( $_POST['payload'] ) ) {
	$payload = json_decode( $_POST['payload'], true );
	handle_interaction( $payload );
	exit;
}
handle_slash_command();

function handle_slash_command() {
	$channel_id = $_POST['channel_id'] ?? '';
	$user_id    = $_POST['user_id'] ?? '';
	$trigger_id = $_POST['trigger_id'] ?? '';

	// trigger_id is only valid for 3s, and listing every subgroup's membership will take
	// longer than that. Open a loading view now, then views.update after we have the data.
	$loading_view = [
		'type'             => 'modal',
		'callback_id'      => 'subgroup_home',
		'title'            => [ 'type' => 'plain_text', 'text' => 'Subgroups' ],
		'close'            => [ 'type' => 'plain_text', 'text' => 'Close' ],
		'private_metadata' => json_encode( [ 'parent' => $channel_id, 'user' => $user_id ] ),
		'blocks'           => [
			[ 'type' => 'section', 'text' => [ 'type' => 'mrkdwn', 'text' => '_Loading subgroups…_' ] ],
		],
	];

	ack_and_finish();

	$opened = api_call( 'views.open', [
		'trigger_id' => $trigger_id,
		'view'       => json_encode( $loading_view ),
	] );
	if ( empty( $opened['ok'] ) ) {
		return;
	}

	$view_id = $opened['view']['id'];

	$parent = find_channel( $channel_id );
	if ( ! $parent ) {
		update_view_message( $view_id, sprintf( "@wordpressdotorg & <@%s> isn't here.", SUBGROUP_BOT_USER_ID ) );
		return;
	}

	$parent_members = get_members( $parent['id'] );
	if ( ! in_array( $user_id, $parent_members, true ) ) {
		update_view_message( $view_id, 'Unauthorized — you must be a member of this channel to manage its subgroups.' );
		return;
	}

	refresh_home_view( $view_id, $parent, $user_id );
}

function handle_interaction( $payload ) {
	switch ( $payload['type'] ?? '' ) {
		case 'block_actions':
			handle_block_action( $payload );
			return;
		case 'view_submission':
			handle_view_submission( $payload );
			return;
	}
}

function handle_block_action( $payload ) {
	$action    = $payload['actions'][0] ?? [];
	$action_id = $action['action_id'] ?? '';
	$meta      = json_decode( $payload['view']['private_metadata'] ?? '{}', true );
	$user_id   = $payload['user']['id'] ?? '';
	$view_id   = $payload['view']['id'] ?? '';

	switch ( $action_id ) {
		case 'join_subgroup':
			ack_and_finish();

			$channel_to_join = $action['value'] ?? '';
			if ( $channel_to_join ) {
				api_call( 'conversations.invite', [
					'channel' => $channel_to_join,
					'users'   => $user_id,
				] );
			}

			$parent_id   = $meta['parent'] ?? '';
			$parent_name = $meta['parent_name'] ?? '';
			if ( $parent_id && $parent_name ) {
				refresh_home_view( $view_id, [ 'id' => $parent_id, 'name' => $parent_name ], $user_id );
			}
			return;

		case 'open_manage_modal':
			$parent_id   = $meta['parent'] ?? '';
			$parent_name = $meta['parent_name'] ?? '';
			if ( ! $parent_id || ! $parent_name ) {
				return;
			}
			// get_members runs once per subgroup, so a full manage build can
			// exceed Slack's 3s trigger_id budget. Push a loading view now and
			// views.update with the real content afterwards.
			$loading = [
				'type'             => 'modal',
				'callback_id'      => 'subgroup_manage',
				'title'            => [ 'type' => 'plain_text', 'text' => 'Manage subgroups' ],
				'close'            => [ 'type' => 'plain_text', 'text' => 'Close' ],
				'private_metadata' => json_encode( [
					'parent'      => $parent_id,
					'parent_name' => $parent_name,
					'user'        => $user_id,
					'root_view'   => $view_id,
				] ),
				'blocks'           => [
					[ 'type' => 'section', 'text' => [ 'type' => 'mrkdwn', 'text' => '_Loading subgroups…_' ] ],
				],
			];
			$pushed = api_call( 'views.push', [
				'trigger_id' => $payload['trigger_id'] ?? '',
				'view'       => json_encode( $loading ),
			] );

			ack_and_finish();

			if ( empty( $pushed['ok'] ) ) {
				return;
			}
			$parent = [ 'id' => $parent_id, 'name' => $parent_name ];
			api_call( 'views.update', [
				'view_id' => $pushed['view']['id'],
				'view'    => json_encode( build_manage_view( $parent, $user_id, $view_id ) ),
			] );
			return;

		case 'rename_subgroup':
			$channel_id  = $action['value'] ?? '';
			$parent_id   = $meta['parent'] ?? '';
			$parent_name = $meta['parent_name'] ?? '';
			if ( ! $channel_id || ! $parent_id || ! $parent_name ) {
				return;
			}
			$parent       = [ 'id' => $parent_id, 'name' => $parent_name ];
			$info         = api_call( 'conversations.info', [ 'channel' => $channel_id ] );
			$current_name = $info['channel']['name'] ?? '';
			api_call( 'views.push', [
				'trigger_id' => $payload['trigger_id'] ?? '',
				'view'       => json_encode( build_rename_view( $parent, $channel_id, $current_name, $user_id, $view_id, $meta['root_view'] ?? '' ) ),
			] );
			return;

		case 'reopen_subgroup':
			ack_and_finish();

			$reopen_id = $action['value'] ?? '';
			$parent_id = $meta['parent'] ?? '';
			$root_view = $meta['root_view'] ?? '';

			if ( $reopen_id ) {
				$result = api_call( 'conversations.unarchive', [
					'channel' => $reopen_id,
				], SUBGROUP_USER_TOKEN );

				if ( ! empty( $result['ok'] ) ) {
					// Pull the bot in if it wasn't a member pre-archive.
					// already_in_channel is fine to ignore.
					api_call( 'conversations.invite', [
						'channel' => $reopen_id,
						'users'   => SUBGROUP_BOT_USER_ID,
					], SUBGROUP_USER_TOKEN );

					$info          = api_call( 'conversations.info', [ 'channel' => $reopen_id ] );
					$reopened_name = $info['channel']['name'] ?? 'unknown';

					api_call( 'chat.postMessage', [
						'channel' => $parent_id,
						'text'    => sprintf( '<@%s> has reopened archived channel: <#%s> (`%s`)', $user_id, $reopen_id, $reopened_name ),
					] );
				}
			}

			$parent_name = $meta['parent_name'] ?? '';
			if ( $parent_id && $parent_name ) {
				$parent = [ 'id' => $parent_id, 'name' => $parent_name ];
				api_call( 'views.update', [
					'view_id' => $view_id,
					'view'    => json_encode( build_manage_view( $parent, $user_id, $root_view ) ),
				] );
				if ( $root_view ) {
					refresh_home_view( $root_view, $parent, $user_id );
				}
			}
			return;

		case 'archive_subgroup':
			ack_and_finish();

			$archive_id = $action['value'] ?? '';
			$parent_id  = $meta['parent'] ?? '';
			$root_view  = $meta['root_view'] ?? '';

			if ( $archive_id ) {
				$info          = api_call( 'conversations.info', [ 'channel' => $archive_id ] );
				$archived_name = $info['channel']['name'] ?? 'unknown';

				$result = api_call( 'conversations.archive', [
					'channel' => $archive_id,
				], SUBGROUP_USER_TOKEN );

				if ( ! empty( $result['ok'] ) ) {
					// Don't link the archived channel — viewers who weren't
					// members can't reach it anyway, and the mention renders
					// as a strikethrough dead link.
					api_call( 'chat.postMessage', [
						'channel' => $parent_id,
						'text'    => sprintf( '<@%s> has archived channel: `%s`', $user_id, $archived_name ),
					] );
				}
			}

			$parent_name = $meta['parent_name'] ?? '';
			if ( $parent_id && $parent_name ) {
				$parent = [ 'id' => $parent_id, 'name' => $parent_name ];
				// Refresh the manage view (so the archived row disappears).
				api_call( 'views.update', [
					'view_id' => $view_id,
					'view'    => json_encode( build_manage_view( $parent, $user_id, $root_view ) ),
				] );
				// Refresh the underlying home view too so it's in sync when
				// the user closes the picker.
				if ( $root_view ) {
					refresh_home_view( $root_view, $parent, $user_id );
				}
			}
			return;

		case 'open_create_modal':
			$parent_id   = $meta['parent'] ?? '';
			$parent_name = $meta['parent_name'] ?? '';
			if ( ! $parent_id || ! $parent_name ) {
				return;
			}
			$parent = [ 'id' => $parent_id, 'name' => $parent_name ];
			api_call( 'views.push', [
				'trigger_id' => $payload['trigger_id'] ?? '',
				'view'       => json_encode( build_create_view( $parent, $user_id, $view_id ) ),
			] );
			return;
	}
}

function handle_view_submission( $payload ) {
	$cb = $payload['view']['callback_id'] ?? '';
	if ( $cb === 'subgroup_create' ) {
		handle_create_submission( $payload );
		return;
	}
	if ( $cb === 'subgroup_conflict' ) {
		handle_conflict_submission( $payload );
		return;
	}
	if ( $cb === 'subgroup_rename' ) {
		handle_rename_submission( $payload );
		return;
	}
}

function handle_rename_submission( $payload ) {
	$view   = $payload['view'];
	$meta   = json_decode( $view['private_metadata'] ?? '{}', true );
	$values = $view['state']['values'] ?? [];

	$parent_id    = $meta['parent'] ?? '';
	$parent_name  = $meta['parent_name'] ?? '';
	$user_id      = $payload['user']['id'] ?? '';
	$root_view    = $meta['root_view'] ?? '';
	$manage_view  = $meta['manage_view'] ?? '';
	$channel_id   = $meta['channel_id'] ?? '';
	$current_name = $meta['current_name'] ?? '';

	$new_name = trim( $values['name']['value']['value'] ?? '' );
	$prefix   = $parent_name . '-';

	if ( strpos( $new_name, $prefix ) !== 0 || strlen( $new_name ) <= strlen( $prefix ) ) {
		header( 'Content-Type: application/json' );
		echo json_encode( [
			'response_action' => 'errors',
			'errors'          => [ 'name' => "Must start with \"{$prefix}\" and include a name after the dash." ],
		] );
		return;
	}
	if ( $new_name === $current_name ) {
		header( 'Content-Type: application/json' );
		echo json_encode( [
			'response_action' => 'errors',
			'errors'          => [ 'name' => 'New name is the same as the current one.' ],
		] );
		return;
	}

	$rename = api_call( 'conversations.rename', [
		'channel' => $channel_id,
		'name'    => $new_name,
	], SUBGROUP_USER_TOKEN );
	if ( empty( $rename['ok'] ) ) {
		$err = $rename['error'] ?? 'unknown';
		$msg = $err === 'name_taken' ? "\"{$new_name}\" is already taken." : "Rename failed ({$err}).";
		header( 'Content-Type: application/json' );
		echo json_encode( [
			'response_action' => 'errors',
			'errors'          => [ 'name' => $msg ],
		] );
		return;
	}

	// Empty ACK closes just the rename modal, returning the user to Manage.
	ack_and_finish();

	api_call( 'chat.postMessage', [
		'channel' => $parent_id,
		'text'    => sprintf( '<@%s> renamed `%s` to <#%s> (`%s`).', $user_id, $current_name, $channel_id, $new_name ),
	] );

	if ( $parent_name ) {
		$parent = [ 'id' => $parent_id, 'name' => $parent_name ];
		if ( $manage_view ) {
			api_call( 'views.update', [
				'view_id' => $manage_view,
				'view'    => json_encode( build_manage_view( $parent, $user_id, $root_view ) ),
			] );
		}
		if ( $root_view ) {
			refresh_home_view( $root_view, $parent, $user_id );
		}
	}
}

function handle_create_submission( $payload ) {
	$view        = $payload['view'];
	$meta        = json_decode( $view['private_metadata'] ?? '{}', true );
	$parent_id   = $meta['parent'] ?? '';
	$parent_name = $meta['parent_name'] ?? '';
	$user_id     = $payload['user']['id'] ?? '';
	$root_view   = $meta['root_view'] ?? '';

	$values         = $view['state']['values'] ?? [];
	$name           = trim( $values['name']['value']['value'] ?? '' );
	$purpose        = trim( $values['purpose']['value']['value'] ?? '' );
	$topic          = trim( $values['topic']['value']['value'] ?? '' );
	$strategy       = $values['strategy']['value']['selected_option']['value'] ?? 'all';
	$selected_users = $values['users']['value']['selected_users'] ?? [];

	$prefix = $parent_name . '-';
	if ( strpos( $name, $prefix ) !== 0 || strlen( $name ) <= strlen( $prefix ) ) {
		header( 'Content-Type: application/json' );
		echo json_encode( [
			'response_action' => 'errors',
			'errors'          => [ 'name' => "Must start with \"{$prefix}\" and include a name after the dash." ],
		] );
		return;
	}

	// Workspace policy restricts channel creation to admins/owners, so the bot
	// token gets restricted_action. The user token (owner-authorized at install
	// time) inherits owner permissions and can create.
	$new = api_call( 'conversations.create', [
		'name'       => $name,
		'is_private' => true,
	], SUBGROUP_USER_TOKEN );

	// Name collision: if it's an archived channel we can see, hand off to a
	// conflict-resolution modal that lets the user pick between unarchiving
	// and renaming the archived one out of the way.
	if ( empty( $new['ok'] ) && ( $new['error'] ?? '' ) === 'name_taken' ) {
		$existing = find_private_channel_by_name( $name );
		if ( $existing && ! empty( $existing['is_archived'] ) ) {
			header( 'Content-Type: application/json' );
			echo json_encode( [
				'response_action' => 'push',
				'view'            => build_conflict_view( $existing, [
					'parent'         => $parent_id,
					'parent_name'    => $parent_name,
					'user'           => $user_id,
					'root_view'      => $root_view,
					'original_name'  => $name,
					'purpose'        => $purpose,
					'topic'          => $topic,
					'strategy'       => $strategy,
					'selected_users' => $selected_users,
				] ),
			] );
			return;
		}
		$msg = $existing
			? "An active channel named \"#{$name}\" already exists."
			: "A channel named \"{$name}\" exists but I can't see it — it may be archived in a place the bot isn't a member. Ask a Slack admin.";
		header( 'Content-Type: application/json' );
		echo json_encode( [
			'response_action' => 'errors',
			'errors'          => [ 'name' => $msg ],
		] );
		return;
	}

	if ( empty( $new['ok'] ) ) {
		$err      = $new['error'] ?? 'unknown';
		$messages = [
			'invalid_name'             => "\"{$name}\" isn't a valid channel name.",
			'invalid_name_punctuation' => 'Channel names can\'t contain punctuation.',
			'invalid_name_specials'    => 'Channel names can only contain lowercase letters, numbers, hyphens, and underscores.',
			'invalid_name_maxlength'   => 'Channel name is too long (80 character limit).',
			'invalid_name_required'    => 'A channel name is required.',
			'restricted_action'        => 'Slack refused the creation (restricted_action). The app may need its user token reinstalled by an owner.',
		];
		$msg = $messages[ $err ] ?? "Channel creation failed ({$err}).";
		header( 'Content-Type: application/json' );
		echo json_encode( [
			'response_action' => 'errors',
			'errors'          => [ 'name' => $msg ],
		] );
		return;
	}

	$new_id  = $new['channel']['id'];
	$creator = $new['channel']['creator'] ?? '';

	// Close the create view; root view is now on top again.
	ack_and_finish();

	finalize_create( $new_id, $name, $creator, $parent_id, $parent_name, $user_id, $purpose, $topic, $strategy, $selected_users, $root_view, false );
}

function handle_conflict_submission( $payload ) {
	$view   = $payload['view'];
	$meta   = json_decode( $view['private_metadata'] ?? '{}', true );
	$values = $view['state']['values'] ?? [];

	$parent_id      = $meta['parent'] ?? '';
	$parent_name    = $meta['parent_name'] ?? '';
	$user_id        = $payload['user']['id'] ?? '';
	$root_view      = $meta['root_view'] ?? '';
	$original_name  = $meta['original_name'] ?? '';
	$purpose        = $meta['purpose'] ?? '';
	$topic          = $meta['topic'] ?? '';
	$strategy       = $meta['strategy'] ?? 'all';
	$selected_users = $meta['selected_users'] ?? [];
	$archived_id    = $meta['archived_channel_id'] ?? '';
	$archived_name  = $meta['archived_channel_name'] ?? '';

	$action       = $values['action']['value']['selected_option']['value'] ?? 'unarchive';
	$new_archived = trim( $values['new_name_for_archived']['value']['value'] ?? '' );

	$prefix = $parent_name . '-';

	if ( $action === 'rename' ) {
		if ( strpos( $new_archived, $prefix ) !== 0 || strlen( $new_archived ) <= strlen( $prefix ) ) {
			header( 'Content-Type: application/json' );
			echo json_encode( [
				'response_action' => 'errors',
				'errors'          => [ 'new_name_for_archived' => "Must start with \"{$prefix}\" and include a name after the dash." ],
			] );
			return;
		}
		if ( $new_archived === $original_name ) {
			header( 'Content-Type: application/json' );
			echo json_encode( [
				'response_action' => 'errors',
				'errors'          => [ 'new_name_for_archived' => 'Pick a different name — this is the one you\'re trying to free up.' ],
			] );
			return;
		}

		$rename = api_call( 'conversations.rename', [
			'channel' => $archived_id,
			'name'    => $new_archived,
		], SUBGROUP_USER_TOKEN );
		if ( empty( $rename['ok'] ) ) {
			$err = $rename['error'] ?? 'unknown';
			$msg = $err === 'name_taken'
				? "\"{$new_archived}\" is also already taken."
				: "Rename failed ({$err}).";
			header( 'Content-Type: application/json' );
			echo json_encode( [
				'response_action' => 'errors',
				'errors'          => [ 'new_name_for_archived' => $msg ],
			] );
			return;
		}

		$new = api_call( 'conversations.create', [
			'name'       => $original_name,
			'is_private' => true,
		], SUBGROUP_USER_TOKEN );
		if ( empty( $new['ok'] ) ) {
			$err = $new['error'] ?? 'unknown';
			header( 'Content-Type: application/json' );
			echo json_encode( [
				'response_action' => 'errors',
				'errors'          => [ 'new_name_for_archived' => "Rename succeeded but create failed ({$err}). The archived channel is now called `{$new_archived}`." ],
			] );
			return;
		}
		$new_id  = $new['channel']['id'];
		$creator = $new['channel']['creator'] ?? '';

		header( 'Content-Type: application/json' );
		echo json_encode( [ 'response_action' => 'clear' ] );
		ack_and_finish();

		api_call( 'chat.postMessage', [
			'channel' => $parent_id,
			'text'    => sprintf( '<@%s> renamed archived channel `%s` to `%s` to free the name.', $user_id, $archived_name, $new_archived ),
		] );

		finalize_create( $new_id, $original_name, $creator, $parent_id, $user_id, $purpose, $topic, $strategy, $selected_users, $root_view, false );
		return;
	}

	// Unarchive path.
	$unarch = api_call( 'conversations.unarchive', [
		'channel' => $archived_id,
	], SUBGROUP_USER_TOKEN );
	if ( empty( $unarch['ok'] ) ) {
		$err = $unarch['error'] ?? 'unknown';
		header( 'Content-Type: application/json' );
		echo json_encode( [
			'response_action' => 'errors',
			'errors'          => [ 'action' => "Unarchive failed ({$err})." ],
		] );
		return;
	}

	$info    = api_call( 'conversations.info', [ 'channel' => $archived_id ] );
	$creator = $info['channel']['creator'] ?? '';

	header( 'Content-Type: application/json' );
	echo json_encode( [ 'response_action' => 'clear' ] );
	ack_and_finish();

	finalize_create( $archived_id, $archived_name, $creator, $parent_id, $parent_name, $user_id, $purpose, $topic, $strategy, $selected_users, $root_view, true );
}

/**
 * Shared post-creation steps: set purpose/topic, invite members, announce to
 * the parent channel, and refresh the home view.
 *
 * The owner (via user token) is the sole member of a freshly created channel.
 * Everything that touches $new_id has to run as the owner until the bot is
 * invited in — hence SUBGROUP_USER_TOKEN for purpose/topic/invite.
 */
function finalize_create( $new_id, $name, $creator, $parent_id, $parent_name, $user_id, $purpose, $topic, $strategy, $selected_users, $root_view, $reopened ) {
	if ( $purpose ) {
		api_call( 'conversations.setPurpose', [ 'channel' => $new_id, 'purpose' => $purpose ], SUBGROUP_USER_TOKEN );
	}
	if ( $topic ) {
		api_call( 'conversations.setTopic', [ 'channel' => $new_id, 'topic' => $topic ], SUBGROUP_USER_TOKEN );
	}

	$invitees = [ $user_id, SUBGROUP_BOT_USER_ID ];
	if ( $strategy === 'all' ) {
		$invitees = array_merge( $invitees, get_members( $parent_id ) );
	} elseif ( $strategy === 'specific' ) {
		$invitees = array_merge( $invitees, $selected_users );
	}
	// For reopened channels pre-existing members remain; drop them so one
	// already_in_channel doesn't sink the rest of the batch.
	$existing_members = $reopened ? get_members( $new_id ) : [ $creator ];
	$invitees         = array_values( array_unique( array_diff( $invitees, $existing_members ) ) );
	// conversations.invite fails the entire batch on one malformed ID. Filter
	// defensively so a stray character in a config value can't sink everyone.
	$valid_invitees = array_values( array_filter( $invitees, function ( $id ) {
		return preg_match( '/^[UWB][A-Z0-9]+$/', $id );
	} ) );
	$invitees = $valid_invitees;

	if ( $invitees ) {
		api_call( 'conversations.invite', [
			'channel' => $new_id,
			'users'   => implode( ',', $invitees ),
		], SUBGROUP_USER_TOKEN );
	}

	// Include the name in backticks so non-members (who see <#C…> as a dead
	// strikethrough) still get the channel name.
	$verb = $reopened ? 'has reopened archived channel' : 'has created new channel';
	api_call( 'chat.postMessage', [
		'channel' => $parent_id,
		'text'    => sprintf( '<@%s> %s: <#%s> (`%s`)', $user_id, $verb, $new_id, $name ),
	] );

	if ( $root_view && $parent_name ) {
		refresh_home_view( $root_view, [ 'id' => $parent_id, 'name' => $parent_name ], $user_id );
	}
}

function list_all_private_channels( $include_archived = false ) {
	$bot = api_call( 'conversations.list', [
		'exclude_archived' => ! $include_archived,
		'types'            => 'private_channel',
		'limit'            => 999,
	] );
	$channels = $bot['channels'] ?? [];

	if ( ! $include_archived ) {
		return $channels;
	}

	// Fill in archived channels the bot was never a member of by also asking
	// via the owner user token, which sees everything the owner is in.
	$user = api_call( 'conversations.list', [
		'exclude_archived' => false,
		'types'            => 'private_channel',
		'limit'            => 999,
	], SUBGROUP_USER_TOKEN );
	$merged = [];
	foreach ( $channels as $g ) {
		$merged[ $g['id'] ] = $g;
	}
	foreach ( $user['channels'] ?? [] as $g ) {
		if ( ! isset( $merged[ $g['id'] ] ) ) {
			$merged[ $g['id'] ] = $g;
		}
	}
	return array_values( $merged );
}

function find_private_channel_by_name( $name ) {
	foreach ( list_all_private_channels( true ) as $g ) {
		if ( $g['name'] === $name ) {
			return $g;
		}
	}
	return null;
}

function find_channel( $channel_id ) {
	foreach ( list_all_private_channels() as $g ) {
		if ( $g['id'] === $channel_id ) {
			return $g;
		}
	}
	return null;
}

function get_members( $channel_id ) {
	$r = api_call( 'conversations.members', [
		'channel' => $channel_id,
		'limit'   => 999,
	] );
	return $r['members'] ?? [];
}

function format_purpose( $channel ) {
	$purpose = $channel['purpose']['value'] ?? '';
	if ( ! $purpose ) {
		return '';
	}
	$newline = strpos( $purpose, "\n" );
	if ( false !== $newline ) {
		$purpose = substr( $purpose, 0, $newline );
	}
	$purpose = trim( $purpose );
	if ( mb_strlen( $purpose ) > 150 ) {
		$purpose = mb_substr( $purpose, 0, 149 ) . '…';
	}
	return $purpose;
}

function get_subgroups( $parent_name, $include_archived = false ) {
	$prefix = $parent_name . '-';
	$out    = [];
	foreach ( list_all_private_channels( $include_archived ) as $g ) {
		if ( strpos( $g['name'], $prefix ) === 0 ) {
			$out[] = $g;
		}
	}
	return $out;
}

function refresh_home_view( $view_id, $parent, $user_id ) {
	$subgroups = get_subgroups( $parent['name'] );
	api_call( 'views.update', [
		'view_id' => $view_id,
		'view'    => json_encode( build_home_view( $parent, $subgroups, $user_id ) ),
	] );
}

function build_home_view( $parent, $subgroups, $user_id ) {
	$blocks = [
		[
			'type' => 'section',
			'text' => [ 'type' => 'mrkdwn', 'text' => "*Subgroups of <#{$parent['id']}>*" ],
		],
		[ 'type' => 'divider' ],
	];

	if ( empty( $subgroups ) ) {
		$blocks[] = [
			'type' => 'section',
			'text' => [ 'type' => 'mrkdwn', 'text' => '_No subgroups yet._' ],
		];
	} else {
		foreach ( $subgroups as $g ) {
			$members   = get_members( $g['id'] );
			$is_member = in_array( $user_id, $members, true );
			$count     = count( $members );
			$purpose   = format_purpose( $g );
			$name_md   = $is_member ? "<#{$g['id']}>" : "`{$g['name']}`";
			$lines     = [ "*{$name_md}*" ];
			if ( $purpose ) {
				$lines[] = $purpose;
			}
			$lines[] = "{$count} members";

			$block = [
				'type' => 'section',
				'text' => [ 'type' => 'mrkdwn', 'text' => implode( "\n", $lines ) ],
			];
			if ( ! $is_member ) {
				$block['accessory'] = [
					'type'      => 'button',
					'text'      => [ 'type' => 'plain_text', 'text' => 'Join' ],
					'action_id' => 'join_subgroup',
					'value'     => $g['id'],
				];
			}
			$blocks[] = $block;
		}
	}

	$blocks[] = [ 'type' => 'divider' ];
	$footer_actions = [
		[
			'type'      => 'button',
			'text'      => [ 'type' => 'plain_text', 'text' => 'Create new subgroup' ],
			'style'     => 'primary',
			'action_id' => 'open_create_modal',
		],
	];
	$footer_actions[] = [
		'type'      => 'button',
		'text'      => [ 'type' => 'plain_text', 'text' => 'Manage' ],
		'action_id' => 'open_manage_modal',
	];
	$blocks[] = [
		'type'     => 'actions',
		'elements' => $footer_actions,
	];

	return [
		'type'             => 'modal',
		'callback_id'      => 'subgroup_home',
		'title'            => [ 'type' => 'plain_text', 'text' => 'Subgroups' ],
		'close'            => [ 'type' => 'plain_text', 'text' => 'Close' ],
		'private_metadata' => json_encode( [
			'parent'      => $parent['id'],
			'parent_name' => $parent['name'],
			'user'        => $user_id,
		] ),
		'blocks'           => $blocks,
	];
}

function build_manage_view( $parent, $user_id, $root_view_id ) {
	$all = get_subgroups( $parent['name'], true );
	$active   = [];
	$archived = [];
	foreach ( $all as $g ) {
		if ( ! empty( $g['is_archived'] ) ) {
			$archived[] = $g;
		} else {
			$active[] = $g;
		}
	}

	$blocks = [
		[
			'type' => 'section',
			'text' => [
				'type' => 'mrkdwn',
				'text' => "*Manage subgroups of <#{$parent['id']}>*",
			],
		],
		[ 'type' => 'divider' ],
	];

	if ( empty( $active ) ) {
		$blocks[] = [
			'type' => 'section',
			'text' => [ 'type' => 'mrkdwn', 'text' => '_No active subgroups._' ],
		];
	} else {
		$first = true;
		foreach ( $active as $g ) {
			if ( ! $first ) {
				$blocks[] = [ 'type' => 'divider' ];
			}
			$first = false;
			$members   = get_members( $g['id'] );
			$count     = count( $members );
			$is_member = in_array( $user_id, $members, true );
			$purpose   = format_purpose( $g );
			$name_md   = $is_member ? "<#{$g['id']}>" : "`{$g['name']}`";
			$lines     = [ "*{$name_md}*" ];
			if ( $purpose ) {
				$lines[] = $purpose;
			}
			$lines[]  = "{$count} members";
			$blocks[] = [
				'type' => 'section',
				'text' => [ 'type' => 'mrkdwn', 'text' => implode( "\n", $lines ) ],
			];
			$blocks[] = [
				'type'     => 'actions',
				'elements' => [
					[
						'type'      => 'button',
						'text'      => [ 'type' => 'plain_text', 'text' => 'Rename' ],
						'action_id' => 'rename_subgroup',
						'value'     => $g['id'],
					],
					[
						'type'      => 'button',
						'text'      => [ 'type' => 'plain_text', 'text' => 'Archive' ],
						'style'     => 'danger',
						'action_id' => 'archive_subgroup',
						'value'     => $g['id'],
						'confirm'   => [
							'title'   => [ 'type' => 'plain_text', 'text' => 'Archive channel?' ],
							'text'    => [ 'type' => 'mrkdwn', 'text' => "Archive <#{$g['id']}>?" ],
							'confirm' => [ 'type' => 'plain_text', 'text' => 'Archive' ],
							'deny'    => [ 'type' => 'plain_text', 'text' => 'Cancel' ],
							'style'   => 'danger',
						],
					],
				],
			];
		}
	}

	if ( ! empty( $archived ) ) {
		// `updated` is ms-since-epoch of the last change — for archived
		// channels that's effectively the archive time. Fall back to `created`
		// (seconds) scaled up, in case `updated` isn't present.
		usort( $archived, function ( $a, $b ) {
			$at = $a['updated'] ?? ( ( $a['created'] ?? 0 ) * 1000 );
			$bt = $b['updated'] ?? ( ( $b['created'] ?? 0 ) * 1000 );
			return $bt <=> $at;
		} );

		$blocks[] = [ 'type' => 'divider' ];
		$blocks[] = [
			'type' => 'section',
			'text' => [ 'type' => 'mrkdwn', 'text' => '*Archived*' ],
		];
		$first = true;
		foreach ( $archived as $g ) {
			if ( ! $first ) {
				$blocks[] = [ 'type' => 'divider' ];
			}
			$first = false;
			$purpose = format_purpose( $g );
			$lines   = [ "`{$g['name']}`" ];
			if ( $purpose ) {
				$lines[] = $purpose;
			}
			$blocks[] = [
				'type'      => 'section',
				'text'      => [ 'type' => 'mrkdwn', 'text' => implode( "\n", $lines ) ],
				'accessory' => [
					'type'      => 'button',
					'text'      => [ 'type' => 'plain_text', 'text' => 'Reopen' ],
					'action_id' => 'reopen_subgroup',
					'value'     => $g['id'],
				],
			];
		}
	}

	return [
		'type'             => 'modal',
		'callback_id'      => 'subgroup_manage',
		'title'            => [ 'type' => 'plain_text', 'text' => 'Manage subgroups' ],
		'close'            => [ 'type' => 'plain_text', 'text' => 'Close' ],
		'private_metadata' => json_encode( [
			'parent'      => $parent['id'],
			'parent_name' => $parent['name'],
			'user'        => $user_id,
			'root_view'   => $root_view_id,
		] ),
		'blocks'           => $blocks,
	];
}

function build_rename_view( $parent, $channel_id, $current_name, $user_id, $manage_view_id, $root_view_id ) {
	$prefix = $parent['name'] . '-';

	return [
		'type'             => 'modal',
		'callback_id'      => 'subgroup_rename',
		'title'            => [ 'type' => 'plain_text', 'text' => 'Rename subgroup' ],
		'submit'           => [ 'type' => 'plain_text', 'text' => 'Rename' ],
		'close'            => [ 'type' => 'plain_text', 'text' => 'Cancel' ],
		'private_metadata' => json_encode( [
			'parent'       => $parent['id'],
			'parent_name'  => $parent['name'],
			'user'         => $user_id,
			'root_view'    => $root_view_id,
			'manage_view'  => $manage_view_id,
			'channel_id'   => $channel_id,
			'current_name' => $current_name,
		] ),
		'blocks' => [
			[
				'type' => 'section',
				'text' => [
					'type' => 'mrkdwn',
					'text' => sprintf( 'Renaming <#%s>.', $channel_id ),
				],
			],
			[
				'type'     => 'input',
				'block_id' => 'name',
				'label'    => [ 'type' => 'plain_text', 'text' => 'New name' ],
				'element'  => [
					'type'          => 'plain_text_input',
					'action_id'     => 'value',
					'initial_value' => $current_name,
					'max_length'    => 80,
				],
				'hint'     => [ 'type' => 'plain_text', 'text' => "Must start with \"{$prefix}\"." ],
			],
		],
	];
}

function build_create_view( $parent, $user_id, $root_view_id ) {
	$prefix = $parent['name'] . '-';

	return [
		'type'             => 'modal',
		'callback_id'      => 'subgroup_create',
		'title'            => [ 'type' => 'plain_text', 'text' => 'Create subgroup' ],
		'submit'           => [ 'type' => 'plain_text', 'text' => 'Create' ],
		'close'            => [ 'type' => 'plain_text', 'text' => 'Cancel' ],
		'private_metadata' => json_encode( [
			'parent'      => $parent['id'],
			'parent_name' => $parent['name'],
			'user'        => $user_id,
			'root_view'   => $root_view_id,
		] ),
		'blocks' => [
			[
				'type'     => 'input',
				'block_id' => 'name',
				'label'    => [ 'type' => 'plain_text', 'text' => 'Channel name' ],
				'element'  => [
					'type'          => 'plain_text_input',
					'action_id'     => 'value',
					'initial_value' => $prefix,
					'max_length'    => 80,
				],
				'hint'     => [ 'type' => 'plain_text', 'text' => "Must start with \"{$prefix}\"." ],
			],
			[
				'type'     => 'input',
				'block_id' => 'purpose',
				'optional' => true,
				'label'    => [ 'type' => 'plain_text', 'text' => 'Purpose' ],
				'element'  => [
					'type'       => 'plain_text_input',
					'action_id'  => 'value',
					'multiline'  => true,
					'max_length' => 250,
				],
				'hint'     => [ 'type' => 'plain_text', 'text' => 'Shown in the channel details panel.' ],
			],
			[
				'type'     => 'input',
				'block_id' => 'topic',
				'optional' => true,
				'label'    => [ 'type' => 'plain_text', 'text' => 'Topic' ],
				'element'  => [
					'type'       => 'plain_text_input',
					'action_id'  => 'value',
					'max_length' => 250,
				],
				'hint'     => [ 'type' => 'plain_text', 'text' => 'Short phrase shown in the channel header.' ],
			],
			[
				'type'     => 'input',
				'block_id' => 'strategy',
				'label'    => [ 'type' => 'plain_text', 'text' => 'Who to invite' ],
				'element'  => [
					'type'           => 'radio_buttons',
					'action_id'      => 'value',
					'initial_option' => [
						'text'  => [ 'type' => 'plain_text', 'text' => "All members of #{$parent['name']}" ],
						'value' => 'all',
					],
					'options' => [
						[
							'text'  => [ 'type' => 'plain_text', 'text' => "All members of #{$parent['name']}" ],
							'value' => 'all',
						],
						[
							'text'  => [ 'type' => 'plain_text', 'text' => 'Specific people (plus me)' ],
							'value' => 'specific',
						],
						[
							'text'  => [ 'type' => 'plain_text', 'text' => 'Just me' ],
							'value' => 'none',
						],
					],
				],
			],
			[
				'type'     => 'input',
				'block_id' => 'users',
				'optional' => true,
				'label'    => [ 'type' => 'plain_text', 'text' => 'Specific people' ],
				'element'  => [
					'type'        => 'multi_users_select',
					'action_id'   => 'value',
					'placeholder' => [ 'type' => 'plain_text', 'text' => 'Select users' ],
				],
				'hint'     => [ 'type' => 'plain_text', 'text' => 'Only used when "Specific people" is chosen above.' ],
			],
		],
	];
}

function build_conflict_view( $archived, $ctx ) {
	$prefix    = $ctx['parent_name'] . '-';
	$suffix    = '-archived-' . date( 'Ymd' );
	$suggested = $ctx['original_name'] . $suffix;
	if ( strlen( $suggested ) > 80 ) {
		$suggested = substr( $ctx['original_name'], 0, 80 - strlen( $suffix ) ) . $suffix;
	}

	return [
		'type'             => 'modal',
		'callback_id'      => 'subgroup_conflict',
		'title'            => [ 'type' => 'plain_text', 'text' => 'Name in use' ],
		'submit'           => [ 'type' => 'plain_text', 'text' => 'Continue' ],
		'close'            => [ 'type' => 'plain_text', 'text' => 'Cancel' ],
		'private_metadata' => json_encode( array_merge( $ctx, [
			'archived_channel_id'   => $archived['id'],
			'archived_channel_name' => $archived['name'],
		] ) ),
		'blocks' => [
			[
				'type' => 'section',
				'text' => [
					'type' => 'mrkdwn',
					'text' => sprintf(
						'A channel named `%s` already exists but is *archived*. What would you like to do?',
						$archived['name']
					),
				],
			],
			[
				'type'     => 'input',
				'block_id' => 'action',
				'label'    => [ 'type' => 'plain_text', 'text' => 'Action' ],
				'element'  => [
					'type'           => 'radio_buttons',
					'action_id'      => 'value',
					'initial_option' => [
						'text'  => [ 'type' => 'plain_text', 'text' => 'Unarchive it and reuse' ],
						'value' => 'unarchive',
					],
					'options'        => [
						[
							'text'  => [ 'type' => 'plain_text', 'text' => 'Unarchive it and reuse' ],
							'value' => 'unarchive',
						],
						[
							'text'  => [ 'type' => 'plain_text', 'text' => 'Rename the archived channel and create a new one' ],
							'value' => 'rename',
						],
					],
				],
			],
			[
				'type'     => 'input',
				'block_id' => 'new_name_for_archived',
				'optional' => true,
				'label'    => [ 'type' => 'plain_text', 'text' => 'New name for archived channel' ],
				'element'  => [
					'type'          => 'plain_text_input',
					'action_id'     => 'value',
					'initial_value' => $suggested,
					'max_length'    => 80,
				],
				'hint'     => [ 'type' => 'plain_text', 'text' => "Only used with 'Rename'. Must start with \"{$prefix}\"." ],
			],
		],
	];
}

function update_view_message( $view_id, $message ) {
	api_call( 'views.update', [
		'view_id' => $view_id,
		'view'    => json_encode( [
			'type'   => 'modal',
			'title'  => [ 'type' => 'plain_text', 'text' => 'Subgroups' ],
			'close'  => [ 'type' => 'plain_text', 'text' => 'Close' ],
			'blocks' => [
				[ 'type' => 'section', 'text' => [ 'type' => 'mrkdwn', 'text' => $message ] ],
			],
		] ),
	] );
}
