<?php
/**
 * FreeScout webhook: records contributor stats for helpdesk activity.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

require __DIR__ . '/common.php';

/**
 * Events that count as a reply sent by the agent.
 *
 * @var string[]
 */
const REPLY_EVENTS = array( 'conversation.user_replied', 'conversation.created_by_user' );

/**
 * Records contributor stats for an event.
 *
 * @param object $request Request payload.
 * @return void
 */
function contributor_stats( object $request ): void {
	$event = (string) ( $request->event ?? '' );

	bump_stats_extra( 'freescout', $event );

	if ( empty( $request->agent->id ) ) {
		return;
	}

	$wporg_user = get_wporg_user_for_agent_emails( array( (string) ( $request->agent->email ?? '' ) ) );
	$stat_user  = $wporg_user ? $wporg_user->user_nicename : 'FS-' . (int) $request->agent->id;
	$mailbox    = get_mailbox_slug( $request );
	$fields     = array( 'total' );

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

contributor_stats( get_request() );

header( 'Content-Type: application/json; charset=utf-8' );
echo '{}';
