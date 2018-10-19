<?php
/**
 * Code Reference edit user submitted content (comments, notes, etc).
 *
 * Allows users to edit top level and child comments from parsed post types.
 *
 * @package wporg-developer
 */

/**
 * Class to handle editing user submitted content.
 */
class DevHub_User_Content_Edit {

	/**
	 * Initializer
	 */
	public static function init() {
		// Priority 20 is after this theme and the WP Parser register post types.
		add_action( 'init', array( __CLASS__, 'do_init' ), 20 );
	}

	/**
	 * Handles adding hooks to enable editing comments.
	 * Adds rewrite rules for editing comments in the front end.
	 */
	public static function do_init() {
		// Add the edit user note rewrite rule
		add_rewrite_rule( 'reference/comment/edit/([0-9]{1,})/?$', 'index.php?edit_user_note=$matches[1]', 'top' );

		// Update comment for edit comment request
		self::update_comment();

		// Add edit_user_note query var for editing.
		add_filter( 'query_vars',                      array( __CLASS__, 'comment_query_var' ) );

		// Redirect to home page if the edit request is invalid.
		add_action( 'template_redirect',               array( __CLASS__, 'redirect_invalid_edit_request' ) );

		// Include the comment edit template.
		add_filter( 'template_include',                array( __CLASS__, 'template_include' ) );

		// Set the post_type and post id for use in the comment edit template.
		add_action( 'pre_get_posts',                   array( __CLASS__, 'pre_get_posts' ) );
	}

	/**
	 * Add the edit_user_note query var to the public query vars.
	 *
	 * @param array $query_vars Array with public query vars.
	 * @return array Public query vars.
	 */
	public static function comment_query_var( $query_vars ) {
		$query_vars[] = 'edit_user_note';
		return $query_vars;
	}

	/**
	 * Update a comment after editing.
	 */
	public static function update_comment() {

		if ( is_admin() || ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$comment_data = wp_unslash( $_POST );

		$defaults = array(
			'update_user_note',
			'_wpnonce',
			'comment_ID',
			'comment',
			'comment_parent',
			'comment_post_ID'
		);

		foreach ( $defaults as $value ) {
			if ( ! isset( $comment_data[ $value ] ) ) {
				// Return if any of the $_POST keys are missing.
				return;
			}
		}

		$comment = trim( (string) $comment_data['comment'] );
		if ( ! $comment ) {
			// Bail and provide a way back to the edit form if a comment is empty.
			$msg  = __( '<strong>ERROR</strong>: please type a comment.', 'wporg' );
			$args = array( 'response' => 200, 'back_link' => true );
			wp_die( '<p>' . $msg . '</p>', __( 'Comment Submission Failure', 'wporg' ), $args );
		}

		$updated       = 0;
		$post_id       = absint( $comment_data['comment_post_ID'] );
		$comment_id    = absint( $comment_data['comment_ID'] );
		$can_user_edit = DevHub\can_user_edit_note( $comment_id );
		$action        = 'update_user_note_' . $comment_id;
		$nonce         = wp_verify_nonce( $comment_data['_wpnonce'], $action );

		if ( $nonce && $can_user_edit ) {
			$comment_data['comment_content'] = $comment;
			$updated = wp_update_comment( $comment_data );
		}

		$location = get_permalink( $post_id );
		if ( $location ) {
			$query = $updated ? '?updated-note=' . $comment_id : '';
			$query .= '#comment-' . $comment_id;
			wp_safe_redirect( $location . $query );
			exit;
		}
	}

	/**
	 * Redirects to the home page if the edit request is invalid for the current user.
	 *
	 * Redirects if the comment doesn't exist.
	 * Redirects if the comment is not for a parsed post type.
	 * Redirects if the current user is not the comment author.
	 * Redirects if a comment is already approved.
	 *
	 * Doesn't redirect for users with the edit_comment capability.
	 */
	public static function redirect_invalid_edit_request() {
		$comment_id = absint( get_query_var( 'edit_user_note' ) );
		if ( ! $comment_id ) {
			// Not a query for editing a note.
			return;
		}

		if ( ! DevHub\can_user_edit_note( $comment_id ) ) {
			wp_redirect( home_url( '/reference' ) );
			exit();
		}
	}

	/**
	 * Use the 'comments-edit.php' template for editing comments.
	 *
	 * The current user has already been verified in the template_redirect action.
	 *
	 * @param string $template Template to include.
	 * @return string Template to include.
	 */
	public static function template_include( $template ) {
		$comment_id = absint( get_query_var( 'edit_user_note' ) );
		if ( ! $comment_id ) {
			// Not a query for editing a note.
			return $template;
		}

		$comment_template = get_query_template( "comments-edit" );
		if ( $comment_template ) {
			$template = $comment_template;
		}

		return $template;
	}

	/**
	 * Sets the post and post type for an edit request.
	 *
	 * Trows a 404 if the current user can't edit the requested note.
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference)
	 */
	public static function pre_get_posts( $query ) {
		$comment_id = absint( get_query_var( 'edit_user_note' ) );

		if ( is_admin() || ! ( $query->is_main_query() && $comment_id ) ) {
			// Not a query for editing a note.
			return;
		}

		if ( DevHub\can_user_edit_note( $comment_id ) ) {
			$comment = get_comment( $comment_id );
			if ( isset( $comment->comment_post_ID ) ) {
				$query->is_singular = true;
				$query->is_single = true;
				$query->set( 'post_type', get_post_type( $comment->comment_post_ID ) );
				$query->set( 'p', $comment->comment_post_ID );

				return;
			}
		}

		// Set 404 if a user can't edit a note.
		$query->set_404();
	}

} // DevHub_User_Content_Edit

DevHub_User_Content_Edit::init();

