<?php
namespace WordPressdotorg\Plugin_Directory\Bin\BouncedEmails;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Author_Notice;

use WordPressdotorg\MU_Plugins\Utilities\HelpScout; // NOTE: NOT the same as the HelpScout class in this plugin.

use function WordPressdotorg\API\HelpScout\{ get_user_email_for_email };

/**
 * This script will process all the bounced emails in HelpScout and take the appropriate action.
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
 *  - If an action is taken, the HS ticket is closed. (This action will show as 'Systems').
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


// Load the HelpScout API helper methods.
require_once API_WPORGPATH . '/dotorg/helpscout/common.php';

// Set a user to run as.
wp_set_current_user( get_user_by( 'slug', 'wordpressdotorg' )->ID );

/**
 * Fetch the bounce email threads.
 */
function get_bounces() {
	$api = HelpScout::instance();

	return $api->get_paged(
		'/v2/conversations',
		[
			'mailbox' => $api->get_mailbox_id( 'plugins' ),
			'embed'   => 'threads',
			'status'  => 'pending',
			'tag'     => 'auto-bounce',
		]
	);
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

	foreach ( $email->_embedded->threads as $reply ) {
		if ( 'customer' != $reply->type ) {
			continue;
		}

		$body = $reply->body;
		break;
	}

	$body = preg_replace( '#<br/?>#i', "\n", $body );
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
	'processed'      => 0,
	'error'          => 0,
	'closed'         => 0,
	'revoked-commit' => 0,
	'hs-closed'      => 0,
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
$just_close_it = []; // The HS tickets to just close.

foreach ( get_bounces()->_embedded->conversations as $bounce ) {
	$helpscout_url = "https://secure.helpscout.net/conversation/{$bounce->id}/{$bounce->number}";

	$stats['processed']++;

	echo "Processing {$helpscout_url}\n";

	$email = get_user_email_for_email( $bounce );
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

		$actions_to_take[ $plugin->ID ]['id'][]  = $bounce->id;
		$actions_to_take[ $plugin->ID ]['users'][ $user->ID ] = $bounce_message;
	}

	if ( ! $plugins && $closed_plugins ) {
		$just_close_it[] = $bounce->id;
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

	$helpscout_url = '';
	foreach ( $data['id'] as $hs_id ) {
		$helpscout_url .= " https://secure.helpscout.net/conversation/{$hs_id}";
	}
	$helpscout_url = trim( $helpscout_url );

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
			"Removing {$user->user_login} as a committer due to Email bounce. See {$helpscout_url}.\n{$bounce_message}<em>Automated by <code>bin/bounced-emails.php</code></em>",
			$plugin
		);
		Tools::revoke_plugin_committer( $plugin, $user );

		$stats['revoked-commit']++;
	}

	// Close those HS tickets.
	if ( 'live' == OPERATION_MODE ) {
		foreach ( $data['id'] as $hs_id ) {
			HelpScout::instance()->api(
				'/v2/conversations/' . $hs_id,
				[
					'op'    => 'replace',
					'path'  => '/status',
					'value' => 'closed',
				],
				'PATCH'
			);
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

	$helpscout_url = '';
	foreach ( $data['id'] as $hs_id ) {
		$helpscout_url .= " https://secure.helpscout.net/conversation/{$hs_id}";
	}
	$helpscout_url = trim( $helpscout_url );

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
		"Closing due to Email bounce. See {$helpscout_url}.\n{$bounce_message}<em>Automated by <code>bin/bounced-emails.php</code></em>",
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

	// Change status.
	wp_update_post( [
		'ID'          => $plugin->ID,
		'post_status' => 'closed',
	] );

	// Close those HS tickets.
	foreach ( $data['id'] as $hs_id ) {
		HelpScout::instance()->api(
			'/v2/conversations/' . $hs_id,
			[
				'op'    => 'replace',
				'path'  => '/status',
				'value' => 'closed',
			],
			'PATCH'
		);
	}

	$stats['closed']++;

	echo "\n";
}

// Close those HS tickets.
foreach ( $just_close_it as $hs_id ) {
	$stats['hs-closed']++;

	echo "Marking https://secure.helpscout.net/conversation/{$hs_id} as closed as all plugins are closed.\n";

	if ( 'live' == OPERATION_MODE ) {
		HelpScout::instance()->api(
			'/v2/conversations/' . $hs_id,
			[
				'op'    => 'replace',
				'path'  => '/status',
				'value' => 'closed',
			],
			'PATCH'
		);
	}
}

echo "\nAll Done! Stats:\n";
var_dump( $stats );
