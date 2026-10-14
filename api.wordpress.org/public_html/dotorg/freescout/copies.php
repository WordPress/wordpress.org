<?php
/**
 * WordPress.org's copy of helpdesk conversations, `wporg_helpscout`, which the plugin directory reads: written from
 * FreeScout's events by webhook.php, and pointed at imported conversations by replace-copies.php.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

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

/**
 * A conversation's row in WordPress.org's copy, whose columns and table name are HelpScout's.
 *
 * @param object      $request Webhook payload, with the conversation in `email`; see WPOrgWebhooks' EventPayload.
 * @param object|null $row     Its current row, if any.
 * @param int         $user_id The sender's WordPress.org user ID, 0 if unknown.
 * @return array
 */
function get_email_row( object $request, ?object $row, int $user_id ): array {
	$email   = $request->email ?? new \stdClass();
	$sender  = $email->sender ?? new \stdClass();
	$address = (string) ( $sender->email ?? '' );
	$name    = trim( ( $sender->first_name ?? '' ) . ' ' . ( $sender->last_name ?? '' ) );

	$created = (int) strtotime( (string) ( $email->created_at ?? '' ) );
	$created = $created ? $created : (int) strtotime( (string) ( $row->created ?? '' ) );
	$closed  = (int) strtotime( (string) ( $email->closed_at ?? '' ) );
	$updated = (int) strtotime( (string) ( $email->user_updated_at ?? '' ) );
	$times   = array_filter( array( $created, $updated, $closed ) );

	return array(
		'id'       => (int) ( $request->conversation->id ?? 0 ),
		'number'   => (int) ( $email->number ?? 0 ),
		'user_id'  => $user_id,
		'mailbox'  => get_copy_mailbox_slug( $request ),
		'status'   => (string) ( $email->status ?? '' ),
		'email'    => $address ? ( $name ? "{$name} <{$address}>" : $address ) : (string) ( $row->email ?? '' ),
		'subject'  => (string) ( $email->subject ?? ( $row->subject ?? '' ) ),
		'preview'  => (string) ( $email->preview ?? ( $row->preview ?? '' ) ),
		'created'  => gmdate( 'Y-m-d H:i:s', $created ? $created : time() ),
		'closed'   => $closed ? gmdate( 'Y-m-d H:i:s', $closed ) : '',
		'modified' => gmdate( 'Y-m-d H:i:s', $times ? max( $times ) : time() ),
	);
}

/**
 * The slug of the conversation's current mailbox, which the copy keeps, rather than the event's, which stats count.
 *
 * @param object $request Webhook payload.
 * @return string
 */
function get_copy_mailbox_slug( object $request ): string {
	return sanitize_title( (string) ( $request->email->mailbox->name ?? ( $request->mailbox->name ?? '' ) ) );
}

/**
 * The copies an event's conversation replaces: one merged into it, and the HelpScout conversations it, or the one
 * merged into it, were imported from.
 *
 * @param object $request Webhook payload.
 * @return array[] FreeScout's IDs, under `freescout`, and HelpScout's, under `helpscout`.
 */
function get_replaced_ids( object $request ): array {
	return array(
		'freescout' => array_values( array_filter( array( (int) ( $request->merged_id ?? 0 ) ) ) ),
		'helpscout' => array_values(
			array_unique(
				array_filter(
					array(
						(int) ( $request->helpscout_id ?? 0 ),
						(int) ( $request->merged_helpscout_id ?? 0 ),
					)
				)
			)
		),
	);
}

/**
 * Whether copying a conversation needs all of its threads: the copy has neither it nor its HelpScout conversation, and
 * the event carries only the newest.
 *
 * @param object      $request       Webhook payload.
 * @param object|null $row           The conversation's row in the copy, if it has one.
 * @param bool        $has_helpscout Whether the copy has the HelpScout conversation it was imported from.
 * @return bool
 */
function needs_all_threads( object $request, ?object $row, bool $has_helpscout ): bool {
	return ! $row && ! $has_helpscout && empty( $request->email->all_threads );
}

/**
 * Moves the plugins and themes of copies to the copy that replaces them. Pairs it has already stay behind, and go with
 * their copy.
 *
 * @param int   $id  ID of the copy that replaces them.
 * @param int[] $ids IDs of the copies it replaces.
 * @return void
 *
 * @throws \RuntimeException If the write failed.
 */
function take_over_meta( int $id, array $ids ): void {
	global $wpdb;

	if ( ! $ids ) {
		return;
	}

	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholders are added for each ID.
	check_write( $wpdb->query( $wpdb->prepare( 'UPDATE IGNORE %i SET helpscout_id = %d WHERE helpscout_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( "{$wpdb->base_prefix}helpscout_meta", $id ), $ids ) ) ) );
}

/**
 * Adds a FreeScout copy's meta, skipping pairs it has: its helpdesk, the HelpScout conversations it replaces, and the
 * plugins and themes it mentions.
 *
 * @param int     $id            Conversation ID.
 * @param int[]   $helpscout_ids HelpScout conversations it replaces.
 * @param array[] $mentioned     Plugins' and themes' slugs, by type.
 * @return void
 *
 * @throws \RuntimeException If the write failed.
 */
function add_copy_meta( int $id, array $helpscout_ids, array $mentioned ): void {
	global $wpdb;

	$pairs = array( array( 'helpdesk', 'freescout' ) );
	foreach ( $helpscout_ids as $helpscout_id ) {
		$pairs[] = array( 'helpscout_conversation', (string) $helpscout_id );
	}
	foreach ( $mentioned as $type => $slugs ) {
		foreach ( $slugs as $slug ) {
			$pairs[] = array( (string) $type, (string) $slug );
		}
	}

	$args = array( "{$wpdb->base_prefix}helpscout_meta" );
	foreach ( $pairs as list( $meta_key, $meta_value ) ) {
		array_push( $args, $id, $meta_key, $meta_value );
	}

	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The placeholders are added for each pair.
	check_write( $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i ( helpscout_id, meta_key, meta_value ) VALUES ' . implode( ', ', array_fill( 0, count( $pairs ), '( %d, %s, %s )' ) ), $args ) ) );
}

/**
 * Deletes conversations from WordPress.org's copy, with their meta.
 *
 * @param int[] $ids Conversation IDs; HelpScout's for the copies of its conversations.
 * @return void
 *
 * @throws \RuntimeException If a deletion failed.
 */
function delete_emails( array $ids ): void {
	global $wpdb;

	if ( ! $ids ) {
		return;
	}

	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholders are added for each ID.
	check_write( $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( "{$wpdb->base_prefix}helpscout" ), $ids ) ) ) );
	check_write( $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE helpscout_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( "{$wpdb->base_prefix}helpscout_meta" ), $ids ) ) ) );
	// phpcs:enable
}

/**
 * What switching an imported conversation does to its copy.
 *
 * @param object $copy          The imported conversation: helpscout_id, id, number, status, and delete.
 * @param bool   $has_helpscout Whether the copy has the HelpScout conversation.
 * @param bool   $has_freescout Whether it has the FreeScout one.
 * @return string `skip`; `delete` both copies (deleted or spam); `rename` the HelpScout copy to the FreeScout ID; or
 *                `merge` it into the FreeScout copy.
 */
function plan_copy_replacement( object $copy, bool $has_helpscout, bool $has_freescout ): string {
	$helpscout_id = (int) ( $copy->helpscout_id ?? 0 );
	$id           = (int) ( $copy->id ?? 0 );
	if ( $helpscout_id <= 0 || $id <= 0 || $helpscout_id === $id ) {
		return 'skip';
	}

	if ( ! empty( $copy->delete ) ) {
		return $has_helpscout || $has_freescout ? 'delete' : 'skip';
	}

	if ( ! $has_helpscout ) {
		return 'skip';
	}

	return $has_freescout ? 'merge' : 'rename';
}

/**
 * Throws if a write failed: wpdb returns false instead.
 *
 * @param mixed $result What wpdb returned.
 * @return void
 *
 * @throws \RuntimeException If it failed.
 */
function check_write( mixed $result ): void {
	global $wpdb;

	if ( false === $result ) {
		throw new \RuntimeException( 'Could not write the copy: ' . (string) $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Logged, not shown.
	}
}
