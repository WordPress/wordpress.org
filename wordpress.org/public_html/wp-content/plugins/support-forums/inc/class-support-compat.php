<?php
/**
 * Hooks for the support forums at https://wordpress.org/support only.
 */

namespace WordPressdotorg\Forums;

class Support_Compat {

	var $loaded = false;

	public function __construct() {
		if ( ! $this->loaded ) {

			// Topic resolution modifications.
			add_filter( 'wporg_bbp_topic_resolution_is_enabled_on_forum', array( $this, 'is_enabled_on_forum' ), 10, 2 );

			$this->loaded = true;
		}
	}

	/**
	 * Disable topic resolutions on the reviews forum.
	 *
	 * @param bool $retval Is topic resolution enabled for this forum?
	 * @param int $forum_id Optional. The forum id
	 * @return bool True if enabled, otherwise false
	 */
	public function is_enabled_on_forum( $retval, $forum_id = 0 ) {
		// Check the passed forum id.
		if ( ! empty( $forum_id ) ) {
			$retval = ( $forum_id != Plugin::REVIEWS_FORUM_ID );
		}

		// Check the current forum.
		if ( bbp_is_single_forum() ) {
			$retval = ( bbp_get_forum_id() != Plugin::REVIEWS_FORUM_ID );
		}

		// Check the current topic forum.
		if ( bbp_is_single_topic() || bbp_is_topic_edit() ) {
		   	$retval = ( bbp_get_topic_forum_id() != Plugin::REVIEWS_FORUM_ID );
		}

		return $retval;
	}
}
