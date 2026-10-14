<?php
/**
 * FreeScout webhook: records contributor stats for helpdesk activity, and keeps WordPress.org's copy of conversations.
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

/**
 * Keeps WordPress.org's copy of a conversation, and the plugins and themes it mentions, as HelpScout's webhook did.
 *
 * A conversation replaces the copies of one merged into it, and of the HelpScout conversations they were imported from,
 * taking over their plugins and themes, which slug changes kept up to date.
 *
 * @param object $request Request payload.
 * @return bool Whether it needs all of the conversation's threads, which FreeScout sends it again with.
 *
 * @throws \RuntimeException If a write failed; the event sent again finishes it.
 */
function log_email( object $request ): bool {
	global $wpdb;

	$id    = (int) ( $request->conversation->id ?? 0 );
	$email = $request->email ?? null;
	if ( ! $id || ! is_object( $email ) ) {
		return false;
	}

	// Read from the primary database, not a lagging replica.
	if ( is_callable( array( $wpdb, 'send_reads_to_masters' ) ) ) {
		$wpdb->send_reads_to_masters();
	}

	// Spam and deleted conversations aren't kept; the current state wins over the event, as a deletion may be undone.
	$deleted = isset( $email->state ) ? 'deleted' === $email->state : 'conversation.deleted' === ( $request->event ?? '' );
	if ( $deleted || 'spam' === ( $email->status ?? '' ) ) {
		delete_emails( array_merge( array( $id ), ...array_values( get_replaced_ids( $request ) ) ) );

		return false;
	}

	return write_email( $request, $id );
}

/**
 * Writes WordPress.org's copy of a conversation; see log_email().
 *
 * @param object $request Request payload.
 * @param int    $id      Conversation ID.
 * @return bool Whether it needs all of the conversation's threads; see needs_all_threads().
 *
 * @throws \RuntimeException If a write failed.
 */
function write_email( object $request, int $id ): bool {
	global $wpdb;

	$email        = $request->email;
	$emails_table = "{$wpdb->base_prefix}helpscout";
	$replaced     = get_replaced_ids( $request );
	$helpscout_id = (int) ( $request->helpscout_id ?? 0 );

	$row           = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $emails_table, $id ) );
	$has_helpscout = ! $row && $helpscout_id && $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE id = %d', $emails_table, $helpscout_id ) );
	if ( needs_all_threads( $request, $row, (bool) $has_helpscout ) ) {
		return true;
	}

	/*
	 * The subject's mentions were added with the copy; the lookup costs queries, so only redo it when it changed. The
	 * column keeps 255 characters.
	 */
	$mailbox   = $request->email->mailbox ?? ( $request->mailbox ?? null );
	$subject   = (string) ( $email->subject ?? '' );
	$threads   = (array) ( $email->threads ?? array() );
	$seen      = $row && mb_substr( $subject, 0, 255 ) === (string) $row->subject && get_copy_mailbox_slug( $request ) === (string) $row->mailbox;
	$mentioned = $seen && ! $threads ? array() : get_existing_slugs(
		get_plugin_or_theme_from_email(
			(object) array(
				'conversation' => (object) array( 'subject' => $seen ? '' : $subject ),
				'mailbox'      => $mailbox,
				'threads'      => $threads,
			)
		)
	);

	$user_id = (int) ( $row->user_id ?? 0 );
	if ( ! $user_id ) {
		$user    = get_sender_user( (object) array( 'sender' => $email->sender ?? null ) );
		$user_id = $user ? (int) $user->ID : 0;
	}

	$data    = get_email_row( $request, $row, $user_id );
	$columns = array_keys( $data );

	// In an order that the event, sent again after a failed write, finishes.
	$replaced_ids = array_merge( ...array_values( $replaced ) );
	take_over_meta( $id, $replaced_ids );
	add_copy_meta( $id, $replaced['helpscout'], $mentioned );

	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The placeholders are added for each column; one statement, so two events for a new conversation at once don't both insert it.
	check_write(
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (' . implode( ', ', array_fill( 0, count( $columns ), '%i' ) ) . ') VALUES (' . implode( ', ', array_fill( 0, count( $data ), '%s' ) ) . ') ON DUPLICATE KEY UPDATE ' . implode( ', ', array_fill( 0, count( $columns ) - 1, '%i = VALUES( %i )' ) ),
				array_merge(
					array( $emails_table ),
					$columns,
					array_values( $data ),
					// Every column but the ID, twice: name, and name again for VALUES().
					...array_map(
						static function ( string $column ): array {
							return array( $column, $column );
						},
						array_values( array_diff( $columns, array( 'id' ) ) )
					)
				)
			)
		)
	);
	// phpcs:enable

	delete_emails( $replaced_ids );

	return false;
}

/**
 * The slugs of plugins and themes that exist, as the copy only keeps those.
 *
 * @param array $mentioned Slugs, by type: plugins, and themes.
 * @return array
 */
function get_existing_slugs( array $mentioned ): array {
	global $wpdb;

	$sites = array(
		'plugins' => array( WPORG_PLUGIN_DIRECTORY_BLOGID, 'plugin' ),
		'themes'  => array( WPORG_THEME_DIRECTORY_BLOGID, 'repopackage' ),
	);

	foreach ( $mentioned as $type => $slugs ) {
		if ( ! isset( $sites[ $type ] ) || ! $slugs ) {
			unset( $mentioned[ $type ] );
			continue;
		}

		$slugs = array_values( array_unique( array_map( 'strval', $slugs ) ) );

		/*
		 * Not get_posts(): its "any" status only means statuses this request registers, and the directories' own, like a
		 * plugin in review's, aren't registered here.
		 */
		switch_to_blog( $sites[ $type ][0] );
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The placeholders are added for each slug.
		$mentioned[ $type ] = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_name FROM %i WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND post_name IN (" . implode( ',', array_fill( 0, count( $slugs ), '%s' ) ) . ')',
				array_merge( array( $wpdb->posts, $sites[ $type ][1] ), $slugs )
			)
		);
		// phpcs:enable
		restore_current_blog();
	}

	return array_filter( $mentioned );
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
