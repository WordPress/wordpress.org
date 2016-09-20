<?php
/**
 * Plugin Name: o2 Posting Access
 * Description: Allows any registered member to post on your o2 site.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\o2\Posting_Access;

class Plugin {

	/**
	 * Initializes actions and filters.
	 */
	public function init() {
		add_filter( 'user_has_cap', [ $this, 'add_post_capabilities' ], 10, 4 );
		add_action( 'admin_bar_menu', [ $this, 'remove_non_accessible_menu_items' ], 100 );

		if ( apply_filters( 'wporg_o2_enable_pending_for_unknown_users', true ) ) {
			add_filter( 'gettext_with_context', [ $this, 'replace_post_button_label' ], 10, 4 );
			add_filter( 'o2_create_post', [ $this, 'save_new_post_as_pending' ] );
			add_filter( 'the_title', [ $this, 'prepend_pending_notice' ], 10, 2 );
			add_filter( 'comments_open', [ $this, 'close_comments_for_pending_posts' ], 10, 2 );
		}
	}

	/**
	 * Disable comments for pending posts.
	 *
	 * @see wp_handle_comment_submission()
	 *
	 * @param bool  $open    Whether the current post is open for comments.
	 * @param int   $post_id The post ID or WP_Post object.
	 * @return bool Whether the current post is open for comments.
	 */
	public function close_comments_for_pending_posts( $open, $post_id ) {
		if ( ! $post = get_post( $post_id ) ) {
			return $open;
		}

		$status     = get_post_status( $post );
		$status_obj = get_post_status_object( $status );

		if ( ! $status_obj->public && ! $status_obj->private ) {
			$open = false;
		}

		return $open;
	}

	/**
	 * Prepends 'Pending Review:' to a post title.
	 *
	 * @param string $title   The post title.
	 * @param int    $post_id The post ID.
	 * @return string Filtered post title.
	 */
	public function prepend_pending_notice( $title, $post_id = null ) {
		if ( $post_id && ( ! is_admin() || is_admin() && wp_doing_ajax() ) && 'pending' === get_post_status( $post_id ) ) {
			/* translators: %s: Post title */
			$title = sprintf( __( 'Pending Review: %s', 'o2' ), $title );
		}

		return $title;
	}

	/**
	 * Removes content related menu items which are not accessible because of
	 * the missing 'read' capability.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public function remove_non_accessible_menu_items( $wp_admin_bar ) {
		if ( current_user_can( 'read' ) ) {
			return;
		}

		$wp_admin_bar->remove_node( 'new-content' );
		$wp_admin_bar->remove_node( 'comments' );
		$wp_admin_bar->remove_node( 'edit' );
	}

	/**
	 * Sets post status of a new post to 'pending'.
	 *
	 * @param object $post Post data.
	 * @return object Filtered post data.
	 */
	public function save_new_post_as_pending( $post ) {
		if ( $this->user_can_publish( $post->post_author ) ) {
			return $post;
		}

		$post->post_status = 'pending';

		return $post;
	}

	/**
	 * Replaces the button label "Post" with "Submit for review" if current user
	 * can't publish a post with post status 'publish'.
	 *
	 * @param string $translation  Translated text.
	 * @param string $text         Text to translate.
	 * @param string $context      Context information for the translators.
	 * @param string $domain       Text domain. Unique identifier for retrieving translated strings.
	 * @return string Filtered translated text.
	 */
	public function replace_post_button_label( $translation, $text, $context, $domain ) {
		if ( 'o2' !== $domain || 'Verb, to post' !== $context || 'Post' !== $text ) {
			return $translation;
		}

		remove_filter( 'gettext_with_context', [ $this, 'replace_post_button_label' ] );

		if ( $this->user_can_publish() ) {
			return $translation;
		}

		return __( 'Submit for review', 'o2' );
	}

	/**
	 * Whether a user can publish a post with post status 'publish'
	 * .
	 * @param int $user_id Optional. The user ID.
	 * @return bool True when user can, false if not.
	 */
	public function user_can_publish( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		if ( is_user_member_of_blog( $user->ID ) ) {
			return true;
		}

		$mod_keys = trim( get_option( 'moderation_keys', '' ) );
		if ( ! empty( $mod_keys ) ) {
			$mod_keys = explode( "\n", $mod_keys );
			$mod_keys = array_map( 'trim', $mod_keys );
			$mod_keys = array_filter( $mod_keys );
			$mod_keys = array_map( function( $mod_key ) {
				return preg_quote( $mod_key, '#' );
			}, $mod_keys);

			$pattern = '#(' . implode( '|', $mod_keys ) . ')#i';

			if ( preg_match( $pattern, $user->user_email ) ) {
				return false;
			}
		}

		$has_published_post = (bool) get_posts( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'author'      => $user_id,
			'numberposts' => 1,
		] );
		if ( ! $has_published_post ) {
			return false;
		}

		return apply_filters( 'wporg_o2_user_can_publish', true, $user );
	}

	/**
	 * Adds post capabilities to current user.
	 *
	 * @param array   $allcaps An array of all the user's capabilities.
	 * @param array   $caps    Actual capabilities for meta capability.
	 * @param array   $args    Optional parameters passed to has_cap(), typically object ID.
	 * @param WP_User $user    The user object.
	 * @return array Array of all the user's capabilities.
	 */
	public function add_post_capabilities( $allcaps, $caps, $args, $user ) {
		if ( ! is_user_logged_in() || in_array( 'publish_posts', $allcaps, true ) || is_user_member_of_blog( $user->ID ) ) {
			return $allcaps;
		}

		$allcaps['publish_posts'] = true;
		$allcaps['edit_posts'] = true;
		$allcaps['edit_published_posts'] = true;

		return $allcaps;
	}
}

add_action( 'o2_loaded', [ new Plugin(), 'init' ] );
