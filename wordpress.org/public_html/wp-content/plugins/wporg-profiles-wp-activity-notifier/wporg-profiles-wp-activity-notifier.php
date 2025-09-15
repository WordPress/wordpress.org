<?php
/*
Plugin Name: WordPress.org WP Activity Notifier
Description: Notifies profiles.wordpress.org when reportable WP activities occur.
Author: Mert Yazicioglu, Scott Reilly
Version: 1.1
*/

use WordPressdotorg\Profiles;

class WPOrg_WP_Activity_Notifier {
	/**
	 * The singleton instance.
	 *
	 * @var WPOrg_WP_Activity_Notifier
	 */
	private static $instance;

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return WPOrg_WP_Activity_Notifier
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize class.
	 */
	private function __construct() {
		$is_wordcamp = defined( 'IS_WORDCAMP_NETWORK' ) && IS_WORDCAMP_NETWORK;
		$environment = $is_wordcamp ? get_wordcamp_environment() : wp_get_environment_type();

		/*
		 * Requests will always fail when in local environments, unless the dev is proxied. Proxied devs could test
		 * locally if they're careful (especially with user IDs), but it's better to test on w.org sandboxes with
		 * test accounts. That prevents real profiles from having test data accidentally added to them.
		 */
		if ( 'local' === $environment ) {
			return;
		}

		if ( $is_wordcamp ) {
			require_once WP_CONTENT_DIR . '/mu-plugins-private/wporg-mu-plugins/pub/profile-helpers.php';
		} else {
			require_once WPMU_PLUGIN_DIR . '/pub/profile-helpers.php';
		}

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Register hook callbacks.
	 */
	public function init() {
		add_action( 'transition_post_status',    array( $this, 'maybe_notify_new_published_post'   ), 10, 3 );
		add_action( 'post_updated',              array( $this, 'maybe_notify_updated_post'         ), 10, 3 );
		add_action( 'transition_comment_status', array( $this, 'maybe_notify_new_approved_comment' ), 10, 3 );
		add_action( 'wp_insert_comment',         array( $this, 'insert_comment' ),                    10, 2 );

		// bbPress 2.x topic support.
		add_action( 'bbp_new_topic',             array( $this, 'notify_forum_new_topic' ),               12 );
		add_action( 'bbp_spammed_topic',         array( $this, 'notify_forum_remove_topic' )                );
		add_action( 'bbp_unspammed_topic',       array( $this, 'notify_forum_new_topic' )                   );
		add_action( 'bbp_trashed_topic',         array( $this, 'notify_forum_remove_topic' )                );
		add_action( 'bbp_untrashed_topic',       array( $this, 'notify_forum_new_topic' )                   );
		add_action( 'bbp_approved_topic',        array( $this, 'notify_forum_new_topic' )                   );
		add_action( 'bbp_unapproved_topic',      array( $this, 'notify_forum_remove_topic' )                );
		add_action( 'wporg_bbp_archived_topic',  array( $this, 'notify_forum_remove_topic' )                );

		// bbPress 2.x reply support.
		add_action( 'bbp_new_reply',             array( $this, 'notify_forum_new_reply' ),               12 );
		add_action( 'bbp_spammed_reply',         array( $this, 'notify_forum_remove_reply' )                );
		add_action( 'bbp_unspammed_reply',       array( $this, 'notify_forum_new_reply' )                   );
		add_action( 'bbp_approved_reply',        array( $this, 'notify_forum_new_reply' )                   );
		add_action( 'bbp_unapproved_reply',      array( $this, 'notify_forum_remove_reply' )                );
		add_action( 'bbp_trashed_reply',         array( $this, 'notify_forum_remove_reply' )                );
		add_action( 'bbp_untrashed_reply',       array( $this, 'notify_forum_new_reply' )                   );
		add_action( 'wporg_bbp_archived_reply',  array( $this, 'notify_forum_remove_reply' )                );
	}

	/**
	 * Indicates whether it is permitted to notify about a post or not.
	 *
	 * @param WP_Post $post The post.
	 * @param string  $action 'publish' for new posts, 'update' for existing posts, 'comment' for new comments.
	 *
	 * @return boolean True == the post can be notified about.
	 */
	public function is_post_notifiable( $post, $action ) {
		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}

		// All actions can notify about handbooks.
		$notifiable_post_types = array( 'handbook' );

		// There's a large number of custom handbooks, and more will be created in the future.
		if ( str_contains( $post->post_type, '-handbook' ) ) {
			$notifiable_post_types[] = $post->post_type;
		}

		if ( 'publish' === $action || 'comment' === $action ) {
			$notifiable_post_types = array_merge(
				$notifiable_post_types,
				array( 'post', 'handbook', 'wporg_workshop', 'lesson-plan', 'course' )
			);
		}

		// Some post types are imported programmatically and aren't directly attributable to a wordpress.org
		// account.
		$is_markdown_post = false;
		$post_meta        = get_post_custom( $post->ID );

		foreach ( $post_meta as $key => $value ) {
			if ( str_contains( $key, '_markdown_source' ) ) {
				$is_markdown_post = true;
			}
		}

		if ( is_plugin_active( 'subscribers-only.php' ) ) {
			$notifiable = false;
		} elseif ( ! in_array( $post->post_type, $notifiable_post_types, true ) ) {
			$notifiable = false;
		} elseif ( 'publish' != $post->post_status ) {
			$notifiable = false;
		} elseif ( ! empty( $post->post_password ) ) {
			$notifiable = false;
		} elseif ( $is_markdown_post || ! $post->post_author ) {
			// Some Handbook posts are automatically created and don't have an author.
			$notifiable = false;
		} elseif ( $post->_xpost_original_permalink || str_starts_with( $post->post_name, 'xpost-' ) ) {
			$notifiable = false;
		} else {
			$notifiable = true;
		}

		return apply_filters( 'wporg_profiles_wp_activity-is_post_notifiable', $notifiable, $post );
	}

	/**
	 * Only send notification for post getting published.
	 *
	 * @param string  $new_status The new status for the post.
	 * @param string  $old_status The old status for the post.
	 * @param WP_Post $post The post.
	 */
	public function maybe_notify_new_published_post( $new_status, $old_status, $post ) {
		if ( 'publish' != $new_status ) {
			return;
		}

		if ( $old_status == $new_status ) {
			return;
		}

		if ( ! $this->is_post_notifiable( $post, 'publish' ) ) {
			return;
		}

		$this->notify_blog_post( $post, 'new' );
	}

	/**
	 * Sends activity notification for new blog post.
	 *
	 * @param WP_Post $post The published post.
	 * @param string  $type 'new' for new posts, 'update' for existing ones.
	 */
	public function notify_blog_post( $post, $type ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		$user_id = 'new' === $type ? $post->post_author : get_current_user_id();
		$user    = get_user_by( 'id', $user_id );

		$content = wp_trim_words(
			strip_shortcodes( has_excerpt( $post ) ? $post->post_excerpt : $post->post_content ),
			55
		);

		$args = array(
			'action'    => 'wporg_handle_activity',
			'type'      => $type,
			'source'    => 'wordpress',
			'user'      => $user->user_login,
			'post_id'   => $post->ID,
			'blog'      => get_bloginfo( 'name' ),
			'blog_url'  => site_url(),
			'post_type' => $post->post_type,
			'title'     => get_the_title( $post ),
			'content'   => $content,
			'url'       => get_permalink( $post->ID ),
		);

		Profiles\api( $args );
	}

	/**
	 * Send an activity notification if the post being updated matches certain criteria.
	 */
	public function maybe_notify_updated_post( int $post_id, WP_Post $before, WP_Post $after ) : void {
		if ( 'publish' !== $before->post_status || 'publish' !== $after->post_status ) {
			return;
		}

		if ( ! $this->is_post_notifiable( $after, 'update' ) ) {
			return;
		}

		$this->notify_blog_post( $after, 'update' );
	}

	/**
	 * Handler for comment creation.
	 *
	 * @param int        $id      Comment ID.
	 * @param WP_Comment $comment Comment.
	 */
	public function insert_comment( $id, $comment ) {
		if ( 1 == $comment->comment_approved ) {
			$this->maybe_notify_new_approved_comment( 'approved', '', $comment );
		}
	}

	/**
	 * Only send notification for comment getting published on a public post.
	 *
	 * @param string     $new_status The new status for the comment.
	 * @param string     $old_status The old status for the comment.
	 * @param WP_Comment $comment    The comment.
	 */
	public function maybe_notify_new_approved_comment( $new_status, $old_status, $comment ) {
		if ( 'approved' != $new_status ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );

		if ( ! $this->is_post_notifiable( $post, 'comment' ) ) {
			return;
		}

		if ( apply_filters( 'wporg_profiles_wp_activity-is_comment_notifiable', true, $comment, $post ) ) {
			$this->notify_new_approved_comment( $comment, $post );
		}
	}

	/**
	 * Sends activity notification for new comment.
	 *
	 * @param WP_Comment $comment The comment.
	 * @param WP_Post    $post The comment's post.
	 */
	private function notify_new_approved_comment( $comment, $post ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		if ( ! $comment->user_id ) {
			return;
		}

		if ( ! $user = get_user_by( 'id', $comment->user_id ) ) {
			return;
		}

		$args = array(
			'action'     => 'wporg_handle_activity',
			'source'     => 'wordpress',
			'user'       => $user->user_login,
			'comment_id' => $comment->comment_ID,
			'content'    => get_comment_excerpt( $comment ),
			'title'      => get_the_title( $post ),
			'blog'       => get_bloginfo( 'name' ),
			'blog_url'   => site_url(),
			'url'        => get_comment_link( $comment ),
		);

		Profiles\api( $args );
	}

	/**
	 * Handler to actual send topic-related activity payload.
	 *
	 * @access private
	 *
	 * @param string $activity The activity type. One of: create-topic, remove-topic.
	 * @param int    $topic_id Topic ID.
	 */
	private function notify_forum_topic_payload( $activity, $topic_id ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		if ( ! bbp_is_site_public() ) {
			return;
		}

		if ( ! in_array( $activity, array( 'create-topic', 'remove-topic' ), true ) ) {
			return;
		}

		if ( 'create-topic' === $activity && ! bbp_is_topic_published( $topic_id ) ) {
			return;
		}

		$url = bbp_get_topic_permalink( $topic_id );
		// Remove moderator flags.
		$url = remove_query_arg( array( 'view' ), $url );

		$args = array(
			'action'   => 'wporg_handle_activity',
			'activity' => $activity,
			'source'   => 'forum',
			'user'     => get_user_by( 'id', bbp_get_topic_author_id( $topic_id ) )->user_login,
			'post_id'  => '',
			'topic_id' => $topic_id,
			'forum_id' => bbp_get_topic_forum_id( $topic_id ),
			'title'    => strip_tags( bbp_get_topic_title( $topic_id ) ),
			'url'      => $url,
			'message'  => bbp_get_topic_excerpt( $topic_id, 55 ),
			'site'     => get_bloginfo( 'name' ),
			'site_url' => site_url(),
		);

		if ( ! apply_filters( 'wporg_profiles_wp_activity-is_forum_notifiable', true, $args ) ) {
			return;
		}

		Profiles\api( $args );
	}

	/**
	 * Handler for bbPress 2.x topic creation.
	 *
	 * @param int $topic_id Topic ID.
	 */
	public function notify_forum_new_topic( $topic_id ) {
		$this->notify_forum_topic_payload( 'create-topic', $topic_id );
	}

	/**
	 * Handler for bbPress 2.x topic removal.
	 *
	 * @param int $topic_id Topic ID.
	 */
	public function notify_forum_remove_topic( $topic_id ) {
		$this->notify_forum_topic_payload( 'remove-topic', $topic_id );
	}

	/**
	 * Handler to actual send reply-related activity payload.
	 *
	 * @access private
	 *
	 * @param string $activity The activity type. One of: create-reply, remove-reply.
	 * @param int    $reply_id Reply ID.
	 */
	private function notify_forum_reply_payload( $activity, $reply_id ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		if ( ! bbp_is_site_public() ) {
			return;
		}

		if ( ! in_array( $activity, array( 'create-reply', 'remove-reply' ), true ) ) {
			return;
		}

		if ( 'create-reply' === $activity && ! bbp_is_reply_published( $reply_id ) ) {
			return;
		}

		$url = bbp_get_reply_url( $reply_id );
		// Remove moderator flags.
		$url = remove_query_arg( array( 'view' ), $url );

		$args = array(
			'action'   => 'wporg_handle_activity',
			'activity' => $activity,
			'source'   => 'forum',
			'user'     => get_user_by( 'id', bbp_get_reply_author_id( $reply_id ) )->user_login,
			'post_id'  => $reply_id,
			'topic_id' => bbp_get_reply_topic_id( $reply_id ),
			'forum_id' => bbp_get_reply_forum_id( $reply_id ),
			'title'    => strip_tags( bbp_get_reply_topic_title( $reply_id ) ),
			'url'      => $url,
			'message'  => $this->get_reply_excerpt( $reply_id, 15 ),
			'site'     => get_bloginfo( 'name' ),
			'site_url' => site_url(),
		);

		if ( ! apply_filters( 'wporg_profiles_wp_activity-is_forum_notifiable', true, $args ) ) {
			return;
		}

		Profiles\api( $args );
	}

	/**
	 * Handler for bbPress 2.x topic reply creation.
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function notify_forum_new_reply( $reply_id ) {
		$this->notify_forum_reply_payload( 'create-reply', $reply_id );
	}

	/**
	 * Handler for bbPress 2.x topic reply removal.
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function notify_forum_remove_reply( $reply_id ) {
		$this->notify_forum_reply_payload( 'remove-reply', $reply_id );
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
	 *
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
	 *
	 * @return string
	 */
	public function trim_text( $text, $length = 15, $trim_style = 'words' ) {
		$length     = (int) $length;
		$trim_style = in_array( $trim_style, array( 'chars', 'words' ), true ) ? $trim_style : 'words';

		// Remove blockquoted text since the text isn't original.
		$text = preg_replace( '/<blockquote>.+?<\/blockquote>/s', '', $text );
		$text = trim( strip_tags( $text ) );

		if ( function_exists( 'strip_shortcodes' ) ) {
			$text = strip_shortcodes( $text );
		}

		// If trimming by chars, behave like a more multibyte-aware
		// /* bbp_get_reply_excerpt */().
		if ( 'chars' === $trim_style ) {
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
		} else { // Else trim by words.
			$text = wp_trim_words( $text, $length );
		}

		return $text;
	}
}

WPOrg_WP_Activity_Notifier::get_instance();
