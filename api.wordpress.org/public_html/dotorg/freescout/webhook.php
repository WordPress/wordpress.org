<?php
/**
 * FreeScout webhook: records contributor stats for helpdesk activity, and keeps WordPress.org's copy of conversations.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

require __DIR__ . '/common.php';
require __DIR__ . '/copies.php';

/**
 * Events that count as a reply sent by the agent.
 *
 * @var string[]
 */
const REPLY_EVENTS = array( 'conversation.user_replied', 'conversation.created_by_user' );

/**
 * Events counted, but not credited to the agent: a restore undoes a credited deletion, and notes are only sent for the
 * copy.
 *
 * @var string[]
 */
const UNCREDITED_EVENTS = array( 'conversation.restored', 'conversation.note_added' );

/**
 * WordPress.org accounts automations write to FreeScout as, like upload confirmations; their events aren't credited.
 *
 * @var int[]
 */
const AUTOMATION_USER_IDS = array( 5911429 ); // wordpressdotorg.

/**
 * Records contributor stats for an event.
 *
 * @param object $request Request payload.
 * @return void
 */
function contributor_stats( object $request ): void {
	$event = (string) ( $request->event ?? '' );

	bump_stats_extra( 'freescout', $event );

	if ( empty( $request->agent->id ) || in_array( $event, UNCREDITED_EVENTS, true ) ) {
		return;
	}

	$username   = (string) ( $request->agent->wporg_username ?? '' );
	$wporg_user = $username ? get_user_by( 'login', $username ) : false;
	if ( $wporg_user && in_array( (int) $wporg_user->ID, AUTOMATION_USER_IDS, true ) ) {
		return;
	}

	$stat_user = $wporg_user ? $wporg_user->user_nicename : 'FS-' . (int) $request->agent->id;
	$mailbox   = get_mailbox_slug( $request );
	$fields    = array( 'total' );

	if ( in_array( $event, REPLY_EVENTS, true ) ) {
		$fields[] = 'replies';
	}

	foreach ( $fields as $field ) {
		bump_stats_extra( "email-{$field}", $stat_user );

		if ( $mailbox ) {
			bump_stats_extra( "email-{$mailbox}-{$field}", $stat_user );
		}
	}
}

$request = get_request( basename( __FILE__ ) );

// Before the stats, which an event sent again would count twice. FreeScout sends it again after a 503.
try {
	$needs_all_threads = log_email( $request );
} catch ( \Throwable $e ) {
	trigger_error( 'FreeScout copy of conversation ' . (int) ( $request->conversation->id ?? 0 ) . ' failed: ' . $e->getMessage(), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- Logged, not shown.
	status_header( 503 );
	exit;
}

header( 'Content-Type: application/json; charset=utf-8' );

// FreeScout sends the event again at once, with all of the conversation's threads; that one counts.
if ( $needs_all_threads ) {
	echo '{"threads":"all"}';
	exit;
}

contributor_stats( $request );

echo '{}';
