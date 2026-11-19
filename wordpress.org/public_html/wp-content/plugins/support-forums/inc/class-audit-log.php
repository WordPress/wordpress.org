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
	 * IDs of topics and replies that were already pending before this request edited them.
	 *
	 * @var int[]
	 */
	protected $pending_before_edit = [];

	function __construct() {
		// Audit-log entries for Term subscriptions (add/remove)
		add_action( 'wporg_bbp_add_user_term_subscription',    [ $this, 'term_subscriptions' ], 10, 2 );
		add_action( 'wporg_bbp_remove_user_term_subscription', [ $this, 'term_subscriptions' ], 10, 2 );

		// Add a user note for forum role changes.
		add_action( 'bbp_transition_user_role', [ $this, 'bbp_transition_user_role' ], 10, 3 );

		// Add a user note when flagging/unflagging a user.
		add_action( 'wporg_bbp_flag_user',   [ $this, 'log_user_flag_changes' ], 10, 1 );
		add_action( 'wporg_bbp_unflag_user', [ $this, 'log_user_flag_changes' ], 10, 1 );

		// Add a user note when a user's topic, reply, or review is held for moderation.
		add_action( 'bbp_new_topic', [ $this, 'log_pending_post' ], 20 );
		add_action( 'bbp_edit_topic', [ $this, 'log_pending_post' ], 20 );
		add_action( 'bbp_new_reply', [ $this, 'log_pending_post' ], 20 );
		add_action( 'bbp_edit_reply', [ $this, 'log_pending_post' ], 20 );
		add_action( 'bbp_edit_topic_pre_extras', [ $this, 'remember_pending_before_edit' ] );
		add_action( 'bbp_edit_reply_pre_extras', [ $this, 'remember_pending_before_edit' ] );

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
	 * Monitor for role changes, and log as appropriate.
	 *
	 * NOTE: This is also triggered on Locale forums. We may need to revisit that at some point.
	 *
	 * @param string|bool $new_role      New forum role, or false for no role.
	 * @param string|bool $previous_role Previous forum role, or false for no role.
	 * @param WP_User     $user          User whose forum role changed.
	 */
	public function bbp_transition_user_role( $new_role, $previous_role, WP_User $user ) {
		// Preserve the existing policy of not logging role removals.
		if ( ! $new_role ) {
			return;
		}

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
			return;
		}

		// Determine what triggered this change.
		$where_from = ! ms_is_switched() ? home_url( '/' ) : $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$where_from = explode( '?', $where_from )[0];
		$where_from = wp_parse_url( $where_from, PHP_URL_HOST ) ?: $where_from;

		// Add a user note about this action.
		$note_text = sprintf(
			'Forum role changed to %s%s%s.',
			$new_role,
			$previous_role ? sprintf( ' from %s', $previous_role ) : '',
			$where_from ?    sprintf( ' via %s', $where_from ) : ''
		);

		// Used in wporg-login & user-helpers to add context.
		$note_text = apply_filters( 'wporg_bbp_forum_role_changed_note_text', $note_text, $user );

		// Add a user note about this action.
		Plugin::get_instance()->user_notes->add_user_note_or_update_previous(
			$user->ID,
			$note_text
		);
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
	 * Add a user note when a user's own topic, reply, or review is held for moderation,
	 * naming the Comment Moderation words it matched.
	 *
	 * @param int $post_id The topic or reply ID.
	 */
	public function log_pending_post( $post_id ) {
		$post = get_post( $post_id );

		if (
			! $post ||
			bbp_get_pending_status_id() !== $post->post_status ||
			! $post->post_author ||
			// bbPress doesn't re-check a post that was already held, so this edit didn't hold it.
			in_array( $post->ID, $this->pending_before_edit, true ) ||
			// Only the author's own posts, not a moderator editing someone else's.
			get_current_user_id() !== (int) $post->post_author
		) {
			return;
		}

		// Only log the holds a moderator can act on.
		$words = $this->get_matched_moderation_keys( $post );
		if ( ! $words ) {
			return;
		}

		if ( bbp_get_reply_post_type() === $post->post_type ) {
			$type = 'reply';
		} elseif ( Plugin::REVIEWS_FORUM_ID === (int) $post->post_parent ) {
			$type = 'review';
		} else {
			$type = 'topic';
		}

		$note_text = sprintf(
			'%s held for moderation: matched %s.',
			str_starts_with( current_action(), 'bbp_edit_' ) ? 'Edited ' . $type : ucfirst( $type ),
			implode( ', ', array_map( fn( $word ) => '“' . $word . '”', $words ) )
		);

		// A separate note for each hold, so each one links to its own post.
		Plugin::get_instance()->user_notes->add_user_note(
			$post->post_author,
			$note_text,
			$post->ID
		);
	}

	/**
	 * Remember a topic or reply that is still pending as its edit is about to be saved.
	 *
	 * @param int $post_id The topic or reply ID.
	 */
	public function remember_pending_before_edit( $post_id ) {
		if ( bbp_get_pending_status_id() === get_post_status( $post_id ) ) {
			$this->pending_before_edit[] = (int) $post_id;
		}
	}

	/**
	 * Find the Comment Moderation words a topic or reply matches, the same way
	 * bbp_check_for_moderation() looks for them.
	 *
	 * @param \WP_Post $post The topic or reply.
	 * @return string[] The matched words.
	 */
	public function get_matched_moderation_keys( $post ) {
		$moderation = apply_filters( 'bbp_moderation_keys', trim( get_option( 'moderation_keys' ) ) );
		if ( ! $moderation ) {
			return [];
		}

		$user   = get_userdata( $post->post_author );
		$fields = [
			$user->display_name,
			$user->user_email,
			$user->user_url,
			bbp_current_author_ip(),
			bbp_current_author_ua(),
			$post->post_title,
			$post->post_content,
			wp_strip_all_tags( $post->post_content ),
		];

		$matched = [];
		foreach ( explode( "\n", $moderation ) as $word ) {
			$word = trim( $word );
			if ( ! $word ) {
				continue;
			}

			$pattern = '#' . preg_quote( $word, '#' ) . '#iu';
			foreach ( $fields as $field ) {
				if ( preg_match( $pattern, (string) $field ) ) {
					$matched[] = $word;
					break;
				}
			}
		}

		return $matched;
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

			// If we're on a Rosetta network, and bbPress is active, link to the local profile edit page.
			if ( defined( 'IS_ROSETTA_NETWORK' ) && IS_ROSETTA_NETWORK && function_exists( 'bbp_get_user_profile_edit_url' ) ) {
				$user_edit_url = bbp_get_user_profile_edit_url( $user_id );
			} else {
				// Link to the global WordPress.org profile edit page.
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
					htmlspecialchars_decode(
						$note_text
					)
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