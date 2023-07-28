<?php

namespace WordPressdotorg\Forums;
use WP_User;

class Audit_Log {

	/**
	 * List of note IDs that have been edited, keyed by user ID.
	 *
	 * @var array
	 */
	var $edited_notes = [];

	/**
	 * Keeps track of the role changes for each user.
	 *
	 * @var array
	 */
	var $last_role_change = [];

	function __construct() {
		// Audit-log entries for Term subscriptions (add/remove)
		add_action( 'wporg_bbp_add_user_term_subscription',    [ $this, 'term_subscriptions' ], 10, 2 );
		add_action( 'wporg_bbp_remove_user_term_subscription', [ $this, 'term_subscriptions' ], 10, 2 );

		// Add a user note for forum role changes.
		add_action( 'add_user_role',     [ $this, 'monitor_role_changes' ], 10, 2 );
		add_action( 'remove_user_role',  [ $this, 'monitor_role_changes' ], 10, 2 );
		add_filter( 'bbp_set_user_role', [ $this, 'bbp_set_user_role' ],    10, 3 );

		// Add a user note when flagging/unflagging a user.
		add_action( 'wporg_bbp_flag_user',   [ $this, 'log_user_flag_changes' ], 10, 1 );
		add_action( 'wporg_bbp_unflag_user', [ $this, 'log_user_flag_changes' ], 10, 1 );

		// Slack logs for Moderation notes.
		add_action( 'wporg_bbp_note_added', [ $this, 'forums_notes_added' ], 10, 2 );
		add_action( 'shutdown',             [ $this, 'forums_notes_added_shutdown' ] );
	}

	/**
	 * Record audit log entries for when term subscriptions are added/removed.
	 */
	public function term_subscriptions( $user_id, $term_id ) {
		$action = 'wporg_bbp_add_user_term_subscription' == current_filter() ? 'subscribe' : 'unsubscribe';
		$type   = str_replace( 'topic-', '', get_term( $term_id )->taxonomy );

		$this->log(
			// plugin: plugin-slug (one-click)
			// theme: theme-slug
			// tag: tag-name
			"%s: %s%s",
			[
				// Args for the above sprintf.
				'type'        => $type,
				'slug'        => get_term( $term_id )->slug,
				// Tokenised links are from email unsubscribe links
				'one-click'   => ( isset( $_POST['List-Unsubscribe'] ) && 'One-Click' === $_POST['List-Unsubscribe'] ) ? ' (one-click)' : '',

				// Not used in the printf, but included in meta
				'request-uri' => $_SERVER['REQUEST_URI'],
				'referer'     => wp_get_raw_referer(),
			],
			$user_id,
			'subscriptions',
			$action,
			get_current_user_id()
		);
	}


	/**
	 * Keep track of the role changes that happen via bbp_set_user_role.
	 *
	 * TODO: Check if this is still needed, bbPress might pass context via bbp_set_user_role.
	 */
	function monitor_role_changes( $user_id, $role ) {
		$this->last_role_change[ $user_id ] ??= [];
		$this->last_role_change[ $user_id ][ current_filter() ] = $role;
	}

	/**
	 * Monitor for role changes, and log as appropriate.
	 *
	 * NOTE: This is also triggered on Locale forums. We may need to revisit that at some point.
	 */
	function bbp_set_user_role( $new_role, $user_id, WP_User $user ) {
		$previous_role = $this->last_role_change[ $user_id ][ 'remove_user_role' ] ?? false;

		// For the purposes of this function, a previous participant role is irrelevant.
		if ( bbp_get_participant_role() == $previous_role ) {
			$previous_role = false;
		}

		$monitored_roles = [
			bbp_get_keymaster_role(),
			bbp_get_moderator_role(),
			// bbp_get_participant_role(), // Not monitoring for changes involving only this role.
			bbp_get_spectator_role(),
			bbp_get_blocked_role()
		];

		if (
			// If the role change is not one we're monitoring, bail.
			! array_intersect( $monitored_roles, [ $new_role, $previous_role ] ) ||
			// If we can't detect any change, bail.
			$new_role === $previous_role
		) {
			return $new_role; // We're on a filter.
		}

		// Determine what triggered this change.
		$where_from = ! ms_is_switched() ? home_url( '/' ) : $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$where_from = explode( '?', $where_from )[0];
		$where_from = preg_replace( '!^(?:https?://)?(.+?)/?[^/]*$!i', '$1', $where_from );

		// Add a user note about this action.
		$note_text = sprintf(
			'Forum role changed to %s%s%s.',
			get_role( $new_role )->name,
			$previous_role ? sprintf( ' from %s', get_role( $previous_role )->name ) : '',
			$where_from ?    sprintf( ' via %s', $where_from ) : ''
		);

		// Used in wporg-login to add context.
		$note_text = apply_filters( 'wporg_bbp_forum_role_changed_note_text', $note_text, $user );

		// Add a user note about this action.
		Plugin::get_instance()->user_notes->add_user_note_or_update_previous(
			$user->ID,
			$note_text
		);

		// It's a filter, so we need to return the value.
		return $new_role;
	}

	/**
	 * Add a user note when a user is flagged / unflagged.
	 *
	 * @param int $user_id
	 */
	function log_user_flag_changes( $user_id ) {
		$flag_action = ( 'wporg_bbp_flag_user' === current_action() ) ? 'flagged' : 'unflagged';

		$note_text = "User {$flag_action}.";

		Plugin::get_instance()->user_notes->add_user_note_or_update_previous(
			$user_id,
			$note_text
		);
	}

	/**
	 * Record Note changes to Slack.
	 *
	 * @param int $user_id
	 * @param int $note_id
	 */
	function forums_notes_added( $user_id, $note_id ) {
		$this->edited_notes[ $user_id ] ??= [];
		$this->edited_notes[ $user_id ][] = $note_id;
	}

	/**
	 * Send Slack notifications for Note changes.
	 */
	function forums_notes_added_shutdown() {
		if ( ! $this->edited_notes || ! function_exists( 'notify_slack' ) || ! defined( 'FORUMS_MODACTIONS_SLACK_CHANNEL' ) ) {
			return;
		}

		// Fetch all the notes altered.
		foreach ( $this->edited_notes as $user_id => $note_ids ) {
			$note_ids = array_unique( $note_ids );
			$notes    = Plugin::get_instance()->user_notes->get_user_notes( $user_id, false );

			$notes = array_filter(
				$notes->raw,
				function( $note_id ) use ( $note_ids ) {
					return in_array( $note_id, $note_ids );
				},
				ARRAY_FILTER_USE_KEY
			);

			$notes = array_map(
				function( $note ) {
					$pretext = '';
					if ( strtotime( $note->date ) < time() - 5 ) {
						$pretext = '_(Edited)_ ';
					}

					return $pretext . $note->text;
				},
				$notes
			);

			$note_text = trim( implode( "\n", $notes ) );
			$note_text = str_replace( "\r", '', $note_text );

			if ( str_contains( $note_text, 'Forum role changed' ) ) {
				$action_text = 'Role changed';
			} else {
				$action_text = 'Note added';
			}

			$user_edit_url = bbp_get_user_profile_edit_url( $user_id );

			// On login.wordpress.org, the link should direct to the global forums.
			if ( defined( 'WPORG_LOGIN_REGISTER_BLOGID' ) && WPORG_LOGIN_REGISTER_BLOGID == get_current_blog_id() ) {
				$user_edit_url = sprintf( 'https://wordpress.org/support/users/%s/edit/', get_userdata( $user_id )->user_nicename );
			}

			$message = sprintf(
				"*%s for %s* (created %s ago)\n%s\n",
				$action_text,
				sprintf(
					'<%s|%s>',
					$user_edit_url,
					get_userdata( $user_id )->display_name ?: get_userdata( $user_id )->user_login
				),
				human_time_diff( strtotime( get_userdata( $user_id )->user_registered ) ),
				// Wrap the note in a blockquote.
				'> ' . str_replace(
					"\n",
					"\n> ",
					$note_text
				)
			);

			notify_slack( FORUMS_MODACTIONS_SLACK_CHANNEL, $message, wp_get_current_user() );
		}
	}

	/**
	 * Log audit log entries into Stream.
	 *
	 * This is a shortcut past the Stream Connectors, reaching in and calling the logging function directly..
	 *
	 * @see https://github.com/xwp/stream/blob/develop/classes/class-log.php#L57-L70 for args.
	 */
	public function log( $message, $args, $object_id, $context, $action, $user_id = null ) {
		if (
			! function_exists( 'wp_stream_get_instance' ) ||
			! is_callable( [ wp_stream_get_instance()->log, 'log' ] )
		) {
			return;
		}

		wp_stream_get_instance()->log->log( 'bbpress', $message, $args, $object_id, $context, $action, $user_id );
	}
}