<?php

namespace WordPressdotorg\Forums;

class Users {

	public function __construct() {
		add_filter( 'bbp_get_user_display_role', array( $this, 'display_role' ), 10, 2 );
	}

	/**
	 * If the user has a custom title, use that instead of the forum role.
	 *
	 * @param string $role The user's forum role
	 * @param int $user_id The user id
	 * @return string The user's custom forum title, or their forum role
	 */
	public function display_role( $role, $user_id ) {
		$title = get_user_option( 'title', $user_id );
		if ( ! empty( $title ) ) {
			return esc_html( $title );
		}

		return $role;
	}
}
