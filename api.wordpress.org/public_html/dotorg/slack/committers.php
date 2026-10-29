<?php
/**
 * Slack outgoing-webhook handler that redirects @committers mentions to the slash command.
 *
 * Standalone handler: WordPress is not loaded, so request data is never slashed. Slack
 * authenticates itself with the shared `WEBHOOK_TOKEN` secret below; nonces do not exist
 * in server-to-server webhooks.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 *
 * @package WordPressdotorg\API\Slack
 */

namespace Dotorg\Slack\Committers;

require dirname( __DIR__, 2 ) . '/includes/slack-config.php';

// Slack sends the token as POST data; anything else is not a webhook request.
if ( ! isset( $_POST['token'] ) || ! is_string( $_POST['token'] ) || '' === $_POST['token'] ) {
	return;
}

if ( ! hash_equals( WEBHOOK_TOKEN, $_POST['token'] ) ) {
	return;
}

// The Slack user name of whoever triggered the webhook, echoed back in the JSON response below.
$user_name = (string) filter_var( $_POST['user_name'] ?? '', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );

// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WP loaded.
echo json_encode(
	array(
		'username'   => 'wordpressdotorg',
		'link_names' => 1,
		'text'       => sprintf( '@%s: Use the `/committers` command.', $user_name ),
	)
);

exit;
