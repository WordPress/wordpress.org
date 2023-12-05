<?php
namespace WordPressdotorg\API\HelpScout;

// Webhook fired when an event is fired for any inbox.
// Events: Conversation Created (convo.created), convo.assigned, convo.customer.reply.created, convo.merged, convo.agent.reply.created, convo.deleted, convo.status, convo.moved

include __DIR__ . '/common.php';

// $request is the validated HelpScout request.
$request = get_request();
$event   = $_SERVER['HTTP_X_HELPSCOUT_EVENT'] ?? '';

// Handle the openverse webhook.
openverse_webhook( $event, $request );

// Warm the caches.
get_email_thread( $request->id, true );

// Record stats.
contributor_stats( $event, $request );

// Record the email in the database.
log_email( $event, $request );

/**
 * Ping the Openverse webhook.
 */
function openverse_webhook( $event, $request ) {
	if (
		'production' === wp_get_environment_type() &&
		defined( 'HELPSCOUT_OPENVERSE_WEBHOOK' ) && HELPSCOUT_OPENVERSE_WEBHOOK &&
		defined( 'HELPSCOUT_OPENVERSE_MAILBOXID' ) && HELPSCOUT_OPENVERSE_MAILBOXID &&
		'convo.created' === $event &&
		isset( $request->mailboxId ) && HELPSCOUT_OPENVERSE_MAILBOXID === $request->mailboxId
	) {
		$subject = $request->subject;
		$url     = $request->_links->web->href;

		wp_safe_remote_post(
			HELPSCOUT_OPENVERSE_WEBHOOK,
			[
				'body' => wp_json_encode( compact( 'subject', 'url' ) )
			]
		);
	}
}

/**
 * Record some Contributor stats.
 *
 * These are recorded through Webhook events, instead of through the Helpscout API, as the Helpscout stats API is focused around
 * user KPI's, of which assigning emails, marking as spam, closing, etc. do not correspond to the type of data we want to track.
 *
 * @param string $event   The Helpscout event.
 * @param object $request The Helpscout payload.
 */
function contributor_stats( $event, $request ) {
	bump_stats_extra( 'helpscout', $event );

	$hs_user_id        = false;
	$hs_author_fields  = [
		$request->createdBy ?? false,
		$request->closedByUser ?? false,
		$request->_embedded->threads[0]->createdBy ?? false,
	];

	foreach ( $hs_author_fields as $item ) {
		if ( ! $item || ! isset( $item->id, $item->type ) || 'user' !== $item->type ) {
			continue;
		}

		// User ID 0 is "unknown"/not-set, user ID 1 is HelpScout / Automations.
		if ( $item->id > 1 ) {
			$hs_user_id = $item->id;
			break;
		}
	}

	if ( ! $hs_user_id ) {
		return;
	}

	// Determine the WordPress.org user for this HelpScout user.
	$stat_user  = 'HS-' . get_client()->name . '-' . $hs_user_id;
	$wporg_user = get_wporg_user_for_helpscout_user( $hs_user_id );
	if ( $wporg_user ) {
		$stat_user = $wporg_user->user_nicename;
	}

	// Per-mailbox stats.
	$mailbox = get_mailbox_name( $request );

	// Total actions performed by user.
	bump_stats_extra( 'hs-total', $stat_user );

	if ( $mailbox ) {
		bump_stats_extra( 'hs-' . $mailbox . '-total', $stat_user );
	}

	// Specific actions performed by user, replies and outgoing emails are counted as replies.
	switch ( $event ) {
		case 'convo.agent.reply.created':
			bump_stats_extra( 'hs-replies', $stat_user );

			if ( $mailbox ) {
				bump_stats_extra( 'hs-' . $mailbox . '-replies', $stat_user );
			}
			break;
		case 'convo.created':
			if ( 'user' === $request->createdBy->type ?? '' ) {
				bump_stats_extra( 'hs-replies', $stat_user );

				if ( $mailbox ) {
					bump_stats_extra( 'hs-' . $mailbox . '-replies', $stat_user );
				}
			}
			break;
	}
}
