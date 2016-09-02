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

		// bbPress 2.x topic support
		add_action( 'bbp_new_topic',             array( $this, 'notify_forum_new_topic' ),               12 );
		add_action( 'bbp_spammed_topic',         array( $this, 'notify_forum_remove_topic' )                );
		add_action( 'bbp_unspammed_topic',       array( $this, 'notify_forum_new_topic' )                   );
		add_action( 'bbp_trashed_topic',         array( $this, 'notify_forum_remove_topic' )                );
		add_action( 'bbp_untrashed_topic',       array( $this, 'notify_forum_new_topic' )                   );
		add_action( 'bbp_approved_topic',        array( $this, 'notify_forum_new_topic' )                   );
		add_action( 'bbp_unapproved_topic',      array( $this, 'notify_forum_remove_topic' )                );

		// bbPress 2.x reply support
		add_action( 'bbp_new_reply',             array( $this, 'notify_forum_new_reply' ),               12 );
		add_action( 'bbp_spammed_reply',         array( $this, 'notify_forum_remove_reply' )                );
		add_action( 'bbp_unspammed_reply',       array( $this, 'notify_forum_new_reply' )                   );
		add_action( 'bbp_approved_reply',        array( $this, 'notify_forum_new_reply' )                   );
		add_action( 'bbp_unapproved_reply',      array( $this, 'notify_forum_remove_reply' )                );
		add_action( 'bbp_trashed_reply',         array( $this, 'notify_forum_remove_reply' )                );
		add_action( 'bbp_untrashed_reply',       array( $this, 'notify_forum_new_reply' )                   );
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

	/**
	 * Handler to actual send topic-related activity payload.
	 *
	 * @access private
	 *
	 * @param string $activity. The activity type. One of: create-topic, remove-topic.
	 * @param int    $topic_id  Topic ID.
	 */
	private function _notify_forum_topic_payload( $activity, $topic_id ) {

		// Don't notify if importing.
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		// Bail if site is private.
		if ( ! bbp_is_site_public() ) {
			return;
		}

		// Only handle recognized activities.
		if ( ! in_array( $activity, array( 'create-topic', 'remove-topic' ) ) ) {
			return;
		}

		// Bail on create-topic if topic is not published.
		if ( 'create-topic' === $activity && ! bbp_is_topic_published( $topic_id ) ) {
			return;
		}

		$args = array(
			'body' => array(
				'action'    => 'wporg_handle_activity',
				'activity'  => $activity,
				'source'    => 'forum',
				'user'      => get_user_by( 'id', bbp_get_topic_author_id( $topic_id ) )->user_login,
				'post_id'   => '',
				'topic_id'  => $topic_id,
				'forum_id'  => bbp_get_topic_forum_id( $topic_id ),
				'title'     => bbp_get_topic_title( $topic_id ),
				'url'       => bbp_get_topic_permalink( $topic_id ),
				'message'   => bbp_get_topic_excerpt( $topic_id, 55 ),
				'site'      => get_bloginfo( 'name' ),
				'site_url'  => site_url(),
			)
		);

		$x = wp_remote_post( $this->activity_handler_url, $args );
	}

	/**
	 * Handler for bbPress 2.x topic creation.
	 *
	 * @param int $topic_id Topic ID.
	 */
	public function notify_forum_new_topic( $topic_id ) {
		$this->_notify_forum_topic_payload( 'create-topic', $topic_id );
	}

	/**
	 * Handler for bbPress 2.x topic removal.
	 *
	 * @param int $topic_id Topic ID.
	 */
	public function notify_forum_remove_topic( $topic_id ) {
		$this->_notify_forum_topic_payload( 'remove-topic', $topic_id );
	}

	/**
	 * Handler to actual send reply-related activity payload.
	 *
	 * @access private
	 *
	 * @param string $activity. The activity type. One of: create-reply, remove-reply.
	 * @param int    $reply_id  Reply ID.
	 */
	private function _notify_forum_reply_payload( $activity, $reply_id ) {

		// Don't notify if importing.
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		// Bail if site is private.
		if ( ! bbp_is_site_public() ) {
			return;
		}

		// Only handle recognized activities.
		if ( ! in_array( $activity, array( 'create-reply', 'remove-reply' ) ) ) {
			return;
		}

		// Bail on create-reply if not published.
		if ( 'create-reply' === $activity && ! bbp_is_reply_published( $reply_id ) ) {
			return;
		}

		$args = array(
			'body' => array(
				'action'    => 'wporg_handle_activity',
				'activity'  => $activity,
				'source'    => 'forum',
				'user'      => get_user_by( 'id', bbp_get_reply_author_id( $reply_id ) )->user_login,
				'post_id'   => $reply_id,
				'topic_id'  => bbp_get_reply_topic_id( $reply_id ),
				'forum_id'  => bbp_get_reply_forum_id( $reply_id ),
				'title'     => bbp_get_reply_topic_title( $reply_id ) ,
				'url'       => bbp_get_reply_url( $reply_id ),
				'message'   => $this->get_reply_excerpt( $reply_id, 15 ),
				'site'      => get_bloginfo( 'name' ),
				'site_url'  => site_url(),
			)
		);

		wp_remote_post( $this->activity_handler_url, $args );

	}

	/**
	 * Handler for bbPress 2.x topic reply creation.
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function notify_forum_new_reply( $reply_id ) {
		$this->_notify_forum_reply_payload( 'create-reply', $reply_id );
	}

	/**
	 * Handler for bbPress 2.x topic reply removal.
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function notify_forum_remove_reply( $reply_id ) {
		$this->_notify_forum_reply_payload( 'remove-reply', $reply_id );
	}

	/**
	 * Returns the excerpt of the reply.
	 *
	 * This is similar to `bbp_get_reply_excerpt()` except:
	 *
	 * - Excerpt length is by number of words and not number of characters.
	 * - Omits inclusion of any blockquoted text.
	 *
	 * @param int $reply_id Optional. The reply id.
	 * @param int $words    Optional. The number of words for the excerpt. Default 15.
	 * @return string
	 */
	public function get_reply_excerpt( $reply_id = 0, $words = 15 ) {
		$reply_id = bbp_get_reply_id( $reply_id );
		$excerpt  = get_post_field( 'post_excerpt', $reply_id );

		if ( ! $excerpt ) {
			$excerpt = bbp_get_reply_content( $reply_id );
		}

		$excerpt = $this->trim_text( $excerpt, $words, 'words' );
		return apply_filters( 'bbp_get_reply_excerpt', $excerpt, $reply_id, $words );
	}

	/**
	 * Trims text by words or characters.
	 *
	 * @param string $text       The text to trim.
	 * @param int    $length     Optional. The number of words or characters to try down to. Default 15.
	 * @param string $trim_style Optional. The manner in which the text should be trimmed. Either 'chars' or 'words'. Default 'words'.
	 * @return string
	 */
	public function trim_text( $text, $length = 15, $trim_style = 'words' ) {
		$length     = (int) $length;
		$trim_style = in_array( $trim_style, array( 'chars', 'words' ) ) ? $trim_style : 'words';

		// Remove blockquoted text since the text isn't original.
		$text = preg_replace( '/<blockquote>.+<\/blockquote>/', '', $text );

		// Strip tags and surrounding whitespace.
		$text = trim ( strip_tags( $text ) );

		// If trimming by chars, behave like a more multibyte-aware
		// bbp_get_reply_excerp().
		if ( 'chars' === $trim_style ) {
			// Multibyte support
			if ( function_exists( 'mb_strlen' ) ) {
				$text_length = mb_strlen( $text );
			} else {
				$text_length = strlen( $text );
			}

			if ( $length && ( $text_length > $length ) ) {
				if ( function_exists( 'mb_strlen' ) ) {
					$text = mb_substr( $text, 0, $length - 1 );
				} else {
					$text = substr( $text, 0, $length - 1 );
				}
				$text .= '&hellip;';
			}
		}
		// Else trim by words.
		else {
			$text = wp_trim_words( $text, $length );
		}

		return $text;
	}

}

new WPOrg_WP_Activity_Notifier();
