<?php
namespace WordPressdotorg\Plugin_Directory\Bin\BulkSecurityEmails;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;

use WordPressdotorg\MU_Plugins\Utilities\HelpScout; // NOTE: NOT the same as the HelpScout class in this plugin.

/**
 * This script will process a given CSV and close/email plugin authors.
 *
 * NOTE: The emails created in HelpScout will not be sent. We must also send it (Which this script does).
 *       As a result, replies to the email will NOT thread properly.
 *
 * The CSV should contain multiple fields.
 *  - At least one must be the 'slug', this is the plugin it will be looking for.
 *  - Additional fields MUST be specified, these will be included in the email to the author as the details of the vulnerability.
 *
 * Plugins with 10k or over installs, will be given a 30day grace period. Followupthen is used to remind us.
 * Plugins with less than 10k installs are closed immediately and informed.
 *
 * The --only-warn parameter can be passed to only email plugin authors, and not close the plugin.
 * This may be useful if the plugin has been updated recently.
 * NOTE: The template will reference 'standing in the community and the size of your user base' which may be incorrect.
 */

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

// Comment out once you're sure you know what you're doing :)
die( 'Please check the source, and validate this will work as you anticipate.' );

$opts = getopt( '', array( 'url:', 'abspath:', 'csv:', 'only-warn', 'doit' ) );

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

if ( empty( $opts['csv'] ) ) {
	die( "Need a --csv param." );
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

const ATTRIBUTE_TO_USER = 544543; // dd32 - This is who should show as having sent it. Fetch it from your HS profile edit url.
const ASSIGN_TO_USER    = 433937; // plugin security team - this is who should be assigned for responses
const WARN_THRESHOLD    = 10000;  // active installs to go from instant close => warn w/ 30days.

/**
 * Fetch the email template from HelpScout.
 */
function get_email_template( $name ) {
	static $ids;
	static $cache = [];

	$api = HelpScout::instance();
	$ids            ??= wp_list_pluck( $api->get( '/v2/mailboxes/' . $api->get_mailbox_id( 'plugins' ) . '/saved-replies' ), 'id', 'name' );
	$cache[ $name ] ??= $api->get( '/v2/mailboxes/' . $api->get_mailbox_id( 'plugins' ) . '/saved-replies/' . $ids[ $name ] );

	return $cache[ $name ]->text ?? false;
}

function fetch_all_from_csv() {
	global $opts;

	$csv = fopen( $opts['csv'], 'r' );
	$headers = fgetcsv( $csv );

	// Expect a slug field.
	$slug_pos = array_search( 'slug', array_map( 'strtolower', $headers ) );

	if ( false === $slug_pos ) {
		die( 'Unable to find a "Slug" field in the CSV.' );
	}

	$data = [];
	while ( ! feof( $csv ) ) {
		$raw = fgetcsv( $csv );

		$row = array_combine( $headers, $raw );

		$slug = $raw[ $slug_pos ];

		$data[ $slug ] ??= [];
		$data[ $slug ][] = $row;
	}

	return $data;
}

function format_vulns_for_email( $vulns, $plugin ) {
	$message = '';
	foreach ( $vulns as $vuln ) {
		$message .= "Plugin: " . get_permalink( $plugin ) . "\n";
		foreach ( $vuln as $field => $value ) {
			if ( 'slug' === strtolower( $field ) || 'plugin' === strtolower( $field ) ) {
				continue;
			}

			$message .= "$field: $value\n";
		}
		$message .= "\n\n"; // There might be multiple issues per email.
	}

	return esc_html( trim( $message ) );
}

/**
 * Send an email, both by wp_mail() and log it into HelpScout.
 *
 * HelpScout allows creation of emails through the API, but does not actually allow you to send an email.
 * Therefor, we send the email, and create it within HS.
 * Unfortunately, replies do not thread to the created email.. but at least we have a log..
 */
function send_email( $args ) {
	global $opts;

	$filter = function() { return 'text/html'; };
	add_filter( 'wp_mail_content_type', $filter );

	wp_mail(
		$args['to'] ?? '',
		$args['subject'] ?? '',
		str_replace( "<br />", "<br />\n", $args['body'] ?? '' ),
		array_filter( [
			'From: WordPress.org Plugin Directory <plugins@wordpress.org>',
			'Return-Path: <plugins@wordpress.org>',
			( $args['cc'] ? 'CC: ' . implode( ', ', $args['cc'] ) : '' ),
			( $args['bcc'] ? 'BCC: ' . implode( ', ', (array) $args['bcc'] ) : '' ),
		] )
	);
	remove_filter( 'wp_mail_content_type', $filter );

	$api = HelpScout::instance();
	$payload = [
		'type'      => 'email',
		'assignTo'  => ASSIGN_TO_USER,
		'mailboxId' => $api->get_mailbox_id( 'plugins' ),
		'status'    => 'closed',
		'imported'  => true,
		'customer'  => array_filter( [
			'email'     => $args['to'] ?? '',
			'firstName' => explode( ' ', $args['name'] ?? '', 2 )[0] ?? '',
			'lastName'  => explode( ' ', $args['name'] ?? '', 2 )[1] ?? '',
		] ),
		'subject'   => $args['subject'] ?? '',
		'threads'   => [
			[
				'type' => 'note',
				'text' => 'This was automated by <code>bin/email-bulk-security-vulnerabilities.php</code> processing a CSV file: <code>' . ( $opts['csv'] ?? '' ) . '</code>',
				'user' => ATTRIBUTE_TO_USER
			],
			[
				'type'  => 'reply',
				'cc'    => (array) ( $args['cc'] ?? [] ),
				'bcc'   => (array) ( $args['bcc'] ?? [] ),
				'text'  => $args['body'] ?? '',
				'customer' => [
					'email' => 'plugins@wordpress.org',
				],
				'user' => ATTRIBUTE_TO_USER
			]
		]
	];

	return $api->post( '/v2/conversations', $payload );
}

$stats = [
	'processed' => 0,
	'closed'    => 0,
	'warned'    => 0,
	'error'     => 0,
];

$all_csv_vulns = fetch_all_from_csv();

/*
$all_csv_vulns = [
	'plugin-slug' => [
		[
			'Slug'        => 'plugin-slug',
			'Link'        => 'https://example.org',
			'Version'     => 'Verified in 1.0.0',
			'Description' => 'Plugin is vulnerable to a XSS attack.',
		]
	]
];
*/

$only_warn = isset( $opts['only-warn'] );

foreach ( $all_csv_vulns as $plugin_slug => $vulns ) {
	$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
	if ( ! $plugin ) {
		$stats['error']++;
		echo "ERROR: Plugin not found: $plugin_slug\n\n";
		continue;
	}

	if ( 'publish' != $plugin->post_status ) {
		$stats['error']++;
		echo "ERROR: Plugin not published: $plugin_slug\n\n";
		continue;
	}

	$stats['processed']++;

	$author = get_user_by( 'id', $plugin->post_author );
	$to     = $author->user_email;
	$name   = $author->display_name ?: $author->user_login;

	$committers = Tools::get_plugin_committers( $plugin );
	$committers = array_map( function ( $user_login ) {
		return get_user_by( 'login', $user_login );
	}, $committers );

	$cc = wp_list_pluck( $committers, 'user_email' );
	$cc = array_values( array_diff( $cc, array( $author->user_email ) ) );
	$cc_list = implode( ', ', $cc );

	$active_installs = (int) $plugin->active_installs;
	$vuln_desc       = format_vulns_for_email( $vulns, $plugin );

	if ( $active_installs >= WARN_THRESHOLD || $only_warn ) {
		// WARN, with a 30day followup.
		$email_template = get_email_template( 'Warning: Security Issue (NOT CLOSED)' );
		$subject        = '[WordPress Plugin Directory] Security Notice: ' . $plugin->post_title;
		$bcc            = '30days@followupthen.com';

		$body = $email_template;
		$body = str_replace( 'PLUGIN_LINK', get_permalink( $plugin ), $body );
		$body = str_replace( 'LINK<br /><br />DETAILS', '<pre>' . $vuln_desc . '</pre>', $body );

		echo "Subject: $subject\n\n";
		echo "To: $to\n";
		echo "CC: $cc_list\n";
		echo str_replace( "<br />", "\n", $body );

		if ( 'live' != OPERATION_MODE ) {
			continue;
		}

		send_email( compact( 'subject', 'to', 'name', 'cc', 'bcc', 'body' ) );

		$stats['warned']++;

		// TODO: Add a 30day warning in plugin notes? 

	} else {
		// Close

		$email_template = get_email_template( 'Closed: Security Exploit' );
		$subject        = '[WordPress Plugin Directory] Closure Notice - Security: ' . $plugin->post_title;
		$bcc            = [];

		$body = $email_template;
		$body = str_replace( 'PLUGIN_LINK', get_permalink( $plugin ), $body );
		$body = str_replace( 'REPORT<br /><br />OPTIONAL_ADDITIONAL', '<pre>' . $vuln_desc . '</pre>', $body );

		echo "Subject: $subject\n\n";
		echo "To: $to\n";
		echo "CC: $cc_list\n";
		echo str_replace( "<br />", "\n", $body );

		if ( 'live' != OPERATION_MODE ) {
			continue;
		}

		send_email( compact( 'subject', 'to', 'cc', 'bcc', 'body' ) );

		// Record why it's closed
		update_post_meta( $plugin->ID, '_close_reason', 'security-issue' );
		update_post_meta( $plugin->ID, 'plugin_closed_date', current_time( 'mysql' ) );

		// Change status.
		wp_update_post( [
			'ID'          => $plugin->ID,
			'post_status' => 'closed',
		] );

		Tools::audit_log(
			'Plugin closed. Reason: security-issue. <em>Automated by <code>bin/email-bulk-security-vulnerabilities.php</code></em><br>' . $vuln_desc,
			$plugin
		);

		$stats['closed']++;
	}

	echo "\n\n\n";
}

echo "All Done! Stats: ";

var_dump( $stats );