<?php

namespace WordPressdotorg\Forums;

class Moderators {

	const ARCHIVED               = 'archived';
	const ARCHIVED_META          = '_wporg_bbp_unarchived_post_status';
	const MODERATOR_META         = '_wporg_bbp_moderator';
	const MODERATOR_REPLY_AUTHOR = '_wporg_bbp_moderator_reply_author';
	const DEFAULT_STATUS         = 'publish';
	const VIEWS                  = array( 'archived', 'pending', 'spam' );

	public function __construct() {
		// Moderator-specific views.
		add_action( 'bbp_register_views', array( $this, 'register_views' ), 1 );

		// Scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Allow keymasters and moderators to edit users.
		add_filter( 'bbp_map_primary_meta_caps',        array( $this, 'map_meta_caps' ), 10, 4 );
		add_action( 'bbp_post_request',                 array( $this, 'edit_user_handler' ), 0 );

		// Allow moderators to manage user roles.
		add_filter( 'bbp_get_caps_for_role',            array( $this, 'bbp_get_caps_for_role' ), 10, 2 );

		// Limit which roles a moderator can assign to a user. Before bbp_profile_update_role().
		add_action( 'bbp_profile_update',               array( $this, 'bbp_profile_update' ), 1 );

		// Append 'view=all' to forum, topic, and reply URLs in moderator views.
		add_filter( 'bbp_get_forum_permalink',          array( $this, 'add_view_all' ) );
		add_filter( 'bbp_get_topic_permalink',          array( $this, 'add_view_all' ) );
		add_filter( 'bbp_get_reply_url',                array( $this, 'add_view_all' ) );

		// Archived post status.
		add_action( 'bbp_register_post_statuses',       array( $this, 'register_post_statuses' ) );
		add_filter( 'bbp_get_reply_statuses',           array( $this, 'get_reply_statuses' ), 10, 2 );
		add_action( 'bbp_get_request',                  array( $this, 'archive_handler' ) );
		add_filter( 'bbp_after_has_topics_parse_args',  array( $this, 'add_post_status_to_query' ) );
		add_filter( 'bbp_after_has_replies_parse_args', array( $this, 'add_post_status_to_query' ) );
		add_filter( 'bbp_is_topic_pending',             array( $this, 'archived_is_pending_topic' ), 10, 2 );

		// Adjust the list of admin links for topics and replies.
		add_filter( 'bbp_topic_admin_links',            array( $this, 'admin_links' ), 10, 2 );
		add_filter( 'bbp_reply_admin_links',            array( $this, 'admin_links' ), 10, 2 );

		// Adjust the list of row actions for topics and replies.
		add_filter( 'post_row_actions',                 array( $this, 'row_actions' ), 11, 2 );

		// Add valid topic and reply actions.
		add_filter( 'bbp_get_toggle_topic_actions',     array( $this, 'get_topic_actions' ) );
		add_filter( 'bbp_get_toggle_reply_actions',     array( $this, 'get_reply_actions' ) );

		// Handle topic and reply actions.
		add_filter( 'bbp_get_request',                  array( $this, 'handle_topic_actions_request' ) );
		add_filter( 'bbp_get_request',                  array( $this, 'handle_reply_actions_request' ) );
		add_filter( 'bbp_toggle_topic',                 array( $this, 'handle_topic_actions' ), 10, 3 );
		add_filter( 'bbp_toggle_reply',                 array( $this, 'handle_reply_actions' ), 10, 3 );

		// Convert toggle links to explicit actions.
		add_filter( 'bbp_get_topic_close_link',         array( $this, 'convert_toggles_to_actions' ), 10, 3 );
		add_filter( 'bbp_get_topic_stick_link',         array( $this, 'convert_toggles_to_actions' ), 10, 3 );
		add_filter( 'bbp_get_topic_spam_link',          array( $this, 'convert_toggles_to_actions' ), 10, 3 );
		add_filter( 'bbp_get_topic_approve_link',       array( $this, 'convert_toggles_to_actions' ), 10, 3 );
		add_filter( 'bbp_get_reply_spam_link',          array( $this, 'convert_toggles_to_actions' ), 10, 3 );
		add_filter( 'bbp_get_reply_approve_link',       array( $this, 'convert_toggles_to_actions' ), 10, 3 );

		// Store moderator's username on Approve/Unapprove actions.
		add_action( 'bbp_approved_topic',               array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_approved_reply',               array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_unapproved_topic',             array( $this, 'store_moderator_username' ) );
		add_action( 'bbp_unapproved_reply',             array( $this, 'store_moderator_username' ) );

		// Allow moderators to post as @moderator.
		add_action( 'bbp_theme_before_reply_form_subscription', array( $this, 'form_add_post_as_anon_mod' ) );
		add_filter( 'bbp_new_reply_pre_insert',                 array( $this, 'bbp_new_reply_pre_insert' ) );
		add_action( 'bbp_theme_before_reply_author_details',    array( $this, 'show_anon_mod_name' ) );
		add_filter( 'user_has_cap',                             array( $this, 'anon_moderator_user_has_cap' ), 10, 4 );
		add_filter( 'wporg_notifications_pre_notify_matchers',  array( $this, 'notify_mod_of_at_mention' ), 10, 2 );

		// Hide reply archives for the @moderator account.
		add_filter( 'bbp_get_user_replies_created', array( $this, 'filter_query_hide_moderator' ), 10, 2 );
		add_filter( 'bbp_get_user_topics_started',  array( $this, 'filter_query_hide_moderator' ), 10, 2 );
		add_filter( 'bbp_get_user_engagements',     array( $this, 'filter_query_hide_moderator' ), 10, 2 );

		// Don't include @moderator activity on profiles.
		add_filter( 'wporg_profiles_wp_activity-is_forum_notifiable', array( $this, 'hide_moderator_profile_activity' ), 10, 2 );
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

		bbp_register_view(
			'all-replies',
			__( 'All Replies', 'wporg-forums' ),
			array(
				'meta_key'       => null,
				'post_type'      => 'reply',
				'post_status'    => array(
					'spam',
					'pending',
					'publish'
				),
				'show_stickies'  => false,
				'orderby'        => 'ID',
				'posts_per_page' => 50
			)
		);
	}

	public function enqueue_styles() {
		if ( current_user_can( 'moderate' ) ) {
			wp_enqueue_style(
				'support-forums-moderators',
				plugins_url( 'css/styles-moderators.css', __DIR__ ),
				array(),
				filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'css/styles-moderators.css' )
			);
		}
	}

	/**
	 * Allow keymasters and moderators to edit users without having
	 * an Administrator role on the site.
	 *
	 * @param array  $caps            User's actual capabilities.
	 * @param string $cap             Capability name.
	 * @param int    $current_user_id Current user ID.
	 * @param array  $args            Capability context, typically the object ID.
	 * @return array Filtered capabilities.
	 */
	function map_meta_caps( $caps, $cap, $current_user_id, $args ) {
		switch ( $cap ) {
			case 'promote_user':
			case 'promote_users':
			case 'edit_user':
			case 'edit_users':
				// Bail before "User Role" section is displayed.
				// See https://bbpress.trac.wordpress.org/ticket/3126
				if ( did_action( 'bbp_user_edit_after_account' ) && ! bbp_is_user_keymaster( $current_user_id ) ) {
					return $caps;
				}

				// Get the user ID.
				$user_id_to_check = ! empty( $args[0] )
					? (int) $args[0]
					: bbp_get_displayed_user_id();

				// Users can always edit themselves, so only map for others.
				if ( ! empty( $user_id_to_check ) && ( $user_id_to_check !== $current_user_id ) ) {
					// Moderators cannot edit keymasters.
					if ( bbp_is_user_keymaster( $user_id_to_check ) ) {
						return $caps;
					}

					// Moderators and keymasters cannot edit admins or super admins, unless they have the same role.
					if ( user_can( $user_id_to_check, 'manage_options' ) || is_super_admin( $user_id_to_check ) ) {
						return $caps;
					}

					$caps = array( 'moderate' );
				}
				break;
		}

		return $caps;
	}

	/**
	 * Allow (global) moderators to assign roles to users.
	 *
	 * @param array  $caps Role capabilities.
	 * @param string $role Role name.
	 * @return array
	 */
	function bbp_get_caps_for_role( $caps, $role ) {
		if (
			$role === bbp_get_moderator_role() &&
			Plugin::get_instance()->is_main_forums
		) {
			$caps['promote_users'] = true;
		}
	
		return $caps;
	}

	/**
	 * Limit the site/forum roles a moderator can set.
	 */
	public function bbp_profile_update() {
		// Keymasters need no special handling.
		if ( bbp_is_user_keymaster( get_current_user_id() ) ) {
			return;
		}

		$new_forum_role = sanitize_key( $_POST['bbp-forums-role'] ?? '' );

		// Prevent setting any roles.
		unset( $_POST['role'], $_POST['bbp-forums-role'] );

		$allowed_roles = array(
			bbp_get_participant_role(),
			bbp_get_spectator_role(),
			bbp_get_blocked_role()
		);

		// If it's an allowed role, add it back so it can be processed by bbp_profile_update_role().
		if ( in_array( $new_forum_role, $allowed_roles, true ) ) {
			$_POST['bbp-forums-role'] = $new_forum_role;
		}
	}

	/**
	 * Allow keymasters and moderators to change user's email address
	 * without requiring a confirmation.
	 *
	 * @param string $action The requested action.
	 */
	function edit_user_handler( $action = '' ) {
		if ( 'bbp-update-user' !== $action || is_admin() || bbp_is_user_home_edit() ) {
			return;
		}

		$user_id = bbp_get_displayed_user_id();

		if ( ! bbp_verify_nonce_request( 'update-user_' . $user_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) || empty( $_POST['email'] ) ) {
			return;
		}

		$user_email = bbp_get_displayed_user_field( 'user_email', 'raw' );
		$new_email  = sanitize_text_field( wp_unslash( $_POST['email'] ) );

		if ( $user_email !== $new_email ) {
			// Bail if the email address is invalid or already in use.
			if ( ! is_email( $new_email ) || email_exists( $new_email ) ) {
				return;
			}

			// Set the displayed user's email to the new address
			// so `bbp_edit_user_handler()` does not attempt to update it,
			// `edit_user()` will handle that instead.
			bbpress()->displayed_user->user_email = $new_email;

			add_filter( 'send_email_change_email', '__return_false' );
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

	/**
	 * Remove 'Trash' from reply statuses, add 'Archived' status instead.
	 *
	 * @param array $r        Reply statuses array.
	 * @param int   $reply_id Reply ID.
	 * @return array Filtered reply statuses array.
	 */
	public function get_reply_statuses( $r, $reply_id ) {
		/*
		 * Remove 'Trash' from reply statuses. Trashing a reply will eventually permanently
		 * delete it when the trash is emptied. Better to mark it as pending or spam.
		 */
		unset( $r['trash'] );

		$r[ self::ARCHIVED ] = _x( 'Archived', 'post', 'wporg-forums' );

		return $r;
	}

	public function archive_handler( $action = '' ) {
		if ( ! in_array( $action, $this->get_valid_actions() ) ) {
			return;
		}

		if ( empty( $_GET['post_id'] ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$post_id = absint( $_GET['post_id'] );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		if ( ! current_user_can( 'moderate', $post->ID ) ) {
			return;
		}

		// Check for empty post id.
		if ( ! $post ) {
			bbp_add_error( 'wporg_bbp_archive_post_id', __( '<strong>Error:</strong> No post was found! Which post are you archiving?', 'wporg-forums' ) );

		// Check for current user.
		} elseif ( empty( $user_id ) ) {
			bbp_add_error( 'wporg_bbp_archive_logged_in', __( '<strong>Error:</strong> You must be logged in to do this!', 'wporg-forums' ) );

		// Check nonce.
		} elseif ( ! bbp_verify_nonce_request( 'toggle-post-archive_' . $user_id . '_' . $post->ID ) ) {
			bbp_add_error( 'wporg_bbp_archive_nonce', __( '<strong>Error:</strong> Are you sure you wanted to do that?', 'wporg-forums' ) );

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
			bbp_redirect( $permalink );
		} elseif ( true === $is_archived && 'wporg_bbp_archive_post' === $action ) {
			bbp_add_error( 'wporg_bbp_archive_post', __( '<strong>Error:</strong> There was a problem archiving that post!', 'wporg-forums' ) );
		} elseif ( false === $is_archived && 'wporg_bbp_unarchive_post' === $action ) {
			bbp_add_error( 'wporg_bbp_unarchive_post', __( '<strong>Error:</strong> There was a problem unarchiving that post!', 'wporg-forums' ) );
		}
	}

	public function add_post_status_to_query( $args = array() ) {
		if ( bbp_get_view_all() ) {
			if ( is_array( $args['post_status'] ) ) {
				$args['post_status'][] = self::ARCHIVED;
			} else {
				$post_stati = explode( ',', $args['post_status'] );
				$post_stati[] = self::ARCHIVED;
				$args['post_status'] = implode( ',', $post_stati );
			}
		}
		return $args;
	}

	/**
	 * Remove some unneeded or redundant admin links for topics and replies,
	 * move less commonly used inline quick links to 'Topic Admin' sidebar section.
	 *
	 * @param array $r       An array of admin links.
	 * @param int   $post_id Topic or reply ID.
	 * @return array Filtered admin links.
	 */
	public function admin_links( $r, $post_id ) {
		/*
		 * Remove 'Trash' from admin links. Trashing a topic or reply will eventually
		 * permanently delete it when the trash is emptied. Better to mark it as pending or spam.
		 */
		unset( $r['trash'] );

		/*
		 * Remove 'Unapprove' link. If a post violates the forum rules, it can either be archived
		 * or marked as spam, but it should not be moved back to moderation queue.
		 */
		if ( 'pending' !== get_post_status( $post_id ) ) {
			unset( $r['approve'] );
		}

		/*
		 * Remove 'Reply' link. The theme adds its own 'Reply to Topic' sidebar link
		 * for quick access to reply form, making the default inline link redundant.
		 */
		unset( $r['reply'] );

		/*
		 * The following actions are removed from inline quick links as less commonly used,
		 * but are still available via 'Topic Admin' sidebar section.
		 */
		if ( ! did_action( 'wporg_compat_single_topic_sidebar_pre' ) ) {
			// Remove 'Merge' link.
			unset( $r['merge'] );

			// Remove 'Stick' link for moderators, but keep it for plugin/theme authors and contributors.
			if ( current_user_can( 'moderate', $post_id ) ) {
				unset( $r['stick'] );
			}
		}

		// Remove 'Stick' link for reviews or non-public topics.
		if (
			Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id( $post_id )
		||
			! in_array( get_post_status( $post_id ), array( 'publish', 'closed' ) )
		) {
			unset( $r['stick'] );
		}

		// Add 'Archive' link.
		$r['archive'] = $this->get_archive_link( array( 'post_id' => $post_id ) );

		return $r;
	}

	/**
	 * Remove some unneeded or redundant row action links for topics and replies.
	 *
	 * @param array   $actions An array of row action links.
	 * @param WP_Post $post    The post object.
	 * @return array Filtered row actions links.
	 */
	public function row_actions( $actions, $post ) {
		// Remove 'Trash' link.
		unset( $actions['trash'] );

		// Remove 'Unapprove' link.
		unset( $actions['unapproved'] );

		// Remove 'Stick' link for reviews or non-public topics.
		if (
			Plugin::REVIEWS_FORUM_ID == bbp_get_topic_forum_id( $post->ID )
		||
			! in_array( get_post_status( $post->ID ), array( 'publish', 'closed' ) )
		) {
			unset( $actions['stick'] );
		}

		return $actions;
	}

	public function get_archive_link( $args = array() ) {
		$r = bbp_parse_args( $args, array(
			'post_id' => get_the_ID(),
			'archive' => esc_html__( 'Archive', 'wporg-forums' ),
			'unarchive' => esc_html__( 'Unarchive', 'wporg-forums' ),
		), 'get_post_archive_link' );

		if ( empty( $r['post_id'] ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		$post_id = $r['post_id'];

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( ! current_user_can( 'moderate', $post->ID ) ) {
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

		$classes = array();

		if ( bbp_is_topic( $r['post_id'] ) ) {
			$classes[] = 'bbp-topic-archive-link';
		} else {
			$classes[] = 'bbp-reply-archive-link';
		}

		$url = esc_url( wp_nonce_url( add_query_arg( $query_args, $permalink ), 'toggle-post-archive_' . $user_id . '_' . $post->ID ) );
		return sprintf( "<a href='%s' class='%s'>%s</a>", $url, esc_attr( implode( ' ', $classes ) ), esc_html( $text ) );
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
				$this->store_moderator_username( $post->ID );

				if ( bbp_is_reply( $post->ID ) ) {
					$topic_id = bbp_get_reply_topic_id( $post->ID );

					bbp_update_topic_last_reply_id( $topic_id );
					bbp_update_topic_last_active_id( $topic_id );
					bbp_update_topic_last_active_time( $topic_id );
					bbp_update_topic_voice_count( $topic_id );

					if ( 'publish' === $post->post_status ) {
						bbp_decrease_topic_reply_count( $topic_id );
						bbp_increase_topic_reply_count_hidden( $topic_id );
					}

					do_action( 'wporg_bbp_archived_reply', $post->ID );
				} else {
					bbp_unstick_topic( $post->ID );

					if ( Support_Compat::is_compat_forum( $post->post_parent ) ) {
						$term            = null;
						$plugin_instance = Plugin::get_instance();

						if ( ! empty( $plugin_instance->plugins->term ) ) {
							$term = $plugin_instance->plugins->term;
						} elseif ( ! empty( $plugin_instance->themes->term ) ) {
							$term = $plugin_instance->themes->term;
						}

						if ( $term ) {
							Stickies_Compat::remove_sticky( $term->term_id, $post->ID );
						}
					}

					do_action( 'wporg_bbp_archived_topic', $post->ID );
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
				$this->store_moderator_username( $post->ID );

				if ( bbp_is_reply( $post->ID ) ) {
					$topic_id = bbp_get_reply_topic_id( $post->ID );

					bbp_update_topic_last_reply_id( $topic_id );
					bbp_update_topic_last_active_id( $topic_id );
					bbp_update_topic_last_active_time( $topic_id );
					bbp_update_topic_voice_count( $topic_id );

					if ( 'publish' === $post_status ) {
						bbp_increase_topic_reply_count( $topic_id );
						bbp_decrease_topic_reply_count_hidden( $topic_id );
					}

					do_action( 'wporg_bbp_unarchived_reply', $post->ID );
				} else {
					do_action( 'wporg_bbp_unarchived_topic', $post->ID );
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
				$permalink = bbp_get_reply_url( $post->ID );
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
			'wporg_bbp_close_topic',
			'wporg_bbp_open_topic',
			'wporg_bbp_stick_topic',
			'wporg_bbp_unstick_topic',
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
	 * Handle Spam, Unspam, Unapprove, Approve topic actions request.
	 *
	 * @param string $action The requested action.
	 */
	public function handle_topic_actions_request( $action = '' ) {
		// Bail if required GET actions aren't passed
		if ( empty( $_GET['topic_id'] ) ) {
			return;
		}

		// What's the topic id?
		$topic_id = bbp_get_topic_id( (int) $_GET['topic_id'] );

		// Get possible topic-handler actions
		$possible_actions = $this->get_topic_actions( array() );

		// Bail if action isn't meant for this function
		if ( ! in_array( $action, $possible_actions ) ) {
			return;
		}

		// Make sure topic exists
		$topic = bbp_get_topic( $topic_id );
		if ( empty( $topic ) ) {
			bbp_add_error( 'bbp_toggle_topic_missing', __( '<strong>Error:</strong> This topic could not be found or no longer exists.', 'wporg-forums' ) );
			return;
		}

		// What is the user doing here?
		if ( ! current_user_can( 'edit_topic', $topic_id ) ) {
			bbp_add_error( 'bbp_toggle_topic_permission', __( '<strong>Error:</strong> You do not have permission to do that.', 'wporg-forums' ) );
			return;
		}

		// Preliminary array
		$args = array(
			'id'         => $topic_id,
			'action'     => $action,
			'sub_action' => '',
			'data'       => array( 'ID' => $topic_id )
		);

		// Default return values
		$retval = array(
			'status'      => 0,
			'message'     => '',
			'redirect_to' => bbp_get_topic_permalink( $args['id'], bbp_get_redirect_to() ),
			'view_all'    => false
		);

		// Do the topic action
		$retval = $this->handle_topic_actions( $retval, $args, $args );

		// Redirect back to topic
		if ( ( false !== $retval['status'] ) && ! is_wp_error( $retval['status'] ) ) {
			bbp_redirect( $retval['redirect_to'] );

		// Handle errors
		} else {
			bbp_add_error( 'bbp_toggle_topic', $retval['message'] );
		}
	}

	/**
	 * Handle Spam, Unspam, Unapprove, Approve reply actions request.
	 *
	 * @param string $action The requested action.
	 */
	public function handle_reply_actions_request( $action = '' ) {
		// Bail if required GET actions aren't passed
		if ( empty( $_GET['reply_id'] ) ) {
			return;
		}

		// What's the reply id?
		$reply_id = bbp_get_reply_id( (int) $_GET['reply_id'] );

		// Get possible reply-handler actions
		$possible_actions = $this->get_reply_actions( array() );

		// Bail if action isn't meant for this function
		if ( ! in_array( $action, $possible_actions ) ) {
			return;
		}

		// Make sure reply exists
		$reply = bbp_get_reply( $reply_id );
		if ( empty( $reply ) ) {
			bbp_add_error( 'bbp_toggle_reply_missing', __( '<strong>Error:</strong> This reply could not be found or no longer exists.', 'wporg-forums' ) );
			return;
		}

		// What is the user doing here?
		if ( ! current_user_can( 'edit_reply', $reply_id ) ) {
			bbp_add_error( 'bbp_toggle_reply_permission', __( '<strong>Error:</strong> You do not have permission to do that.', 'wporg-forums' ) );
			return;
		}

		// Preliminary array
		$args = array(
			'id'         => $reply_id,
			'action'     => $action,
			'sub_action' => '',
			'data'       => array( 'ID' => $reply_id )
		);

		// Default return values
		$retval = array(
			'status'      => 0,
			'message'     => '',
			'redirect_to' => bbp_get_reply_url( $args['id'], bbp_get_redirect_to() ),
			'view_all'    => false
		);

		// Do the reply action
		$retval = $this->handle_reply_actions( $retval, $args, $args );

		// Redirect back to reply
		if ( ( false !== $retval['status'] ) && ! is_wp_error( $retval['status'] ) ) {
			bbp_redirect( $retval['redirect_to'] );

		// Handle errors
		} else {
			bbp_add_error( 'bbp_toggle_reply', $retval['message'] );
		}
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
			case 'wporg_bbp_close_topic':
				check_ajax_referer( "close-{$nonce_suffix}" );

				if ( bbp_is_topic_open( $r['id'] ) ) {
					$retval['status']  = bbp_close_topic( $r['id'] );
					$retval['message'] = __( '<strong>Error:</strong> There was a problem closing the topic.', 'wporg-forums' );
				}

				break;

			case 'wporg_bbp_open_topic':
				check_ajax_referer( "close-{$nonce_suffix}" );

				if ( ! bbp_is_topic_open( $r['id'] ) ) {
					$retval['status']  = bbp_open_topic( $r['id'] );
					$retval['message'] = __( '<strong>Error:</strong> There was a problem opening the topic.', 'wporg-forums' );
				}

				break;

			case 'wporg_bbp_stick_topic':
				check_ajax_referer( "stick-{$nonce_suffix}" );

				if ( ! bbp_is_topic_sticky( $r['id'] ) ) {
					$retval['status']  = bbp_stick_topic( $r['id'], ! empty( $_GET['super'] ) );
					$retval['message'] = __( '<strong>Error:</strong> There was a problem sticking the topic.', 'wporg-forums' );
				}

				break;

			case 'wporg_bbp_unstick_topic':
				check_ajax_referer( "stick-{$nonce_suffix}" );

				if ( bbp_is_topic_sticky( $r['id'] ) ) {
					$retval['status']  = bbp_unstick_topic( $r['id'] );
					$retval['message'] = __( '<strong>Error:</strong> There was a problem unsticking the topic.', 'wporg-forums' );
				}

				break;

			case 'wporg_bbp_spam_topic':
				check_ajax_referer( "spam-{$nonce_suffix}" );

				if ( ! bbp_is_topic_spam( $r['id'] ) ) {
					$retval['status']  = bbp_spam_topic( $r['id'] );
					$retval['message'] = __( '<strong>Error:</strong> There was a problem marking the topic as spam.', 'wporg-forums' );
				}
				$retval['view_all'] = true;

				break;

			case 'wporg_bbp_unspam_topic':
				check_ajax_referer( "spam-{$nonce_suffix}" );

				if ( bbp_is_topic_spam( $r['id'] ) ) {
					$retval['status']  = bbp_unspam_topic( $r['id'] );
					$retval['message'] = __( '<strong>Error:</strong> There was a problem unmarking the topic as spam.', 'wporg-forums' );
				}
				$retval['view_all'] = false;

				break;

			case 'wporg_bbp_unapprove_topic':
				check_ajax_referer( "approve-{$nonce_suffix}" );

				if ( ! bbp_is_topic_pending( $r['id'] ) ) {
					$retval['status']  = bbp_unapprove_topic( $r['id'] );
					$retval['message'] = __( '<strong>Error:</strong> There was a problem unapproving the topic.', 'wporg-forums' );
				}
				$retval['view_all'] = true;

				break;

			case 'wporg_bbp_approve_topic':
				check_ajax_referer( "approve-{$nonce_suffix}" );

				if ( bbp_is_topic_pending( $r['id'] ) ) {
					$retval['status']  = bbp_approve_topic( $r['id'] );
					$retval['message'] = __( '<strong>Error:</strong> There was a problem approving the topic.', 'wporg-forums' );
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
					$retval['message']  = __( '<strong>Error:</strong> There was a problem marking the reply as spam.', 'wporg-forums' );
				}
				$retval['view_all'] = true;

				break;

			case 'wporg_bbp_unspam_reply':
				check_ajax_referer( "spam-{$nonce_suffix}" );

				if ( bbp_is_reply_spam( $r['id'] ) ) {
					$retval['status']   = bbp_unspam_reply( $r['id'] );
					$retval['message']  = __( '<strong>Error:</strong> There was a problem unmarking the reply as spam.', 'wporg-forums' );
				}
				$retval['view_all'] = false;

				break;

			case 'wporg_bbp_unapprove_reply':
				check_ajax_referer( "approve-{$nonce_suffix}" );

				if ( ! bbp_is_reply_pending( $r['id'] ) ) {
					$retval['status']   = bbp_unapprove_reply( $r['id'] );
					$retval['message']  = __( '<strong>Error:</strong> There was a problem unapproving the reply.', 'wporg-forums' );
				}
				$retval['view_all'] = true;

				break;

			case 'wporg_bbp_approve_reply':
				check_ajax_referer( "approve-{$nonce_suffix}" );

				if ( bbp_is_reply_pending( $r['id'] ) ) {
					$retval['status']   = bbp_approve_reply( $r['id'] );
					$retval['message']  = __( '<strong>Error:</strong> There was a problem approving the reply.', 'wporg-forums' );
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
	 * @param array  $args Optional. Raw arguments.
	 * @return string Filtered link.
	 */
	public function convert_toggles_to_actions( $link, $r, $args = array() ) {
		if ( false !== strpos( $link, 'bbp_toggle_topic_close' ) ) {
			$action = ( bbp_is_topic_closed( $r['id'] ) ) ? 'wporg_bbp_open_topic' : 'wporg_bbp_close_topic';
			$link   = str_replace( 'bbp_toggle_topic_close', $action, $link );

		} elseif ( false !== strpos( $link, 'bbp_toggle_topic_stick' ) ) {
			$action = ( bbp_is_topic_sticky( $r['id'] ) ) ? 'wporg_bbp_unstick_topic' : 'wporg_bbp_stick_topic';
			$link   = str_replace( 'bbp_toggle_topic_stick', $action, $link );

		} elseif ( false !== strpos( $link, 'bbp_toggle_topic_spam' ) ) {
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
	 * Store moderator's username on Approve/Unapprove and Archive/Unarchive actions.
	 *
	 * @param int $post_id Post ID.
	 */
	public function store_moderator_username( $post_id ) {
		update_post_meta( $post_id, self::MODERATOR_META, wp_get_current_user()->user_nicename );
	}

	/**
	 * Treat the archived status as a pending status to ensure that bbp_get_reply_url() doesn't slash a non-pretty url.
	 * 
	 * @param bool $topic_status Whether the topic is pending.
	 * @param int  $topic_id     The topic ID.
	 * @return bool
	 */
	public function archived_is_pending_topic( $topic_status, $topic_id ) {
		if (
			! $topic_status &&
			( bbp_get_topic_status( $topic_id ) === self::ARCHIVED )
		) {
			$topic_status = true;
		}

		return $topic_status;
	}

	/**
	 * Add a checkbox to the reply form to allow moderators to post anonymously.
	 */
	public function form_add_post_as_anon_mod() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$moderator_user = get_user_by( 'slug', 'moderator' );
		if ( bbp_is_reply_edit() && $moderator_user->ID !== bbp_get_reply_author_id() ) {
			return;
		}

		?>

		<p>
			<label>
				<input type="checkbox" name="post_as_anon_moderator" <?php disabled( true, bbp_is_reply_edit() ); checked( $moderator_user->ID, bbp_get_reply_author_id() ) ?>>
				<?php esc_html_e( 'Post this reply anonymously as @moderator.', 'wporg-forums' ); ?>
			</label>
		</p>

		<?php
	}

	/**
	 * Overwrite the reply author if required.
	 *
	 * @param array $post_data The reply data.
	 * @return array The filtered reply data.
	 */
	public function bbp_new_reply_pre_insert( $post_data ) {
		if ( ! current_user_can( 'moderate' ) || empty( $_POST['post_as_anon_moderator'] ) ) {
			return $post_data;
		}

		// Overwrite the author.
		$post_data['post_author'] = get_user_by( 'slug', 'moderator' )->ID;

		// Record the real user in the post meta.
		$post_data['meta_input'] ??= [];
		$post_data['meta_input'][ self::MODERATOR_REPLY_AUTHOR ] = get_current_user_id();

		return $post_data;
	}

	/**
	 * Display the moderator's name (to other moderators) if the reply was posted anonymously.
	 */
	function show_anon_mod_name() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$moderator_user = get_user_by( 'slug', 'moderator' );
		if ( $moderator_user->ID !== bbp_get_reply_author_id() ) {
			return;
		}

		$user = get_user_by( 'id', get_post_meta( bbp_get_reply_id(), self::MODERATOR_REPLY_AUTHOR, true ) );

		printf(
			'<em>' . __( 'Posted by <a href="%s">@%s</a>.', 'wporg-forums' ) . '</em><br/>',
			esc_url( bbp_get_user_profile_url( $user->ID ) ),
			esc_html( $user->user_nicename )
		);
	}

	/**
	 * Pretend the @moderator user can moderate, except when they're logged in.
	 *
	 * This keeps the moderator account as a low-access account, while also showing the replies
	 * with a moderator badge, and bypassing moderation.
	 */
	public function anon_moderator_user_has_cap( $allcaps, $caps, $args, $user ) {
		if (
			$user &&
			[ 'moderate' === $caps ] &&
			'moderator' === $user->user_nicename &&
			$user->ID !== get_current_user_id()
		) {
			$allcaps['moderate'] = true;
		}

		return $allcaps;
	}

	/**
	 * When a @moderator mention is used in a reply, notify the
	 * moderators who have interacted with that thread.
	 *
	 * As it's possible for multiple moderators to be involved in a thread
	 * the notification is sent to all who have interacted with it.
	 *
	 * This filter is within the wporg-notifications plugin.
	 */
	public function notify_mod_of_at_mention( $matchers, $data ) {
		if (
			empty( $matchers['moderator']->type ) ||
			'username' != $matchers['moderator']->type ||
			'forum_topic_reply' != $data['type']
		) {
			return $matchers;
		}

		// Get all moderators who have interacted with this thread.		
		$mod_replies = (array) get_posts( [
			'post_parent'    => $data['topic_id'],
			'post_type'      => bbp_get_reply_post_type(),
			'author'         => get_user_by( 'slug', 'moderator' )->ID,
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'order'          => 'ASC'
		] );

		$moderators = [];
		foreach ( $mod_replies as $reply_id ) {
			$moderators[] = get_post_meta( $reply_id, self::MODERATOR_REPLY_AUTHOR, true );
		}

		foreach ( array_unique( $moderators ) as $mod_id ) {
			$moderator = get_user_by( 'id', $mod_id );
			if ( ! $moderator || isset( $matchers[ $moderator->user_nicename ] ) ) {
				continue;
			}

			$matchers[ $moderator->user_nicename ]                    = $matchers['moderator'];
			$matchers[ $moderator->user_nicename ]->notification_name = "@moderator response";
		}

		return $matchers;
	}

	/**
	 * Disable activity queries for the moderator user, from non-moderators.
	 *
	 * @param bool $query   The user query.
	 * @param int  $user_id The user ID.
	 */
	public function filter_query_hide_moderator( $query, $user_id ) {
		if ( ! current_user_can( 'moderate' ) ) {
			// For the bbp_get_user_engagements filter
			if ( ! $user_id || ! is_numeric( $user_id ) ) {
				$user_id = bbp_get_displayed_user_id();
			}

			$moderator_user = get_user_by( 'slug', 'moderator' );
			if ( $moderator_user->ID === $user_id ) {
				return false;
			}
		}

		return $query;
	}

	/**
	 * Don't send @moderator actions to profiles.wordpress.org
	 *
	 * @param bool  $returnval Whether to send the profile activity or not.
	 * @param array $args      The data that will be sent to the activity API.
	 * @return bool $returnval
	 */
	public function hide_moderator_profile_activity( $returnval, $args ) {
		if ( 'moderator' == ( $args['user'] ?? '' ) ) {
			return false;
		}

		return $returnval;
	}
}
