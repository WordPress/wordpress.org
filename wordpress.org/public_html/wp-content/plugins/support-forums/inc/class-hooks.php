<?php

namespace WordPressdotorg\Forums;

class Hooks {

	public function __construct() {
		// Basic behavior filters and actions.
		add_filter( 'bbp_get_forum_pagination_count', '__return_empty_string' );

		// Display-related filters and actions.
		add_filter( 'bbp_get_topic_admin_links', array( $this, 'get_admin_links' ), 10, 3 );
		add_filter( 'bbp_get_reply_admin_links', array( $this, 'get_admin_links' ), 10, 3 );
	}

	/**
	 * Remove "Trash" from admin links. Trashing a topic or reply will eventually
	 * permanently delete it when the trash is emptied. Better to mark it as
	 * pending or spam.
	 */
	public function get_admin_links( $retval, $r, $args ) {
		unset( $r['links']['trash'] );

		$links = implode( $r['sep'], array_filter( $r['links'] ) );
		$retval = $r['before'] . $links . $r['after'];

		return $retval;
	}
}
