<?php

namespace WordPressdotorg\Forums;

class Audit_Log {

	/**
	 * List of note IDs that have been edited, keyed by user ID.
	 *
	 * @var array
	 */
	var $edited_notes = [];

	function __construct() {
		// Audit-log entries for Term subscriptions (add/remove)
		add_action( 'wporg_bbp_add_user_term_subscription',    array( $this, 'term_subscriptions' ), 10, 2 );
		add_action( 'wporg_bbp_remove_user_term_subscription', array( $this, 'term_subscriptions' ), 10, 2 );

		// Slack logs for Moderation notes.
		add_action( 'wporg_bbp_note_added', [ $this, 'forums_notes_added' ], 10, 2 );
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
	 * Record Note changes to Slack.
	 *
	 * @param int $user_id
	 * @param int $note_id
	 */
	function forums_notes_added( $user_id, $note_id ) {
		$this->edited_notes[ $user_id ] ??= [];
		$this->edited_notes[ $user_id ][] = $note_id;

		if ( ! has_action( 'shutdown', [ $this, 'forums_notes_added_shutdown' ] ) ) {
			add_action( 'shutdown', [ $this, 'forums_notes_added_shutdown' ] );
		}
	}

	/**
	 * Send Slack notifications for Note changes.
	 */
	function forums_notes_added_shutdown() {
		if ( ! function_exists( 'notify_slack' ) || ! defined( 'FORUMS_MODACTIONS_SLACK_CHANNEL' ) ) {
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

			if ( str_contains( $note_text, 'Forum role changed' ) ) {
				$action_text = 'Role changed';
			} else {
				$action_text = 'Note added';
			}

			$user_edit_url = bbp_get_user_profile_edit_url( $user_id );

			// On login.wordpress.org, the link should direct to the global forums.
			if ( defined( 'WPORG_LOGIN_REGISTER_BLOGID' ) && WPORG_LOGIN_REGISTER_BLOGID == get_current_blog_id() ) {
				$user_edit_url = sprintf( 'https://wordpress.org/support/users/%s/edit/', urlencode( get_userdata( $user_id )->user_login ) );
			}

			$message = sprintf(
				"*%s for %s*\n%s\n",
				$action_text,
				sprintf(
					'<%s|%s>',
					$user_edit_url,
					get_userdata( $user_id )->display_name ?: get_userdata( $user_id )->user_login
				),
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