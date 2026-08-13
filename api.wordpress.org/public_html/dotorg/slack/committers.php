<?php
/**
 * Slack outgoing-webhook handler for the committers channel.
 *
 * This is a standalone Slack outgoing-webhook handler: WordPress is not loaded, so request data
 * is never slashed. Slack authenticates itself with the shared `WEBHOOK_TOKEN` below; nonces
 * don't exist in server-to-server webhooks.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 *
 * @package WordPressdotorg\API\Slack
 */

// Allow committers to publicly mention other committers via @committers.

namespace Dotorg\Slack\Committers;

require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

if ( ! is_string( $_POST['token'] ?? null ) || ! hash_equals( WEBHOOK_TOKEN, $_POST['token'] ) ) {
	return;
}

// The Slack user name of whoever triggered the webhook, echoed back in the JSON response below.
$user_name = filter_var( $_POST['user_name'] ?? '', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );

echo json_encode( array(
	'username'   => 'wordpressdotorg',
	'link_names' => 1,
	'text'       => sprintf( '@%s: Use the `/committers` command.', $user_name ),
) );

exit;
