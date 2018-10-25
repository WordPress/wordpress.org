<?php
/**
 * Admin area customizations and tools.
 *
 * @package wporg-developer
 */

/**
 * Class to handle admin area customization and tools.
 */
class DevHub_Admin {

	/**
	 * Initializer.
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'do_init' ] );
	}

	/**
	 * Handles adding/removing hooks.
	 */
	public static function do_init() {
		add_action( 'comment_author', [ __CLASS__, 'append_user_nicename' ], 10, 2 );
	}

	/**
	 * Appends the user nicename to the user display name shown for comment authors.
	 *
	 * Facilitates discovery of @-mention name for users.
	 *
	 * @param string $author_name The comment author's display name.
	 * @param int    $comment_id  The comment ID.
	 * @return string
	 */
	public static function append_user_nicename( $author_name, $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( $comment->user_id ) {
			$username = get_user_by( 'id', $comment->user_id )->user_nicename;
	
			$author_name .= '</strong><div class="comment-author-nicename">@' . $username . '</div><strong>';
		}

		return $author_name;
	}

} // DevHub_Admin

DevHub_Admin::init();

