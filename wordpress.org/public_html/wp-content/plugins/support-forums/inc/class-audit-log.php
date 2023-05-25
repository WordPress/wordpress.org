<?php

namespace WordPressdotorg\Forums;

class Audit_Log {

	function __construct() {
		// Audit-log entries for Term subscriptions (add/remove)
		add_action( 'wporg_bbp_add_user_term_subscription',    array( $this, 'term_subscriptions' ), 10, 2 );
		add_action( 'wporg_bbp_remove_user_term_subscription', array( $this, 'term_subscriptions' ), 10, 2 );
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