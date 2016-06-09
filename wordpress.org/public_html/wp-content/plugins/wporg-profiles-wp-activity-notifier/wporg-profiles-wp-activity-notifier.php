<?php
/*
Plugin Name: WordPress.org WP Activity Notifier
Description: Notifies profiles.wordpress.org when reportable WP activities occur.
Author: Mert Yazicioglu, Scott Reilly
Version: 1.1
*/

class WPOrg_WP_Activity_Notifier {

	private $activity_handler_url = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		add_action( 'transition_post_status',    array( $this, 'maybe_notify_new_published_post'   ), 10, 3 );
		add_action( 'transition_comment_status', array( $this, 'maybe_notify_new_approved_comment' ), 10, 3 );
		add_action( 'wp_insert_comment',         array( $this, 'insert_comment' ),                    10, 2 );
	}

	/**
	 * Indicates whether it is permitted to notify about a post or not.
	 *
	 * @param WP_Post $post The post
	 * @return boolean True == the post can be notified about.
	 */
	public function is_post_notifiable( $post ) {

		// Sanity check the argument is a post
		if ( ! $post || ! is_a( $post, 'WP_Post' ) )
			return false;

		// Don't notify if the site is for subscribers only
		if ( class_exists( 'Subscribers_Only' ) )
			$notifiable = false;

		// Don't notify if not of 'post' post_type
		elseif ( 'post' != $post->post_type )
			$notifiable = false;

		// Don't notify if not publicly published
		elseif ( 'publish' != $post->post_status )
			$notifiable = false;

		// Don't notify if password is required
		elseif ( ! empty( $post->post_password ) )
			$notifiable = false;

		// At this point it is permitted to notify about the post
		else
			$notifiable = true;

		// Return filtered value to allow overriding or extending checks
		return apply_filters( 'wporg_profiles_wp_activity-is_post_notifiable', $notifiable, $post );

	}

	/**
	 * Only send notification for post getting published.
	 *
	 * @param string $new_status The new status for the post
	 * @param string $old_status The old status for the post
	 * @param WP_Post $post The post
	 */
	public function maybe_notify_new_published_post( $new_status, $old_status, $post ) {

		// Only proceed if the post is transitioning to the publish status
		if ( 'publish' != $new_status )
			return;

		// Only proceed if the post is actually changing status
		if ( $old_status == $new_status )
			return;

		// Only proceed if permitted to notify about the post
		if ( ! $this->is_post_notifiable( $post ) )
			return;

		// Send notification for the post
		$this->notify_new_blog_post( $post );
	}

	/**
	 * Sends activity notification for new blog post.
	 *
	 * @param WP_Post $post The published post
	 */
	public function notify_new_blog_post( $post ) {
		$author = get_user_by( 'id', $post->post_author );

		$content = wp_trim_words(
			( has_excerpt( $post ) ? $post->post_excerpt : $post->post_content ),
			55
		);

		$args = array(
			'body' => array(
				'action'   => 'wporg_handle_activity',
				'source'   => 'wordpress',
				'user'     => $author->user_login,
				'post_id'  => $post->ID,
				'blog'     => get_bloginfo( 'name' ),
				'blog_url' => site_url(),
				'title'    => get_the_title( $post ),
				'content'  => $content,
				'url'      => get_permalink( $post->ID ),
			)
		);

		wp_remote_post( $this->activity_handler_url, $args );
	}

	/**
	 * Handler for comment creation.
	 *
	 * @param int $id         Comment ID
	 * @param object $comment Comment
	 * @return void
	*/
	function insert_comment( $id, $comment ) {
		if ( 1 == $comment->comment_approved )
			$this->maybe_notify_new_approved_comment( 'approved', '', $comment );
	}

	/**
	 * Only send notification for comment getting published on a public post.
	 *
	 * @param string $new_status The new status for the comment
	 * @param string $old_status The old status for the comment
	 * @param WP_Comment $comment The comment
	 */
	public function maybe_notify_new_approved_comment( $new_status, $old_status, $comment ) {

		// Only proceed if the comment is transitioning to the approved status
		if ( 'approved' != $new_status )
			return;

		$post = get_post( $comment->comment_post_ID );

		// Only proceed if permitted to notify about the post
		if ( ! $this->is_post_notifiable( $post ) )
			return;

		// Only proceed if there are no objections to the comment notification
		if ( apply_filters( 'wporg_profiles_wp_activity-is_comment_notifiable', true, $comment, $post ) )
			$this->notify_new_approved_comment( $comment, $post );
	}

	/**
	 * Sends activity notification for new comment.
	 *
	 * @param WP_Comment $comment The comment
	 * @param WP_Post $post The comment's post
	 */
	private function notify_new_approved_comment( $comment, $post ) {

		if ( ! $comment->user_id )
			return;

		if ( ! $user = get_user_by( 'id', $comment->user_id ) )
			return;

		$args = array(
			'body' => array(
				'action'     => 'wporg_handle_activity',
				'source'     => 'wordpress',
				'user'       => $user->user_login,
				'comment_id' => $comment->comment_ID,
				'content'    => get_comment_excerpt( $comment ),
				'title'      => get_the_title( $post ),
				'blog'       => get_bloginfo( 'name' ),
				'blog_url'   => site_url(),
				'url'        => get_comment_link( $comment ),
			)
		);

		wp_remote_post( $this->activity_handler_url, $args );
	}

}

new WPOrg_WP_Activity_Notifier();
