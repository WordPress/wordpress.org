<?php

namespace WordPressdotorg\Forums\User_Moderation;

class Plugin {

	/**
	 * @todo Flag view/action on user profile.
	 * @todo AJAXify user flagging action.
	 * @todo Audit trail/reason for moderating user.
	 * @todo Blocking/deleting a user completely.
	 */

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	// Back-compat user option name.
	const USER_META = 'is_bozo';

	/**
	 * Always return the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiate a new Plugin object.
	 */
	private function __construct() {
		add_action( 'bbp_init', array( $this, 'bbp_init' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function bbp_init() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Add views and actions for flagged users.
		if ( $this->is_user_flagged( $user_id ) ) {
			// Set moderated status on post insert.
			add_filter( 'bbp_new_forum_pre_insert',  array( $this, 'pre_insert' ) );
			add_filter( 'bbp_edit_forum_pre_insert', array( $this, 'pre_insert' ) );
			add_filter( 'bbp_new_topic_pre_insert',  array( $this, 'pre_insert' ) );
			add_filter( 'bbp_edit_topic_pre_insert', array( $this, 'pre_insert' ) );
			add_filter( 'bbp_new_reply_pre_insert',  array( $this, 'pre_insert' ) );
			add_filter( 'bbp_edit_reply_pre_insert', array( $this, 'pre_insert' ) );

			// Alter queries for moderated users.
			add_filter( 'posts_where', array( $this, 'posts_where' ) );
		}

		// Add views and actions for moderators.
		if ( user_can( $user_id, 'moderate' ) ) {
			// Provide user moderation actions.
			add_action( 'bbp_theme_after_topic_author_details', array( $this, 'add_topic_author_action' ) );
			add_action( 'bbp_theme_after_reply_author_details', array( $this, 'add_reply_author_action' ) );

			// Set user moderated status.
			add_action( 'bbp_get_request', array( $this, 'user_flag_handler' ) );

			// Provide moderators with user flag status.
			add_filter( 'bbp_get_topic_class', array( $this, 'add_topic_author_class' ) );
			add_filter( 'bbp_get_reply_class', array( $this, 'add_reply_author_class' ) );

			// Scripts.
			add_action( 'bbp_ajax_wporg_bbp_flag_user',   array( $this, 'ajax_flag_user' ) );
		}
	}

	/**
	 * Change the post status if the user is marked for moderation.
	 */
	public function pre_insert( $args = array() ) {
		$args['post_status'] = bbp_get_pending_status_id();
		return $args;
	}

	/**
	 * Adjust the query for moderated users.
	 */
	public function posts_where( $where ) {
		global $wpdb;
		if ( bbp_is_single_forum() || bbp_is_single_topic() ) {
			if (
				strpos( $where, $wpdb->prepare( "$wpdb->posts.post_type = %s", bbp_get_topic_post_type() ) ) !== false
			||
				strpos( $where, $wpdb->prepare( "$wpdb->posts.post_type = %s", bbp_get_reply_post_type() ) ) !== false
			) {
				$original = $wpdb->prepare( "$wpdb->posts.post_status = %s OR ", bbp_get_public_status_id() );
				$replacement = $wpdb->prepare( " ( $wpdb->posts.post_status = '%s' AND $wpdb->posts.post_author = '%d' ) OR ", bbp_get_pending_status_id(), get_current_user_id() );
				$where = str_replace( $original, $original . $replacement, $where );
			}
		}
		return $where;
	}

	/**
	 * Display link under topic author for moderator action.
	 */
	public function add_topic_author_action( $args = array() ) {
		$link = $this->get_user_flag_link( array(
			'user_id' => bbp_get_topic_author_id( bbp_get_topic_id() ),
			'post_id' => bbp_get_topic_id(),
		) );
		if ( false !== $link ) {
			echo $link;
		}
	}

	/**
	 * Display link under reply author for moderator action.
	 */
	public function add_reply_author_action( $args = array() ) {
		$link = $this->get_user_flag_link( array(
			'user_id' => bbp_get_reply_author_id( bbp_get_reply_id() ),
			'post_id' => bbp_get_reply_id(),
		) );
		if ( false !== $link ) {
			echo $link;
		}
	}

	/**
	 * Add class to topics by flagged user.
	 */
	public function add_topic_author_class( $classes = array() ) {
		if ( $this->is_user_flagged( bbp_get_topic_author_id( bbp_get_topic_id() ) ) ) {
			$classes[] = 'user-is-flagged';
		}
		return $classes;
	}

	/**
	 * Add class to replies by flagged user.
	 */
	public function add_reply_author_class( $classes = array() ) {
		if ( $this->is_user_flagged( bbp_get_reply_author_id( bbp_get_reply_id() ) ) ) {
			$classes[] = 'user-is-flagged';
		}
		return $classes;
	}

	/**
	 * Handle the flagging/unflagging of a user.
	 */
	public function user_flag_handler( $action = '' ) {
		if ( empty( $_GET['user_id'] ) ) {
			return;
		}

		// Bail if actions aren't meant for this function
		if ( ! in_array( $action, $this->get_valid_actions() ) ) {
			return;
		}

		// What action is taking place?
		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		$post = get_post( $post_id );
		if ( ! $post ) {
			bbp_add_error( 'wporg_bbp_flag_post_id', __( '<strong>ERROR</strong>: No post was found! Which topic or reply are you marking for moderation?', 'wporg-forums' ) );

		// Check that user id matches post author
		} elseif ( $post->post_author != intval( $_GET['user_id'] ) ) {
			bbp_add_error( 'wporg_bbp_flag_post_user', __( '<strong>ERROR</strong>: That author does not match the flagged post.', 'wporg-forums' ) );

		// Check nonce
		} elseif ( ! bbp_verify_nonce_request( 'toggle-flag_' . $post->post_author . '_' . $post->ID ) ) {
			bbp_add_error( 'wporg_bbp_flag_nonce', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'wporg-forums' ) );

		// Check current user's ability to moderate
		} elseif ( ! current_user_can( 'moderate' ) ) {
			bbp_add_error( 'wporg_bbp_flag_permissions', __( '<strong>ERROR</strong>: You don\'t have permission to moderate that user!', 'wporg-forums' ) );
		}

		// Bail if errors
		if ( bbp_has_errors() ) {
			return;
		}

		$user_id = $post->post_author;
		$is_flagged = $this->is_user_flagged( $user_id );
		$success = false;

		if ( true === $is_flagged && 'wporg_bbp_unflag_user' === $action ) {
			$success = $this->unflag_user( $user_id );
		} elseif ( false === $is_flagged && 'wporg_bbp_flag_user' === $action ) {
			$success = $this->flag_user( $user_id );
		}

		// Success!
		if ( true === $success ) {
			$redirect = bbp_get_topic_permalink( $post_id );
			bbp_redirect( $redirect );
		} elseif ( true === $is_flagged && 'bbp_flag_user' === $action ) {
			bbp_add_error( 'wporg_bbp_flag_user', __( '<strong>ERROR</strong>: There was a problem flagging that user!', 'wporg-forums' ) );
		} elseif ( false === $is_flagged && 'bbp_unflag_user' == $action ) {
			bbp_add_error( 'wporg_bbp_flag_unuser', __( '<strong>ERROR</strong>: There was a problem unflagging that user!', 'wporg-forums' ) );
		}
	}

	public function is_user_flagged( $user_id ) {
		$retval = false;

		$is_flagged = get_user_meta( $user_id, self::USER_META, true );
		if ( $is_flagged ) {
			$retval = true;
		}

		return apply_filters( 'wporg_bbp_is_user_flagged', $retval, $user_id );
	}

	public function flag_user( $user_id ) {
		if ( empty( $user_id ) ) {
			return false;
		}

		if ( ! $this->is_user_flagged( $user_id ) ) {
			update_user_meta( $user_id, self::USER_META, true );
		}
		do_action( 'wporg_bbp_flag_user', $user_id );

		return true;
	}

	public function unflag_user( $user_id ) {
		if ( empty( $user_id ) ) {
			return false;
		}

		if ( $this->is_user_flagged( $user_id ) ) {
			delete_user_meta( $user_id, self::USER_META );
		}
		do_action( 'wporg_bbp_unflag_user', $user_id );

		return true;
	}

	public function get_user_flag_link( $args = array() ) {
		if ( ! current_user_can( 'moderate' ) ) {
			return false;
		}

		$r = bbp_parse_args( $args, array(
			'user_id' => bbp_get_displayed_user_id(),
			'post_id' => 0,
			'flag'    => esc_html__( 'Flag Author', 'wporg-forums' ),
			'unflag'  => esc_html__( 'Unflag Author', 'wporg-forums' ),
		), 'get_user_flag_link' );

		if ( empty( $r['user_id'] ) ) {
			return false;
		}
		$user_id = $r['user_id'];
		$post_id = $r['post_id'];

		// Don't even display this for moderators
		if ( user_can( $user_id, 'moderate' ) ) {
			return false;
		}

		if ( $this->is_user_flagged( $user_id ) ) {
			$text = $r['unflag'];
			$query_args = array( 'action' => 'wporg_bbp_unflag_user', 'user_id' => $user_id );
		} else {
			$text = $r['flag'];
			$query_args = array( 'action' => 'wporg_bbp_flag_user', 'user_id' => $user_id );
		}

		$permalink = bbp_get_topic_permalink( $post_id );

		$url = esc_url( wp_nonce_url( add_query_arg( $query_args, $permalink ), 'toggle-flag_' . $user_id . '_' . $post_id ) );
		return sprintf( "<div class='wporg-bbp-user-flag'><a href='%s'>%s</a></div>",
			$url,
			esc_html( $text ) );
	}

	public function get_valid_actions() {
		return array(
			'wporg_bbp_flag_user',
			'wporg_bbp_unflag_user',
		);
	}
}
