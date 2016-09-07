<?php
/**
 * Hooks for the support forums at https://wordpress.org/support only.
 */

namespace WordPressdotorg\Forums;

class Support_Compat {

	var $loaded = false;

	public function __construct() {
		if ( ! $this->loaded ) {

			// Exclude compat forums from forum dropdown.
			add_filter( 'bbp_after_get_dropdown_parse_args', array( $this, 'get_dropdown' ) );

			// Topic resolution modifications.
			add_filter( 'wporg_bbp_topic_resolution_is_enabled_on_forum', array( $this, 'is_enabled_on_forum' ), 10, 2 );

			$this->loaded = true;
		}
	}

	/**
	 * Remove compat forums from forum dropdown on front-end display.
	 *
	 * @param array $r The function args
	 * @return array The filtered args
	 */
	public function get_dropdown( $r ) {
		if ( is_admin() || ! isset( $r['post_type'] ) || ! $r['post_type'] == bbp_get_forum_post_type() ) {
			return $r;
		}

		// Set up compat forum exclusion.
		if ( bbp_is_topic_edit() || bbp_is_single_view() ) {
			if ( is_array( $r['exclude'] ) ) {
				$r['exclude'] = array_unique( array_merge( $r['exclude'], self::get_compat_forums() ) );
			} elseif( empty( $r['exclude'] ) ) {
				$r['exclude'] = self::get_compat_forums();
			}

			if ( self::is_compat_forum( $r['selected'] ) ) {
				// Prevent forum changes for topics in compat forums.
				add_filter( 'bbp_get_dropdown', array( $this, 'dropdown' ), 10, 2 );
			}
		}
		return $r;
	}

	/**
	 * Disable forum changes on topics in the compat forums.
	 *
	 * @param string $retval The dropdown
	 * @param array $r The function arguments
	 * @return string The dropdown, or substituted hidden input
	 */
	public function dropdown( $retval, $r ) {
		if ( self::is_compat_forum( $r['selected'] ) ) {
			$retval = esc_html( bbp_get_forum_title( $r['selected'] ) );
			$retval .= sprintf( '<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="%d" />', (int) $r['selected'] );
		}
		return $retval;
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

		// Check the current view.
		if ( bbp_is_single_view() ) {
			$retval = ( bbp_get_view_id() != 'reviews' );
		}

		return $retval;
	}

	public static function get_compat_forums() {
		return array( Plugin::PLUGINS_FORUM_ID, Plugin::THEMES_FORUM_ID, Plugin::REVIEWS_FORUM_ID );
	}

	public static function is_compat_forum( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			return false;
		}
		return in_array( $post_id, self::get_compat_forums() );
	}
}
