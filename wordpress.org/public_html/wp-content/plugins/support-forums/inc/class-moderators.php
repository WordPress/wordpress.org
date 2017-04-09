<?php

namespace WordPressdotorg\Forums;

class Moderators {

	const ARCHIVED       = 'archived';
	const ARCHIVED_META  = '_wporg_bbp_unarchived_post_status';
	const MODERATOR_META = '_wporg_bbp_moderator';
	const DEFAULT_STATUS = 'publish';
	const VIEWS          = array( 'archived', 'pending', 'spam' );

	public function __construct() {
		// Moderator-specific views.
		add_action( 'bbp_register_views', array( $this, 'register_views' ), 1 );

		// Scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Append 'view=all' to forum, topic, and reply URLs in moderator views.
		add_filter( 'bbp_get_forum_permalink',          array( $this, 'add_view_all' ) );
		add_filter( 'bbp_get_topic_permalink',          array( $this, 'add_view_all' ) );
		add_filter( 'bbp_get_reply_url',                array( $this, 'add_view_all' ) );

		// Archived post status.
		add_action( 'bbp_register_post_statuses',       array( $this, 'register_post_statuses' ) );
		add_action( 'bbp_get_request',                  array( $this, 'archive_handler' ) );
		add_filter( 'bbp_after_has_topics_parse_args',  array( $this, 'add_post_status_to_query' ) );
		add_filter( 'bbp_after_has_replies_parse_args', array( $this, 'add_post_status_to_query' ) );
		add_filter( 'bbp_topic_admin_links',            array( $this, 'admin_links' ), 10, 2 );
		add_filter( 'bbp_reply_admin_links',            array( $this, 'admin_links' ), 10, 2 );

		// Add valid topic and reply actions.
		add_filter( 'bbp_get_toggle_topic_actions',     array( $this, 'get_topic_actions' ) );
		add_filter( 'bbp_get_toggle_reply_actions',     array( $this, 'get_reply_actions' ) );

		// Handle topic and reply actions.
		add_filter( 'bbp_toggle_topic',                 array( $this, 'handle_topic_actions' ), 10, 3 );
		add_filter( 'bbp_toggle_reply',                 array( $this, 'handle_reply_actions' ), 10, 3 );

		// Convert toggle links to explicit actions.
		add_filter( 'bbp_get_topic_spam_link',          array( $this, 'convert_toggles_to_actions' ), 10, 3 );
		add_filter( 'bbp_get_topic_approve_link',       array( $this, 'convert_toggles_to_actions' ), 10, 3 );
		add_filter( 'bbp_get_reply_spam_link',          array( $this, 'convert_toggles_to_actions' ), 10, 3 );
		add_filter( 'bbp_get_reply_approve_link',       array( $this, 'convert_toggles_to_actions' ), 10, 3 );

		// Store moderator's username on approve/unapprove actions.
		add_action( 'bbp_approved_topic',               array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_approved_reply',               array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_unapproved_topic',             array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_unapproved_reply',             array( $this, 'store_moderator_username' ) );
	}

	/**
	 * Registers views.
	 *
	 * Note: Be sure to update class constant VIEWS when adding/removing views.
	 */
	public function register_views() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		bbp_register_view(
			'spam',
			__( 'Spam', 'wporg-forums' ),
			array(
				'meta_key'      => null,
				'post_type'     => array(
					'topic',
					'reply',
				),
				'post_status'   => 'spam',
				'show_stickies' => false,
				'orderby'       => 'ID',
			)
		);

		bbp_register_view(
			'pending',
			__( 'Pending', 'wporg-forums' ),
			array(
				'meta_key'      => null,
				'post_type'     => array(
					'topic',
					'reply',
				),
				'post_status'   => 'pending',
				'show_stickies' => false,
				'orderby'       => 'ID',
			)
		);

		bbp_register_view(
			'archived',
			__( 'Archived', 'wporg-forums' ),
			array(
				'meta_key'      => null,
				'post_type'     => array(
					'topic',
					'reply',
				),
				'post_status'   => 'archived',
				'show_stickies' => false,
				'orderby'       => 'ID',
			)
		);
	}

	public function enqueue_styles() {
		if ( current_user_can( 'moderate' ) ) {
			wp_enqueue_style( 'support-forums-moderators', plugins_url( 'css/styles-moderators.css', __DIR__ ) );
		}
	}

	/**
	 * Append 'view=all' to forum, topic, and reply URLs in moderator views.
	 *
	 * @param string $url Forum, topic, or reply URL.
	 * @return string Filtered URL.
	 */
	public function add_view_all( $url ) {
		if ( bbp_is_single_view() && in_array( bbp_get_view_id(), self::VIEWS ) ) {
			$url = add_query_arg( 'view', 'all', $url );
		}

		return $url;
	}

	public function register_post_statuses() {

		/**
		 * Add archived post status.
		 *
		 * Archived posts are intended for moderator use in determining a pattern
		 * of behavior. They are not duplicates, or spam, but should not be moved
		 * to the trash because they record user activity.
		 */
		register_post_status(
			self::ARCHIVED,
			array(
				'label'                     => _x( 'Archived', 'post', 'wporg-forums' ),
				'label_count'               => _nx_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'post', 'wporg-forums' ),
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_status_list' => true,
				'show_in_admin_add_list'    => false,
			)
		);

	}

	public function archive_handler( $action = '' ) {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}
		$user_id = get_current_user_id();

		if ( ! in_array( $action, $this->get_valid_actions() ) ) {
			return;
		}

		if ( empty( $_GET['post_id'] ) ) {
			return;
		}

		$post = get_post( absint( $_GET['post_id'] ) );
		if ( ! $post ) {
			return false;
		}

		// Check for empty post id.
		if ( ! $post ) {
			bbp_add_error( 'wporg_bbp_archive_post_id', __( '<strong>ERROR</strong>: No post was found! Which post are you archiving?', 'wporg-forums' ) );

		// Check for current user.
		} elseif ( empty( $user_id ) ) {
			bbp_add_error( 'wporg_bbp_archive_logged_in', __( '<strong>ERROR</strong>: You must be logged in to do this!', 'wporg-forums' ) );

		// Check nonce.
		} elseif ( ! bbp_verify_nonce_request( 'toggle-post-archive_' . $user_id . '_' . $post->ID ) ) {
			bbp_add_error( 'wporg_bbp_archive_nonce', __( '<strong>ERROR</strong>: Are you sure you wanted to do that?', 'wporg-forums' ) );

		}

		if ( bbp_has_errors() ) {
			return;
		}

		$is_archived = $this->is_post_archived( $post->ID );
		$success = false;
		$add_view_all = false;

		if ( true == $is_archived && 'wporg_bbp_unarchive_post' === $action ) {
			$success = $this->unarchive_post( $post->ID );
		} elseif ( false == $is_archived && 'wporg_bbp_archive_post' === $action ) {
			$success = $this->archive_post( $post->ID );
			$add_view_all = true;
		}

		$permalink = $this->get_permalink( $post->ID );
		if ( $add_view_all ) {
			$permalink = bbp_add_view_all( $permalink, true );
		}

		if ( true === $success ) {
			$redirect = $this->get_permalink( $post->ID );
			bbp_redirect( $redirect );
		} elseif ( true === $is_archived && 'wporg_bbp_archive_post' === $action ) {
			bbp_add_error( 'wporg_bbp_archive_post', __( '<strong>ERROR</strong>: There was a problem archiving that post!', 'wporg-forums' ) );
		} elseif ( false === $is_archived && 'wporg_bbp_unarchive_post' === $action ) {
			bbp_add_error( 'wporg_bbp_unarchive_post', __( '<strong>ERROR</strong>: There was a problem unarchiving that post!', 'wporg-forums' ) );
		}
	}

	public function add_post_status_to_query( $args = array() ) {
		if ( bbp_get_view_all() ) {
			$post_stati = explode( ',', $args['post_status'] );
			$post_stati[] = self::ARCHIVED;
			$args['post_status'] = implode( ',', $post_stati );
		}
		return $args;
	}

	public function admin_links( $r, $post_id ) {
		$r['archive'] = $this->get_archive_link( array( 'post_id' => $post_id ) );
		return $r;
	}

	public function get_archive_link( $args = array() ) {
		if ( ! current_user_can( 'moderate' ) ) {
			return false;
		}
		$user_id = get_current_user_id();

		$r = bbp_parse_args( $args, array(
			'post_id' => get_the_ID(),
			'archive' => esc_html__( 'Archive', 'wporg-forums' ),
			'unarchive' => esc_html__( 'Unarchive', 'wporg-forums' ),
		), 'get_post_archive_link' );
		if ( empty( $r['post_id'] ) ) {
			return false;
		}
		$post_id = $r['post_id'];

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( $this->is_post_archived( $post->ID ) ) {
			$text = $r['unarchive'];
			$query_args = array( 'action' => 'wporg_bbp_unarchive_post', 'post_id' => $post->ID );
		} else {
			$text = $r['archive'];
			$query_args = array( 'action' => 'wporg_bbp_archive_post', 'post_id' => $post->ID );
		}

		$permalink = $this->get_permalink( $post->ID );

		$url = esc_url( wp_nonce_url( add_query_arg( $query_args, $permalink ), 'toggle-post-archive_' . $user_id . '_' . $post->ID ) );
		return sprintf( "<a href='%s'>%s</a>", $url, esc_html( $text ) );
	}

	public function is_post_archived( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		return $post->post_status == self::ARCHIVED;
	}

	public function archive_post( $post_id ) {
		if ( empty( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( ! $this->is_post_archived( $post->ID ) ) {
			$post_id = wp_update_post( array(
				'ID'          => $post->ID,
				'post_status' => self::ARCHIVED,
			), true );
			if ( $post_id ) {
				update_post_meta( $post->ID, self::ARCHIVED_META, $post->post_status );
				update_post_meta( $post->ID, self::MODERATOR_META, wp_get_current_user()->user_nicename );

				if ( bbp_is_reply( $post->ID ) ) {
					bbp_increase_topic_reply_count_hidden( bbp_get_reply_topic_id( $post->ID ) );
				}

				return true;
			}
		}

		return false;
	}

	public function unarchive_post( $post_id ) {
		if ( empty( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( $this->is_post_archived( $post->ID ) ) {
			$post_status = get_post_meta( $post->ID, self::ARCHIVED_META, true );
			if ( ! $post_status ) {
				$post_status = self::DEFAULT_STATUS;
			}
			$post_id = wp_update_post( array(
				'ID'          => $post->ID,
				'post_status' => $post_status,
			) );
			if ( $post_id ) {
				delete_post_meta( $post->ID, self::ARCHIVED_META );
				update_post_meta( $post->ID, self::MODERATOR_META, wp_get_current_user()->user_nicename );

				if ( bbp_is_reply( $post->ID ) ) {
					bbp_decrease_topic_reply_count_hidden( bbp_get_reply_topic_id( $post->ID ) );
				}

				return true;
			}
		}

		return false;
	}

	public function get_permalink( $post_id ) {
		if ( empty( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		switch ( $post->post_type ) {
			case 'topic' :
				$permalink = bbp_get_topic_permalink( $post->ID );
				break;
			case 'reply' :
				$permalink = bbp_get_topic_permalink( bbp_get_reply_topic_id( $post->ID ) );
				break;
			case 'post' :
			default :
				$permalink = get_permalink( $post->ID );
		}

		return $permalink;
	}

	public function get_valid_actions() {
		return array(
			'wporg_bbp_archive_post',
			'wporg_bbp_unarchive_post',
		);
	}

	/**
	 * Add Spam, Unspam, Unapprove, Approve to the list of valid topic actions.
	 *
	 * @param array $actions List of topic actions.
	 * @return array Filtered list of actions.
	 */
	public function get_topic_actions( $actions ) {
		$actions = array_merge( $actions, array(
			'wporg_bbp_spam_topic',
			'wporg_bbp_unspam_topic',
			'wporg_bbp_unapprove_topic',
			'wporg_bbp_approve_topic',
		) );

		return $actions;
	}

	/**
	 * Add Spam, Unspam, Unapprove, Approve to the list of valid reply actions.
	 *
	 * @param array $actions List of reply actions.
	 * @return array Filtered list of actions.
	 */
	public function get_reply_actions( $actions ) {
		$actions = array_merge( $actions, array(
			'wporg_bbp_spam_reply',
			'wporg_bbp_unspam_reply',
			'wporg_bbp_unapprove_reply',
			'wporg_bbp_approve_reply',
		) );

		return $actions;
	}

	/**
	 * Handle Spam, Unspam, Unapprove, Approve topic actions.
	 *
	 * By default, bbPress treats them as toggles, which may cause conflicts if
	 * the same action is performed twice by different moderators.
	 *
	 * @param array $retval {
	 *    @type int    $status      Result of the action.
	 *    @type string $message     Message displayed in case of an error.
	 *    @type string $redirect_to URL to redirect to.
	 *    @type bool   $view_all    Whether to append 'view=all' to the URL.
	 * }
	 * @param array  $r    Parsed arguments.
	 * @param array  $args Raw arguments.
	 * @return array
	 */
	public function handle_topic_actions( $retval, $r, $args ) {
		$nonce_suffix = bbp_get_topic_post_type() . '_' . (int) $r['id'];

		switch ( $r['action'] ) {
			case 'wporg_bbp_spam_topic':
				check_ajax_referer( "spam-{$nonce_suffix}" );

				if ( ! bbp_is_topic_spam( $r['id'] ) ) {
					$retval['status']   = bbp_spam_topic( $r['id'] );
					$retval['message']  = __( '<strong>ERROR</strong>: There was a problem marking the topic as spam.', 'wporg-forums' );
				}
				$retval['view_all'] = true;

				break;

			case 'wporg_bbp_unspam_topic':
				check_ajax_referer( "spam-{$nonce_suffix}" );

				if ( bbp_is_topic_spam( $r['id'] ) ) {
					$retval['status']   = bbp_unspam_topic( $r['id'] );
					$retval['message']  = __( '<strong>ERROR</strong>: There was a problem unmarking the topic as spam.', 'wporg-forums' );
				}
				$retval['view_all'] = false;

				break;

			case 'wporg_bbp_unapprove_topic':
				check_ajax_referer( "approve-{$nonce_suffix}" );

				if ( ! bbp_is_topic_pending( $r['id'] ) ) {
					$retval['status']   = bbp_unapprove_topic( $r['id'] );
					$retval['message']  = __( '<strong>ERROR</strong>: There was a problem unapproving the topic.', 'wporg-forums' );
				}
				$retval['view_all'] = true;

				break;

			case 'wporg_bbp_approve_topic':
				check_ajax_referer( "approve-{$nonce_suffix}" );

				if ( bbp_is_topic_pending( $r['id'] ) ) {
					$retval['status']   = bbp_approve_topic( $r['id'] );
					$retval['message']  = __( '<strong>ERROR</strong>: There was a problem approving the topic.', 'wporg-forums' );
				}
				$retval['view_all'] = false;

				break;
		}

		// Add 'view=all' if needed
		if ( ! empty( $retval['view_all'] ) ) {
			$retval['redirect_to'] = bbp_add_view_all( $retval['redirect_to'], true );
		}

		return $retval;
	}

	/**
	 * Handle Spam, Unspam, Unapprove, Approve reply actions.
	 *
	 * By default, bbPress treats them as toggles, which may cause conflicts if
	 * the same action is performed twice by different moderators.
	 *
	 * @param array $retval {
	 *    @type int    $status      Result of the action.
	 *    @type string $message     Message displayed in case of an error.
	 *    @type string $redirect_to URL to redirect to.
	 *    @type bool   $view_all    Whether to append 'view=all' to the URL.
	 * }
	 * @param array  $r    Parsed arguments.
	 * @param array  $args Raw arguments.
	 * @return array
	 */
	public function handle_reply_actions( $retval, $r, $args ) {
		$nonce_suffix = bbp_get_reply_post_type() . '_' . (int) $r['id'];

		switch ( $r['action'] ) {
			case 'wporg_bbp_spam_reply':
				check_ajax_referer( "spam-{$nonce_suffix}" );

				if ( ! bbp_is_reply_spam( $r['id'] ) ) {
					$retval['status']   = bbp_spam_reply( $r['id'] );
					$retval['message']  = __( '<strong>ERROR</strong>: There was a problem marking the reply as spam.', 'wporg-forums' );
				}
				$retval['view_all'] = true;

				break;

			case 'wporg_bbp_unspam_reply':
				check_ajax_referer( "spam-{$nonce_suffix}" );

				if ( bbp_is_reply_spam( $r['id'] ) ) {
					$retval['status']   = bbp_unspam_reply( $r['id'] );
					$retval['message']  = __( '<strong>ERROR</strong>: There was a problem unmarking the reply as spam.', 'wporg-forums' );
				}
				$retval['view_all'] = false;

				break;

			case 'wporg_bbp_unapprove_reply':
				check_ajax_referer( "approve-{$nonce_suffix}" );

				if ( ! bbp_is_reply_pending( $r['id'] ) ) {
					$retval['status']   = bbp_unapprove_reply( $r['id'] );
					$retval['message']  = __( '<strong>ERROR</strong>: There was a problem unapproving the reply.', 'wporg-forums' );
				}
				$retval['view_all'] = true;

				break;

			case 'wporg_bbp_approve_reply':
				check_ajax_referer( "approve-{$nonce_suffix}" );

				if ( bbp_is_reply_pending( $r['id'] ) ) {
					$retval['status']   = bbp_approve_reply( $r['id'] );
					$retval['message']  = __( '<strong>ERROR</strong>: There was a problem approving the reply.', 'wporg-forums' );
				}
				$retval['view_all'] = false;

				break;
		}

		// Add 'view=all' if needed
		if ( ! empty( $retval['view_all'] ) ) {
			$retval['redirect_to'] = bbp_add_view_all( $retval['redirect_to'], true );
		}

		return $retval;
	}

	/**
	 * Convert Spam/Unspam, Unapprove/Approve toggle links to explicit actions.
	 *
	 * @param string $link Link HTML.
	 * @param array  $r    Parsed arguments.
	 * @param array  $args Raw arguments.
	 * @return string Filtered link.
	 */
	public function convert_toggles_to_actions( $link, $r, $args ) {
		if ( false !== strpos( $link, 'bbp_toggle_topic_spam' ) ) {
			$action = ( bbp_is_topic_spam( $r['id'] ) ) ? 'wporg_bbp_unspam_topic' : 'wporg_bbp_spam_topic';
			$link   = str_replace( 'bbp_toggle_topic_spam', $action, $link );

		} elseif ( false !== strpos( $link, 'bbp_toggle_topic_approve' ) ) {
			$action = ( bbp_is_topic_pending( $r['id'] ) ) ? 'wporg_bbp_approve_topic' : 'wporg_bbp_unapprove_topic';
			$link   = str_replace( 'bbp_toggle_topic_approve', $action, $link );

		} elseif ( false !== strpos( $link, 'bbp_toggle_reply_spam' ) ) {
			$action = ( bbp_is_reply_spam( $r['id'] ) ) ? 'wporg_bbp_unspam_reply' : 'wporg_bbp_spam_reply';
			$link   = str_replace( 'bbp_toggle_reply_spam', $action, $link );

		} elseif ( false !== strpos( $link, 'bbp_toggle_reply_approve' ) ) {
			$action = ( bbp_is_reply_pending( $r['id'] ) ) ? 'wporg_bbp_approve_reply' : 'wporg_bbp_unapprove_reply';
			$link   = str_replace( 'bbp_toggle_reply_approve', $action, $link );

		}

		return $link;
	}

	/**
	 * Store moderator's username on approve/unapprove actions.
	 *
	 * @param int $post_id Post ID.
	 */
	public function store_moderator_username( $post_id ) {
		update_post_meta( $post_id, self::MODERATOR_META, wp_get_current_user()->user_login );
	}
}
