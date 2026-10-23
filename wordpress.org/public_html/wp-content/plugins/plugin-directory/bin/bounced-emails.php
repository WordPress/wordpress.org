<?php
namespace WordPressdotorg\Plugin_Directory\Bin\BouncedEmails;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Author_Notice;
use WordPressdotorg\Plugin_Directory\Admin\Status_Transitions;

use WordPressdotorg\Plugin_Directory\Clients\FreeScout;
use WordPressdotorg\Plugin_Directory\Tools\Helpdesk;

use function WordPressdotorg\API\FreeScout\{ get_user_email_for_email };

// phpcs:disable WordPress.Security.EscapeOutput

/**
 * This script will process all the bounced emails in FreeScout's Plugins mailbox and take the appropriate action.
 *
 * It will:
 *  - Fetch plugins tagged 'auto-bounce'
 *  - Determine who the bounce is for
 *  - Then either:
 *    a.  Revoke commit for the bouncing user, IF there are other committers AND the owner is not bouncing.
 *    b.  Close the plugin if all committers are bouncing OR the owner is bouncing.
 *  - In both cases, an Audit log entry is added referencing this script.
 *  - In the case of revoke, the audit log entry contains the bounce message.
 *  - In the case of a plugin closure, the Author notice is set to the next steps for the user AND a copy of the bounce is included.
 *  - If an action is taken, the conversation is closed, as wordpressdotorg.
 */

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

// Comment out once you're sure you know what you're doing :) Remove the sleep( 10 ) below if you want.
die( "Please check the source, and validate this will work as you anticipate." );

$opts = getopt( '', array( 'url:', 'abspath:', 'doit' ) );

if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}

// Dry-run or live mode?
define( __NAMESPACE__ . '\OPERATION_MODE', isset( $opts['doit'] ) ? 'live' : 'dry-run' );

// Bootstrap WordPress
$_SERVER['HTTP_HOST']   = parse_url( $opts['url'], PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = parse_url( $opts['url'], PHP_URL_PATH );

require rtrim( $opts['abspath'], '/' ) . '/wp-load.php';

if ( ! class_exists( '\WordPressdotorg\Plugin_Directory\Plugin_Directory' ) ) {
	fwrite( STDERR, "Error! This site doesn't have the Plugin Directory plugin enabled.\n" );
	if ( defined( 'WPORG_PLUGIN_DIRECTORY_BLOGID' ) ) {
		fwrite( STDERR, "Run the following command instead:\n" );
		fwrite( STDERR, "\tphp " . implode( ' ', $argv ) . ' --url ' . get_site_url( WPORG_PLUGIN_DIRECTORY_BLOGID, '/' ) . "\n" );
	}
	die();
}

if ( 'live' == OPERATION_MODE ) {
	echo "Running in live mode. Changes will be made.\nProceeding in 10s...\n";
	sleep( 10 );
} else {
	echo "Running in dry-run mode. No changes will be made. Pass --doit parameter to make changes.\n";
}


if ( ! Helpdesk::is_freescout() || ! FreeScout::api_key() ) {
	fwrite( STDERR, "Error! Define FREESCOUT_PLUGINS_MAILBOX_ID and FREESCOUT_API_KEY first.\n" );
	die();
}

require_once API_WPORGPATH . '/dotorg/freescout/common.php';

// Set a user to run as.
wp_set_current_user( get_user_by( 'slug', 'wordpressdotorg' )->ID );

/**
 * Fetch the bounce conversations, with their threads, from every page.
 *
 * @return array[]
 */
function get_bounces() {
	$bounces = [];
	$page    = 1;

	do {
		$response = FreeScout::api(
			'conversations',
			[
				'mailboxId' => Helpdesk::freescout_mailbox_id(),
				'embed'     => 'threads',
				'status'    => 'pending',
				'tag'       => 'auto-bounce',
				'page'      => $page,
			],
			'GET',
			$response_code
		);

		if ( 200 !== $response_code ) {
			fwrite( STDERR, "Error! Couldn't list the bounces: $response_code\n" );
			die();
		}

		$bounces = array_merge( $bounces, $response['_embedded']['conversations'] ?? [] );
	} while ( $page++ < (int) ( $response['page']['totalPages'] ?? 0 ) );

	return $bounces;
}

/**
 * Shape a bounce like FreeScout's sidebar payload, which the bounce helpers read.
 *
 * Attachments that may hold the bounced email are downloaded, as the API only links them.
 *
 * @param array $bounce A conversation, with its threads.
 * @return object
 */
function get_bounce_request( $bounce ) {
	$threads = [];
	foreach ( $bounce['_embedded']['threads'] ?? [] as $thread ) {
		$attachments = [];
		foreach ( $thread['_embedded']['attachments'] ?? [] as $attachment ) {
			$mime_type = (string) ( $attachment['mimeType'] ?? '' );
			$size      = (int) ( $attachment['size'] ?? 0 );
			$content   = null;

			if ( $size <= 100 * KB_IN_BYTES && preg_match( '/message|text|rfc/', $mime_type ) ) {
				$response = wp_remote_get( (string) ( $attachment['fileUrl'] ?? '' ), [ 'timeout' => FreeScout::TIMEOUT ] );
				$content  = 200 === wp_remote_retrieve_response_code( $response ) ? wp_remote_retrieve_body( $response ) : null;
			}

			$attachments[] = (object) [
				'mime_type' => $mime_type,
				'size'      => $size,
				'content'   => $content,
			];
		}

		$threads[] = (object) [
			'type'        => (string) ( $thread['type'] ?? '' ),
			'body'        => (string) ( $thread['body'] ?? '' ),
			'attachments' => $attachments,
		];
	}

	return (object) [
		'conversation' => (object) [ 'subject' => (string) ( $bounce['subject'] ?? '' ) ],
		'sender'       => (object) [
			'email'      => (string) ( $bounce['customer']['email'] ?? '' ),
			'first_name' => (string) ( $bounce['customer']['firstName'] ?? '' ),
			'last_name'  => (string) ( $bounce['customer']['lastName'] ?? '' ),
		],
		'threads'      => $threads,
		// A bounce is about whoever the email bounced for, not its sender.
		'related'      => true,
	];
}

/**
 * Close a bounce's conversation.
 *
 * @param int $conversation_id Conversation ID.
 */
function close_conversation( $conversation_id ) {
	FreeScout::api(
		'conversations/' . $conversation_id,
		[
			'status' => 'closed',
			'byUser' => FreeScout::USER_ID,
		],
		'PUT',
		$response_code
	);

	if ( $response_code < 200 || $response_code >= 300 ) {
		echo "\tCouldn't close " . Helpdesk::conversation_url( $conversation_id, 0, true ) . ": $response_code\n";
	}
}

/**
 * Determine the bounce message from the MTA.
 *
 * @param object $email
 * @return string|false
 */
function extract_bounce_message( $email ) {
	$body    = '';
	$message = '';

	foreach ( $email['_embedded']['threads'] ?? [] as $reply ) {
		if ( 'customer' != $reply['type'] ) {
			continue;
		}

		$body = $reply['body'];
		break;
	}

	$body = preg_replace( '#<br\s*/?>#i', "\n", $body );
	$body = str_replace( "\r", '', $body );

	// Trim the body to header-like lines.
	$body            = explode( "\n", $body );
	$last_was_header = false;
	$lines_matched   = 0;
	foreach ( $body as $i => $line ) {
		if ( ! preg_match( '/^[\w-]+:/', $line ) && ! $last_was_header ) {
			$last_was_header = false;
			continue;
		}

		$lines_matched++;
		$last_was_header = true;
		$message        .= $line . "\n";
	}

	/*
	 * If lots of lines matched, it probably contained the full message.. Outlook does this.
	 * See if we can match a smaller specific message.
	 */
	if (
		$lines_matched > 10 &&
		preg_match( '/(^|\n)Reporting-MTA:.+(\n[\w-]+:.+(\n[ \t]+.+)*|\n){2,10}/i', $message, $m )
	) {
		$message = $m[0];
	}

	// Unfurl the message if needed. [\d-.] strips some MTA's codes from the indent.
	$message = preg_replace( '/\n[ \t]+/', ' ', $message );

	// Remove the headers we don't care about.
	if ( str_contains( $message, 'Reporting-MTA' ) ) {
		$lines = explode( "\n", $message );
		$lines = array_filter( $lines, function( $line ) {
			// Removes things like Reporting-MTA, X-PostFix-ID, etc.
			return ! preg_match( '/^(Reporting-MTA|Received-From-MTA|X-)/i', $line );
		} );

		$lines = array_filter( $lines );

		$message = implode( "\n", $lines );
	}

	return trim( $message ) ?: false;
}

$stats = [
	'processed'            => 0,
	'error'                => 0,
	'closed'               => 0,
	'revoked-commit'       => 0,
	'conversations-closed' => 0,
];

$actions_to_take = [
/*	plugin-id => [
		'id' => [ user-123 ],
		'users' => [
			'username' => 'bounce message',
			'user-2'   => 'bounce message',
		],
		'action' => 'close|revoke'
	],
*/
];
$just_close_it = []; // The conversations to just close.

foreach ( get_bounces() as $bounce ) {
	$conversation_url = Helpdesk::conversation_url( (int) $bounce['id'], (int) $bounce['number'], true );

	$stats['processed']++;

	echo "Processing {$conversation_url}\n";

	$email = get_user_email_for_email( get_bounce_request( $bounce ) );
	$user  = get_user_by( 'email', $email );

	$slugs = Tools::get_users_write_access_plugins( $user ) ?: [];
	$plugins = array_map(
		function( $slug ) {
			return Plugin_Directory::get_plugin_post( $slug );
		},
		$slugs
	);

	$closed_plugins = array_filter(
		$plugins,
		function( $plugin ) {
			return 'closed' === get_post_status( $plugin ) || 'disabled' === get_post_status( $plugin );
		}
	);

	$plugins = array_filter(
		$plugins,
		function( $plugin ) {
			return 'publish' === get_post_status( $plugin );
		}
	);

	$single_committer_plugins = array_filter(
		$plugins,
		function( $plugin ) use( $user ) {
			return (
				$user->ID == $plugin->post_author ||
				1 === count( Tools::get_plugin_committers( $plugin ) )
			);
		}
	);
	$multiple_committer_plugins = array_filter(
		$plugins,
		function( $plugin ) use( $user ) {
			return (
				count( Tools::get_plugin_committers( $plugin ) ) > 1 &&
				$user->ID != $plugin->post_author
			);
		}
	);

	if ( ! $email || ! $user || ! $slugs || ( ! $plugins && ! $closed_plugins ) ) {
		echo "\tNo user or plugins found.\n";
		$stats['error']++;
		continue;
	}

	$bounce_message = extract_bounce_message( $bounce );

	echo "\tEmail: $email\n";
	echo "\tUser: {$user->user_login}\n";
	echo "\tSingular committer (or owns): " . implode( ', ', wp_list_pluck( $single_committer_plugins, 'post_name' ) ) . "\n";
	echo "\tJust a committer: " . implode( ', ', wp_list_pluck( $multiple_committer_plugins, 'post_name' ) ) . "\n";
	echo "\tClosed plugins: " . implode( ', ', wp_list_pluck( $closed_plugins, 'post_name' ) ) . "\n";

	echo "\tBounce Message:\n\t\t> " . ( str_replace( "\n", "\n\t\t> ", $bounce_message ) ?: 'Unable to determine bounce reason' ) . "\n";

	foreach ( $plugins as $plugin ) {
		$actions_to_take[ $plugin->ID ] ??= [ 'id' => [], 'users' => [], 'action' => '' ];

		$actions_to_take[ $plugin->ID ]['id'][]  = (int) $bounce['id'];
		$actions_to_take[ $plugin->ID ]['users'][ $user->ID ] = $bounce_message;
	}

	if ( ! $plugins && $closed_plugins ) {
		$just_close_it[] = (int) $bounce['id'];
	}

	echo "\n";
}

// Determine the action to take..
foreach ( $actions_to_take as $post_id => $data ) {
	$plugin          = get_post( $post_id );
	$committer_count = count( Tools::get_plugin_committers( $plugin ) );
	$bouncing_users  = count( $data['users'] );
	$owner_bouncing  = in_array( $plugin->post_author, array_keys( $data['users'] ) );

	$action = 'revoke'; // revoke, close. Default to revoke.
	if ( $bouncing_users >= $committer_count || $owner_bouncing ) {
		$action = 'close';
	}

	$actions_to_take[ $post_id ]['action'] = $action;
}

// Perform Commit Revoke.
foreach ( $actions_to_take as $post_id => $data ) {
	if ( 'revoke' !== $data['action'] ) {
		continue;
	}

	$plugin = get_post( $post_id );

	$conversation_urls = implode(
		' ',
		array_map(
			function ( $conversation_id ) {
				return Helpdesk::conversation_url( $conversation_id, 0, true );
			},
			$data['id']
		)
	);

	echo "Plugin: {$plugin->post_name}\n";
	echo "\t" . get_permalink( $plugin ) . "\n";

	foreach ( $data['users'] as $user_id => $bounce_message ) {
		$user = get_user_by( 'id', $user_id );

		$bounce_message = $bounce_message ? "<pre>{$bounce_message}</pre>\n" : '';

		echo "\tRemoved Commit for {$user->user_login}\n";

		if ( 'live' != OPERATION_MODE ) {
			continue;
		}

		Tools::audit_log(
			"Removing {$user->user_login} as a committer due to Email bounce. See {$conversation_urls}.\n{$bounce_message}<em>Automated by <code>bin/bounced-emails.php</code></em>",
			$plugin
		);
		Tools::revoke_plugin_committer( $plugin, $user );

		$stats['revoked-commit']++;
	}

	if ( 'live' == OPERATION_MODE ) {
		foreach ( $data['id'] as $conversation_id ) {
			close_conversation( $conversation_id );
		}
	}

	echo "\n";
}

// Perform plugin closures.
foreach ( $actions_to_take as $post_id => $data ) {
	if ( 'close' !== $data['action'] ) {
		continue;
	}

	$plugin = get_post( $post_id );

	$conversation_urls = implode(
		' ',
		array_map(
			function ( $conversation_id ) {
				return Helpdesk::conversation_url( $conversation_id, 0, true );
			},
			$data['id']
		)
	);

	echo "Plugin: {$plugin->post_name}\n";
	echo "\t" . get_permalink( $plugin ) . "\n";
	echo "\tClosing due to bounce of all committers, or owner.\n";

	$bounce_message = implode( "\n\n", array_filter( $data['users'] ) );
	$bounce_message = $bounce_message ? '<pre>' . esc_html( $bounce_message ) . "</pre>\n" : '';

	if ( 'live' != OPERATION_MODE ) {
		echo "\n";
		continue;
	}

	Tools::audit_log(
		"Closing due to Email bounce. See {$conversation_urls}.\n{$bounce_message}<em>Automated by <code>bin/bounced-emails.php</code></em>",
		$plugin
	);

	// Add author notice.
	Author_Notice::set(
		$plugin,
		'<p>' .
			'<strong>Your plugin has been closed due to a <a href="/plugins/developers/">guideline violation</a>:</strong> Your email address is currently bouncing.<br>' .
			'The good news is that in most cases, we can restore your plugin(s). To do that, we need you to do the following:' .
			'<ol>' .
				'<li><a href="https://wordpress.org/support/users/profile/edit/">Make sure the email on the user account is valid</a>.</li>' .
				'<li>If the email is a group mail or mailing list, make sure it can receive email from external domains or non-members.</li>' .
				"<li>If the email forwards, check all addresses to make sure they're valid and do not forward bounces, some email forwarders break DMARC signatures in which case you will need to change your forwarding configuration.</li>" .
				'<li>If the ownership of the plugin is in doubt, let us know what accounts are supposed to have access and be the official owners so we can transfer them appropriately.</li>' .
				'<li>You must update the plugin readme to confirm it is compatible with the current release of WordPress. This is to ensure people can actually find your plugin.</li>' .
				'<li>Perform a full security and guideline check of your own work. Look for sanitization, remote loading of content, and any other minor bugs.</li>' .
				'<li>Update all the code and upload it to SVN.</li>' .
				"<li>Contact <a href='mailto:plugins@wordpress.org'>plugins@wordpress.org</a> to begin the review and re-open process.</li>" .
			'</ol>' .
		'</p>' .
		$bounce_message,
		'error'
	);

	// Record why it's closed
	update_post_meta( $plugin->ID, '_close_reason', 'guideline-violation' );
	update_post_meta( $plugin->ID, 'plugin_closed_date', current_time( 'mysql' ) );

	// Set the translation project to inactive.
	Status_Transitions::set_translation_status( $plugin, 'inactive' );

	// Change status.
	wp_update_post( [
		'ID'          => $plugin->ID,
		'post_status' => 'closed',
	] );

	foreach ( $data['id'] as $conversation_id ) {
		close_conversation( $conversation_id );
	}

	$stats['closed']++;

	echo "\n";
}

foreach ( $just_close_it as $conversation_id ) {
	$stats['conversations-closed']++;

	echo 'Marking ' . Helpdesk::conversation_url( $conversation_id, 0, true ) . " as closed as all plugins are closed.\n";

	if ( 'live' == OPERATION_MODE ) {
		close_conversation( $conversation_id );
	}
}

echo "\nAll Done! Stats:\n";
var_dump( $stats );
