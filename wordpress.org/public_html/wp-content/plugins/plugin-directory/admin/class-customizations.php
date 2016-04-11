<?php
namespace WordPressdotorg\Plugin_Directory\Admin;
use \WordPressdotorg\Plugin_Directory;
use \WordPressdotorg\Plugin_Directory\Tools;
use \WordPressdotorg\Plugin_Directory\Admin\List_Table\Plugin_Posts;

/**
 * All functionality related to the Administration interface.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin
 */
class Customizations {

	/**
	 * Fetch the instance of the Customizations class.
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Customizations();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Admin Metaboxes
		add_action( 'add_meta_boxes', array( $this, 'register_admin_metaboxes' ), 10, 2 );
		add_action( 'do_meta_boxes', array( $this, 'replace_title_global' ) );

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_action( 'save_post_plugin', array( $this, 'save_plugin_post' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_head-edit.php', array( $this, 'plugin_posts_list_table' ) );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );

		add_action( 'wp_ajax_replyto-comment', array( $this, 'save_custom_comment' ), 0 );
		add_filter( 'comment_row_actions', array( $this, 'custom_comment_row_actions' ), 10, 2 );

		add_filter( 'postbox_classes_plugin_internal-notes',    array( __NAMESPACE__ . '\Metabox\Internal_Notes', 'postbox_classes' ) );
		add_filter( 'postbox_classes_plugin_plugin-committers', array( __NAMESPACE__ . '\Metabox\Committers',     'postbox_classes' ) );
		add_filter( 'wp_ajax_add-committer',    array( __NAMESPACE__ . '\Metabox\Committers', 'add_committer'    ) );
		add_filter( 'wp_ajax_delete-committer', array( __NAMESPACE__ . '\Metabox\Committers', 'remove_committer' ) );

		// Page access within wp-admin.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'load-index.php',    array( $this, 'disable_admin_page' ) );
		add_action( 'load-profile.php',  array( $this, 'disable_admin_page' ) );
	}

	/**
	 * Adds the plugin name into the post editing title.
	 *
	 * @global string $title The wp-admin title variable.
	 *
	 * @param string $post_type The post type of the current page
	 * @return void.
	 */
	public function replace_title_global( $post_type ) {
		global $title;

		if ( 'plugin' === $post_type ) {
			$title = sprintf( $title, get_the_title() ); // esc_html() on output
		}
	}

	/**
	 * Enqueue JS and CSS assets needed for any wp-admin screens.
	 *
	 * @param string $hook_suffix The hook suffix of the current screen.
	 * @return void.
	 */
	public function enqueue_assets( $hook_suffix ) {
		global $post_type;

		if ( 'plugin' === $post_type ) {
			switch ( $hook_suffix ) {
				case 'post.php':
					wp_enqueue_style( 'plugin-admin-post-css', plugins_url( 'css/edit-form.css', Plugin_Directory\PLUGIN_FILE ), array( 'edit' ), 1 );
					wp_enqueue_script( 'plugin-admin-post-js', plugins_url( 'js/edit-form.js', Plugin_Directory\PLUGIN_FILE ), array( 'wp-util', 'wp-lists' ), 1 );
					wp_localize_script( 'plugin-admin-post-js', 'pluginDirectory', array(
						'removeCommitterAYS' => __( 'Are you sure you want to remove this committer?', 'wporg-plugins' ),
					) );
					break;

				case 'edit.php':
					wp_enqueue_style( 'plugin-admin-edit-css', plugins_url( 'css/plugin-list.css', Plugin_Directory\PLUGIN_FILE ), array(), 1 );
					break;
			}
		}
	}

	/**
	 * Customizes the admin menu according to the current user's privileges.
	 */
	public function admin_menu() {

		/*
		 * WordPress requires that the plugin post_type have at least one submenu accessible *other* than itself.
		 * If it doesn't have at least one submenu then users who cannot also publish posts will not be able to access the post type.
		 */
		add_submenu_page( 'edit.php?post_type=plugin', 'Plugin Handbook', 'Plugin Handbook', 'read', 'handbook', function() {} );
		add_submenu_page( 'edit.php?post_type=plugin', 'Readme Validator', 'Readme Validator', 'read', 'readme_validator', function() {} );

		if ( ! current_user_can( 'manage_options' ) ) {
			remove_menu_page( 'index.php' );
			remove_menu_page( 'profile.php' );
		}
	}

	/**
	 * Disables admin pages.
	 */
	public function disable_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {

			// Dashboard is plugin dashboard.
			if ( 'load-index.php' === current_action() ) {
				wp_safe_redirect( admin_url( 'edit.php?post_type=plugin' ) );
				exit;
			}

			wp_die( __( 'You do not have permission to access this page.', 'wporg-plugins' ), '', array(
				'back_link' => true,
			) );
		}
	}

	/**
	 * Filter the query in wp-admin to list only plugins relevant to the current user.
	 */
	public function pre_get_posts( $query ) {
		global $wpdb;
		if ( ! $query->is_main_query() ) {
			return;
		}

		if ( empty( $query->query['post_status'] ) ) {
			$query->query_vars['post_status'] = array( 'publish', 'future', 'draft', 'pending', 'disabled', 'closed', 'rejected' );
		}

		if ( ! current_user_can( 'plugin_edit_others' ) || ( isset( $query->query['author'] ) && $query->query['author'] == get_current_user_id() ) ) {
			$query->query_vars['author'] = get_current_user_id();

			$plugins = Tools::get_users_write_access_plugins( get_current_user_id() );
			if ( $plugins ) {
				$query->query_vars['post_name__in'] = $plugins;
				$query->query_vars['post_status']   = 'any';

				add_filter( 'posts_where', array( $this, 'pre_get_posts_sql_name_or_user' ) );
			}
		}
	}

	/**
	 * Custom callback for pre_get_posts to use an OR query between post_name & post_author
	 *
	 * @ignore
	 */
	public function pre_get_posts_sql_name_or_user( $where ) {
		global $wpdb;

		remove_filter( 'posts_where', array( $this, 'pre_get_posts_sql_name_or_user' ) );

		// Replace `post_name IN(..) AND post_author IN (..)`
		// With `( post_name IN() OR post_author IN() )`
		$where = preg_replace( "!\s(\S+\.post_name IN .+?)\s*AND\s*(\s\S+\.post_author.+?)AND!i", ' ( $1 OR $2 ) AND', $where );

		// Allow reviewers to also see all pending plugins.
		if ( current_user_can( 'plugin_edit_pending' ) && ( ! isset( $_GET['author'] ) || ( isset( $_GET['post_status'] ) && 'pending' === $_GET['post_status'] ) ) ) {
			$where .= " OR {$wpdb->posts}.post_status = 'pending'";
		}

		return $where;
	}

	/**
	 * Replaces the WP_Posts_List_Table object with the extended Plugin_Posts list table object.
	 *
	 * @global string               $post_type     The current post type.
	 * @global \WP_Posts_List_Table $wp_list_table The WP_Posts_List_Table object.
	 */
	public function plugin_posts_list_table() {
		global $post_type, $wp_list_table;

		if ( 'plugin' === $post_type ) {
			$wp_list_table = new Plugin_Posts();
			$wp_list_table->prepare_items();
		}
	}

	/**
	 * Filter the default post display states used in the posts list table.
	 *
	 * @param array    $post_states An array of post display states.
	 * @param \WP_Post $post        The current post object.
	 */
	public function post_states( $post_states, $post ) {
		$post_status = '';

		if ( isset( $_REQUEST['post_status'] ) ) {
			$post_status = $_REQUEST['post_status'];
		}

		if ( 'disabled' == $post->post_status && 'disabled' != $post_status ) {
			$post_states['disabled'] = _x( 'Disabled', 'plugin status', 'wporg-plugins' );
		}
		if ( 'closed' == $post->post_status && 'closed' != $post_status ) {
			$post_states['closed'] = _x( 'Closed', 'plugin status', 'wporg-plugins' );
		}
		if ( 'rejected' == $post->post_status && 'rejected' != $post_status ) {
			$post_states['rejected'] = _x( 'Rejected', 'plugin status', 'wporg-plugins' );
		}

		return $post_states;
	}

	/**
	 * Register the Admin metaboxes for the plugin management screens.
	 *
	 * @param string   $post_type The post type of the current screen.
	 * @param \WP_Post $post      Post object.
	 * @return void.
	 */
	public function register_admin_metaboxes( $post_type, $post ) {
		if ( 'plugin' != $post_type ) {
			return;
		}

		// Only plugin reviewers/admins need review-related meta boxes.
		if ( current_user_can( 'plugin_approve' ) ) {
			add_meta_box(
				'internal-notes',
				__( 'Internal Notes', 'wporg-plugins' ),
				array( __NAMESPACE__ . '\Metabox\Internal_Notes', 'display' ),
				'plugin', 'normal', 'high'
			);

			add_meta_box(
				'plugin-review',
				__( 'Plugin Review Tools', 'wporg-plugins' ),
				array( __NAMESPACE__ . '\Metabox\Review_Tools', 'display' ),
				'plugin', 'normal', 'high'
			);
		}

		add_meta_box(
			'plugin-fields',
			__( 'Plugin Meta', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Custom_Fields', 'display' ),
			'plugin', 'normal', 'low'
		);

		// Replace the publish box.
		add_meta_box(
			'submitdiv',
			__( 'Plugin Controls', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Controls', 'display' ),
			'plugin', 'side', 'high'
		);

		if ( 'publish' === $post->post_status ) {
			add_meta_box(
				'plugin-committers',
				__( 'Plugin Committers', 'wporg-plugins' ),
				array( __NAMESPACE__ . '\Metabox\Committers', 'display' ),
				'plugin', 'side'
			);
		}

		// Remove unnecessary metaboxes.
		remove_meta_box( 'slugdiv',          'plugin', 'normal' );
		remove_meta_box( 'commentsdiv',      'plugin', 'normal' );
		remove_meta_box( 'commentstatusdiv', 'plugin', 'normal' );
	}

	/**
	 * Hook into the save process for the plugin post_type to save extra metadata.
	 *
	 * Currently saves the tested_with value.
	 *
	 * @param int      $post_id The post_id being updated.
	 * @param \WP_Post $post    The WP_Post object being updated.
	 */
	public function save_plugin_post( $post_id, $post ) {
		// Save meta information
		if ( isset( $_POST['tested_with'] ) && isset( $_POST['hidden_tested_with'] ) && $_POST['tested_with'] != $_POST['hidden_tested_with'] ) {
			update_post_meta( $post_id, 'tested', wp_slash( wp_unslash( $_POST['tested_with'] ) ) );
		}
	}

	/**
	 * Filter the action links displayed for each comment.
	 *
	 * Actions for internal notes can be limited to replying for plugin reviewers.
	 * Plugin Admins can additionally trash, untrash, and quickedit a note.
	 *
	 * @param array       $actions An array of comment actions. Default actions include:
	 *                             'Approve', 'Unapprove', 'Edit', 'Reply', 'Spam',
	 *                             'Delete', and 'Trash'.
	 * @param \WP_Comment $comment The comment object.
	 * @return array Array of comment actions.
	 */
	public function custom_comment_row_actions( $actions, $comment ) {
		if ( 'internal-note' === $comment->comment_type && isset( $_REQUEST['mode'] ) && 'single' === $_REQUEST['mode'] ) {
			$allowed_actions = array( 'reply' => true );

			if ( current_user_can( 'manage_comments' ) ) {
				$allowed_actions['trash']     = true;
				$allowed_actions['untrash']   = true;
				$allowed_actions['quickedit'] = true;
			}

			$actions = array_intersect_key( $actions, $allowed_actions );
		}

		return $actions;
	}

	/**
	 * Saves a comment that is not built-in.
	 *
	 * We pretty much have to replicate all of `wp_ajax_replyto_comment()` to be able to comment on draft posts.
	 */
	public function save_custom_comment() {
		$comment_post_ID = (int) $_POST['comment_post_ID'];
		$post            = get_post( $comment_post_ID );

		if ( 'plugin' !== $post->post_type ) {
			return;
		}
		remove_action( 'wp_ajax_replyto-comment', 'wp_ajax_replyto_comment', 1 );

		global $wp_list_table;
		if ( empty( $action ) ) {
			$action = 'replyto-comment';
		}

		check_ajax_referer( $action, '_ajax_nonce-replyto-comment' );

		if ( ! $post ) {
			wp_die( - 1 );
		}

		if ( ! current_user_can( 'edit_post', $comment_post_ID ) ) {
			wp_die( - 1 );
		}

		if ( empty( $post->post_status ) ) {
			wp_die( 1 );
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			wp_die( __( 'Sorry, you must be logged in to reply to a comment.' ) );
		}

		$user_ID              = $user->ID;
		$comment_author       = wp_slash( $user->display_name );
		$comment_author_email = wp_slash( $user->user_email );
		$comment_author_url   = wp_slash( $user->user_url );
		$comment_content      = trim( $_POST['content'] );
		$comment_type         = isset( $_POST['comment_type'] ) ? trim( $_POST['comment_type'] ) : '';

		if ( current_user_can( 'unfiltered_html' ) ) {
			if ( ! isset( $_POST['_wp_unfiltered_html_comment'] ) ) {
				$_POST['_wp_unfiltered_html_comment'] = '';
			}

			if ( wp_create_nonce( 'unfiltered-html-comment' ) != $_POST['_wp_unfiltered_html_comment'] ) {
				kses_remove_filters(); // start with a clean slate
				kses_init_filters(); // set up the filters
			}
		}

		if ( '' == $comment_content ) {
			wp_die( __( 'ERROR: please type a comment.' ) );
		}

		$comment_parent = 0;
		if ( isset( $_POST['comment_ID'] ) ) {
			$comment_parent = absint( $_POST['comment_ID'] );
		}
		$comment_auto_approved = false;
		$comment_data          = compact( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'comment_parent', 'user_ID' );

		// Automatically approve parent comment.
		if ( ! empty( $_POST['approve_parent'] ) ) {
			$parent = get_comment( $comment_parent );

			if ( $parent && $parent->comment_approved === '0' && $parent->comment_post_ID == $comment_post_ID ) {
				if ( ! current_user_can( 'edit_comment', $parent->comment_ID ) ) {
					wp_die( - 1 );
				}

				if ( wp_set_comment_status( $parent, 'approve' ) ) {
					$comment_auto_approved = true;
				}
			}
		}

		$comment_id = wp_new_comment( $comment_data );
		$comment    = get_comment( $comment_id );
		if ( ! $comment ) {
			wp_die( 1 );
		}

		$position = ( isset( $_POST['position'] ) && (int) $_POST['position'] ) ? (int) $_POST['position'] : '-1';

		ob_start();
		if ( isset( $_REQUEST['mode'] ) && 'dashboard' == $_REQUEST['mode'] ) {
			require_once( ABSPATH . 'wp-admin/includes/dashboard.php' );
			_wp_dashboard_recent_comments_row( $comment );
		} else {
			if ( isset( $_REQUEST['mode'] ) && 'single' == $_REQUEST['mode'] ) {
				$wp_list_table = _get_list_table( 'WP_Post_Comments_List_Table', array( 'screen' => 'edit-comments' ) );
			} else {
				$wp_list_table = _get_list_table( 'WP_Comments_List_Table', array( 'screen' => 'edit-comments' ) );
			}
			$wp_list_table->single_row( $comment );
		}
		$comment_list_item = ob_get_clean();

		$response = array(
			'what'     => 'comment',
			'id'       => $comment->comment_ID,
			'data'     => $comment_list_item,
			'position' => $position
		);

		$counts                   = wp_count_comments();
		$response['supplemental'] = array(
			'in_moderation'        => $counts->moderated,
			'i18n_comments_text'   => sprintf(
				_n( '%s Comment', '%s Comments', $counts->approved ),
				number_format_i18n( $counts->approved )
			),
			'i18n_moderation_text' => sprintf(
				_nx( '%s in moderation', '%s in moderation', $counts->moderated, 'comments' ),
				number_format_i18n( $counts->moderated )
			)
		);

		if ( $comment_auto_approved ) {
			$response['supplemental']['parent_approved'] = $parent->comment_ID;
			$response['supplemental']['parent_post_id']  = $parent->comment_post_ID;
		}

		$x = new \WP_Ajax_Response();
		$x->add( $response );
		$x->send();
	}
}
