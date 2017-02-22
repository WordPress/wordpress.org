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

		// Archived post status.
		add_action( 'bbp_register_post_statuses',       array( $this, 'register_post_statuses' ) );
		add_action( 'bbp_get_request',                  array( $this, 'archive_handler' ) );
		add_filter( 'bbp_after_has_topics_parse_args',  array( $this, 'add_post_status_to_query' ) );
		add_filter( 'bbp_after_has_replies_parse_args', array( $this, 'add_post_status_to_query' ) );
		add_filter( 'bbp_topic_admin_links',            array( $this, 'admin_links' ), 10, 2 );
		add_filter( 'bbp_reply_admin_links',            array( $this, 'admin_links' ), 10, 2 );
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
				update_post_meta( $post->ID, self::MODERATOR_META, wp_get_current_user()->user_login );
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
				update_post_meta( $post->ID, self::MODERATOR_META, wp_get_current_user()->user_login );
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
}
