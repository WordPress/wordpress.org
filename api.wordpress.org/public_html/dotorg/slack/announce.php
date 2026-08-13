<?php
namespace Dotorg\Slack\Announce;

require dirname( __DIR__, 2 ) . '/includes/hyperdb/bb-10-hyper-db.php';
require dirname( __DIR__, 2 ) . '/includes/slack-config.php';
require dirname( __DIR__, 2 ) . '/includes/slack/announce/lib.php';

/*
 * This is a standalone Slack slash-command handler: WordPress is not loaded, so request
 * data is never slashed. Slack authenticates itself with one of the shared
 * `WEBHOOK_TOKEN_*` secrets below; nonces don't exist in server-to-server webhooks.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 */

function get_avatar( $username, $slack_id, $team_id ) {
	global $wpdb;

	$wp_user_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM slack_users WHERE slack_id = %s",
		$slack_id
	) );

	$email = $wpdb->get_var( $wpdb->prepare(
		"SELECT user_email FROM $wpdb->users WHERE ID = %d",
		$wp_user_id
	) );

	$hash = hash( 'sha256', strtolower( trim( $email ) ) );
	return sprintf( 'https://secure.gravatar.com/avatar/%s?s=96d=mm&r=G&%s', $hash, time() );
}

if ( ! is_string( $_POST['token'] ?? null ) ) {
	return;
}

$i = 0;
// WEBHOOK_TOKEN_1, WEBHOOK_TOKEN_2, etc.
while ( defined( __NAMESPACE__ . '\\WEBHOOK_TOKEN_' . ++$i ) ) {
	if ( hash_equals( constant( __NAMESPACE__ . '\\WEBHOOK_TOKEN_' . $i ), $_POST['token'] ) ) {
		run( $_POST );
	}
}
