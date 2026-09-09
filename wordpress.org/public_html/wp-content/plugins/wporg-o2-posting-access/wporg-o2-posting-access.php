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
		if ( ! defined( 'O2__PLUGIN_LOADED' ) ) {
			return;
		}

		add_filter( 'user_has_cap', [ $this, 'add_post_capabilities' ], 10, 4 );
		add_action( 'registered_post_type', [ $this, 'restrict_rest_queries' ] );

		foreach ( get_post_types() as $post_type ) {
			$this->restrict_rest_queries( $post_type );
		}

		add_action( 'admin_bar_menu', [ $this, 'remove_non_accessible_menu_items' ], 100 );

		if ( apply_filters( 'wporg_o2_enable_pending_for_unknown_users', true ) ) {
			if ( ! is_network_admin() && ! is_user_admin() ) {
				add_action( 'admin_bar_menu', [ $this, 'add_pending_posts_count_to_admin_bar' ], 55 );
				add_action( 'wp_enqueue_scripts', [ $this, 'add_pending_posts_icon_to_admin_bar' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'add_pending_posts_icon_to_admin_bar' ] );
			}

			add_filter( 'gettext_with_context', [ $this, 'replace_post_button_label' ], 10, 4 );
			add_filter( 'o2_create_post', [ $this, 'save_new_post_as_pending' ] );
			add_filter( 'wp_insert_post_data', array( $this, 'enforce_pending_status' ) );
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
	 * Adds pending posts count before pending comments count.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public function add_pending_posts_count_to_admin_bar( $wp_admin_bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$awaiting_review = wp_count_posts();
		$awaiting_review = $awaiting_review->pending;
		$awaiting_text   = sprintf( _n( '%s post awaiting review', '%s posts awaiting review', $awaiting_review, 'o2' ), number_format_i18n( $awaiting_review ) );

		$icon   = '<span class="ab-icon"></span>';
		$title  = '<span class="ab-label count-' . $awaiting_review . '" aria-hidden="true">' . number_format_i18n( $awaiting_review ) . '</span>';
		$title .= '<span class="screen-reader-text">' . $awaiting_text . '</span>';

		$wp_admin_bar->add_menu(
			array(
				'id'    => 'pending-posts',
				'title' => $icon . $title,
				'href'  => admin_url( 'edit.php' ),
			)
		);
	}

	/**
	 * Adds icon for the pending posts count.
	 */
	public function add_pending_posts_icon_to_admin_bar() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_inline_style( 'admin-bar', '
			#wpadminbar #wp-admin-bar-pending-posts .ab-icon:before {
				content: "\f109";
				top: 2px;
			}

			#wpadminbar #wp-admin-bar-pending-posts .count-0 {
				opacity: .5;
			}
		' );
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
	 * Applies the 'pending' status to submissions from users who can't publish.
	 *
	 * The capabilities granted in add_post_capabilities() are primitive capabilities
	 * that every write path honors, so the review step can't live on o2's front end
	 * 'o2_create_post' filter alone -- it has to apply wherever a post is created.
	 * Only posts a logged-in user is authoring for themselves are affected, so
	 * editorial and programmatic inserts are left alone.
	 *
	 * @param array $data An array of slashed, sanitized, and processed post data.
	 * @return array Filtered post data.
	 */
	public function enforce_pending_status( $data ) {
		$author = (int) ( $data['post_author'] ?? 0 );

		if ( ! $author || get_current_user_id() !== $author ) {
			return $data;
		}

		$exempt_statuses = array( 'draft', 'pending', 'auto-draft', 'trash', 'inherit' );
		if ( in_array( $data['post_status'], $exempt_statuses, true ) ) {
			return $data;
		}

		if ( $this->user_can_publish( $author ) ) {
			return $data;
		}

		$data['post_status'] = 'pending';

		return $data;
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
	 * Whether a user can publish a post with post status 'publish'.
	 *
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

		if ( is_user_member_of_blog( $user->ID ) || is_super_admin( $user->ID ) ) {
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
		if ( empty( $user->ID ) || ! empty( $allcaps['publish_posts'] ) || is_user_member_of_blog( $user->ID ) ) {
			return $allcaps;
		}

		$allcaps['publish_posts'] = true;
		$allcaps['edit_posts'] = true;
		$allcaps['edit_published_posts'] = true;

		return $allcaps;
	}

	/**
	 * Restricts REST collection queries for a post type.
	 *
	 * @param string $post_type Post type key.
	 * @return void
	 */
	public function restrict_rest_queries( $post_type ) {
		add_filter( "rest_{$post_type}_query", [ $this, 'restrict_non_public_queries' ] );
	}

	/**
	 * Restricts REST queries for non-public post statuses to the user's own posts.
	 *
	 * A query mixing public and non-public statuses is scoped whole, because one
	 * set of WP_Query arguments cannot express "public by anyone, non-public by me".
	 *
	 * @param array $args An array of arguments for WP_Query.
	 * @return array Filtered WP_Query arguments.
	 */
	public function restrict_non_public_queries( $args ) {
		$user_id = get_current_user_id();

		if ( ! $user_id || is_user_member_of_blog( $user_id ) || is_super_admin( $user_id ) ) {
			return $args;
		}

		// 'inherit' is the attachments route's default status, so it is readable without the grant.
		$public_statuses = array_merge( get_post_stati( [ 'public' => true ] ), [ 'inherit' ] );
		if ( ! array_diff( (array) ( $args['post_status'] ?? [] ), $public_statuses ) ) {
			return $args;
		}

		$args['author']         = $user_id;
		$args['author__in']     = [];
		$args['author__not_in'] = [];

		return $args;
	}
}

add_action( 'plugins_loaded', [ new Plugin(), 'init' ] );
