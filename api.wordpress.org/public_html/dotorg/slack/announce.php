<?php
/**
 * Slack slash-command handler for @here and @channel announcements.
 *
 * Standalone handler: WordPress is not loaded, so request data is never slashed. Slack
 * authenticates itself with one of the shared `WEBHOOK_TOKEN_*` secrets below; nonces do
 * not exist in server-to-server webhooks.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 *
 * @package WordPressdotorg\API\Slack
 */

namespace Dotorg\Slack\Announce;

require dirname( __DIR__, 2 ) . '/includes/hyperdb/bb-10-hyper-db.php';
require dirname( __DIR__, 2 ) . '/includes/slack-config.php';
require dirname( __DIR__, 2 ) . '/includes/slack/announce/lib.php';

/**
 * Returns the Gravatar URL for the WordPress.org account linked to a Slack user.
 *
 * Defined in this namespace so that `run()` in lib.php picks it up as an optional hook;
 * it falls back to the Slack profile image when this function is not available.
 *
 * @param string $username The Slack user name. Unused, part of the hook signature.
 * @param string $slack_id The Slack user ID to look up.
 * @param string $team_id  The Slack team ID. Unused, part of the hook signature.
 * @return string The Gravatar URL for the linked account.
 */
function get_avatar( $username, $slack_id, $team_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Signature is fixed by the call in lib.php.
	global $wpdb;

	$wp_user_id = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT user_id FROM slack_users WHERE slack_id = %s',
			$slack_id
		)
	);

	$email = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT user_email FROM $wpdb->users WHERE ID = %d",
			$wp_user_id
		)
	);

	$hash = hash( 'sha256', strtolower( trim( $email ) ) );
	return sprintf( 'https://secure.gravatar.com/avatar/%s?s=96d=mm&r=G&%s', $hash, time() );
}

// Slack sends the token as POST data; anything else is not a webhook request.
if ( ! isset( $_POST['token'] ) || ! is_string( $_POST['token'] ) || '' === $_POST['token'] ) {
	return;
}

$i = 0;
// WEBHOOK_TOKEN_1, WEBHOOK_TOKEN_2, etc.
while ( defined( __NAMESPACE__ . '\\WEBHOOK_TOKEN_' . ( ++$i ) ) ) {
	if ( hash_equals( constant( __NAMESPACE__ . '\\WEBHOOK_TOKEN_' . $i ), $_POST['token'] ) ) {
		run( $_POST );
		break;
	}
}
