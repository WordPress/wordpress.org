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
 * Events counted, but not credited to the agent: restoring a conversation undoes its deletion, which was credited, and
 * notes are only sent for the plugins and themes they mention.
 *
 * @var string[]
 */
const UNCREDITED_EVENTS = array( 'conversation.restored', 'conversation.note_added' );

/**
 * WordPress.org accounts that write to FreeScout for automations, like the plugin directory's upload confirmations,
 * rather than for a person; their events aren't credited to anyone.
 *
 * @var int[]
 */
const AUTOMATION_USER_IDS = array( 5911429 );

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
 * The plugin directory reads the copy for a plugin's emails. A conversation replaces the copies of one merged into it,
 * and of the HelpScout conversations it, or that one, was imported from, taking over their plugins and themes: slug
 * changes update those, and the emails still name the old slug.
 *
 * @param object $request Request payload.
 * @return string COPY_DONE; COPY_RETRY if another event for the conversation held it up, or the write failed; or
 *                COPY_ALL_THREADS if it needs all of the conversation's threads. FreeScout sends it again for both.
 */
function log_email( object $request ): string {
	$id    = (int) ( $request->conversation->id ?? 0 );
	$email = $request->email ?? null;
	if ( ! $id || ! is_object( $email ) ) {
		return COPY_DONE;
	}

	/*
	 * Events for the conversation are written one at a time, see lock_email(). Each carries the conversation as it was
	 * when it was sent, so the copy ends up as the last one written says, which is current unless events crossed.
	 */
	if ( ! lock_email( $id ) ) {
		return COPY_RETRY;
	}

	try {
		// Deleted conversations and spam aren't kept; a deletion undone since is sent as published.
		$deleted = isset( $email->state ) ? 'deleted' === $email->state : 'conversation.deleted' === ( $request->event ?? '' );
		if ( $deleted || 'spam' === ( $email->status ?? '' ) ) {
			in_transaction(
				static function () use ( $request, $id ): void {
					delete_emails( array_merge( array( $id ), ...array_values( get_replaced_ids( $request ) ) ) );
				}
			);

			return COPY_DONE;
		}

		return write_email( $request, $id );
	} catch ( \Throwable $e ) {
		trigger_error( 'FreeScout copy of conversation ' . $id . ' failed: ' . $e->getMessage(), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- Logged, not shown.

		return COPY_RETRY;
	} finally {
		unlock_email( $id );
	}
}

/**
 * Writes WordPress.org's copy of a conversation, while holding its lock; see log_email().
 *
 * @param object $request Request payload.
 * @param int    $id      Conversation ID.
 * @return string COPY_DONE, or COPY_ALL_THREADS if the copy has nothing of the conversation yet, and the event doesn't
 *                carry all of its threads.
 */
function write_email( object $request, int $id ): string {
	global $wpdb;

	$email        = $request->email;
	$emails_table = "{$wpdb->base_prefix}helpscout";
	$meta_table   = "{$wpdb->base_prefix}helpscout_meta";
	$replaced     = get_replaced_ids( $request );
	$helpscout_id = (int) ( $request->helpscout_id ?? 0 );

	$row           = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $emails_table, $id ) );
	$has_helpscout = ! $row && $helpscout_id && $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE id = %d', $emails_table, $helpscout_id ) );
	if ( needs_all_threads( $request, $row, (bool) $has_helpscout ) ) {
		return COPY_ALL_THREADS;
	}

	$ids      = array_merge( array( $id ), ...array_values( $replaced ) );
	$all_meta = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT helpscout_id, meta_key, meta_value FROM %i WHERE helpscout_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholders are added for each ID.
			array_merge( array( $meta_table ), $ids )
		),
		ARRAY_A
	);

	// What the subject mentions was added when the copy got it; looking it up again takes queries for every event.
	$mailbox   = $request->email->mailbox ?? ( $request->mailbox ?? null );
	$subject   = (string) ( $email->subject ?? '' );
	$threads   = (array) ( $email->threads ?? array() );
	$seen      = $row && $subject === (string) $row->subject && get_copy_mailbox_slug( $request ) === (string) $row->mailbox;
	$mentioned = $seen && ! $threads ? array() : get_existing_slugs(
		get_plugin_or_theme_from_email(
			(object) array(
				'conversation' => (object) array( 'subject' => $seen ? '' : $subject ),
				'mailbox'      => $mailbox,
				'threads'      => $threads,
			)
		)
	);

	$plan = plan_email_write( $id, $replaced, (array) $all_meta, $mentioned );

	$user_id = (int) ( $row->user_id ?? 0 );
	if ( ! $user_id ) {
		$user    = get_sender_user( (object) array( 'sender' => $email->sender ?? null ) );
		$user_id = $user ? (int) $user->ID : 0;
	}

	$data    = get_email_row( $request, $row, $user_id );
	$columns = array_keys( $data );

	in_transaction(
		static function () use ( $wpdb, $emails_table, $meta_table, $id, $data, $columns, $plan ): void {
			// Its meta first, which names its helpdesk, and the HelpScout conversations it replaces.
			foreach ( $plan['meta'] as list( $meta_key, $meta_value ) ) {
				check_write(
					$wpdb->insert(
						$meta_table,
						array(
							'helpscout_id' => $id,
							'meta_key'     => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery -- Not a query.
							'meta_value'   => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery -- Not a query.
						)
					)
				);
			}

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

			delete_emails( $plan['delete'] );
		}
	);

	return COPY_DONE;
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

// Before the stats, which an event sent again would count twice.
$copied = log_email( $request );
if ( COPY_RETRY === $copied ) {
	status_header( 503 );
	header( 'Retry-After: ' . LOCK_TIMEOUT );
	exit;
}

header( 'Content-Type: application/json; charset=utf-8' );

// FreeScout sends the event again at once, with all of the conversation's threads; that one counts.
if ( COPY_ALL_THREADS === $copied ) {
	echo '{"threads":"all"}';
	exit;
}

contributor_stats( $request );

echo '{}';
