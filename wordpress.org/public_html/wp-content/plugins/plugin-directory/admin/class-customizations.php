<?php
namespace WordPressdotorg\Plugin_Directory\Admin;

use \WordPressdotorg\Plugin_Directory;
use \WordPressdotorg\Plugin_Directory\Tools;
use \WordPressdotorg\Plugin_Directory\Readme\Validator;
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
		add_filter( 'dashboard_glance_items', array( $this, 'plugin_glance_items' ) );

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		add_action( 'load-edit.php', array( $this, 'bulk_reject_plugins' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_head-edit.php', array( $this, 'plugin_posts_list_table' ) );
		add_action( 'edit_form_top', array( $this, 'show_permalink' ) );
		add_action( 'admin_notices', array( $this, 'add_post_status_notice' ) );
		add_action( 'all_admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );

		add_filter( 'wp_insert_post_data', array( $this, 'check_existing_plugin_slug_on_post_update' ), 10, 2 );
		add_filter( 'wp_unique_post_slug', array( $this, 'check_existing_plugin_slug_on_inline_save' ), 10, 6 );

		add_action( 'wp_ajax_replyto-comment', array( $this, 'save_custom_comment' ), 0 );
		add_filter( 'comment_row_actions', array( $this, 'custom_comment_row_actions' ), 10, 2 );
		add_filter( 'get_comment_link', array( $this, 'link_internal_notes_to_admin' ), 10, 2 );

		// Admin Metaboxes
		add_action( 'add_meta_boxes', array( $this, 'register_admin_metaboxes' ), 10, 2 );
		add_action( 'do_meta_boxes', array( $this, 'replace_title_global' ), 10, 2 );

		add_filter( 'postmeta_form_keys', array( $this, 'postmeta_form_keys' ) );

		add_filter( 'postbox_classes_plugin_internal-notes', array( __NAMESPACE__ . '\Metabox\Internal_Notes', 'postbox_classes' ) );
		add_filter( 'postbox_classes_plugin_plugin-committers', array( __NAMESPACE__ . '\Metabox\Committers', 'postbox_classes' ) );
		add_filter( 'postbox_classes_plugin_plugin-support-reps', array( __NAMESPACE__ . '\Metabox\Support_Reps', 'postbox_classes' ) );
		add_filter( 'wp_ajax_get-notes', array( __NAMESPACE__ . '\Metabox\Internal_Notes', 'get_notes' ) );
		add_filter( 'wp_ajax_add-committer', array( __NAMESPACE__ . '\Metabox\Committers', 'add_committer' ) );
		add_filter( 'wp_ajax_delete-committer', array( __NAMESPACE__ . '\Metabox\Committers', 'remove_committer' ) );
		add_filter( 'wp_ajax_add-support-rep', array( __NAMESPACE__ . '\Metabox\Support_Reps', 'add_support_rep' ) );
		add_filter( 'wp_ajax_delete-support-rep', array( __NAMESPACE__ . '\Metabox\Support_Reps', 'remove_support_rep' ) );
		add_action( 'wp_ajax_plugin-author-lookup', array( __NAMESPACE__ . '\Metabox\Author', 'lookup_author' ) );
		add_action( 'wp_ajax_plugin-svn-sync', array( __NAMESPACE__ . '\Metabox\Review_Tools', 'svn_sync' ) );

		add_action( 'save_post', array( __NAMESPACE__ . '\Metabox\Release_Confirmation', 'save_post' ) );
		add_action( 'save_post', array( __NAMESPACE__ . '\Metabox\Author_Notice', 'save_post' ) );
	}

	/**
	 * Adds the plugin name into the post editing title.
	 *
	 * @global string $title The wp-admin title variable.
	 *
	 * @param string $post_type The post type of the current page.
	 * @param string $context   Meta box context. Possible values include 'normal', 'advanced', 'side'.
	 * @return void.
	 */
	public function replace_title_global( $post_type, $context ) {
		global $title;

		if ( 'plugin' === $post_type && 'normal' === $context ) {
			$post_type_object = get_post_type_object( $post_type );
			$title            = sprintf( '%1$s %2$s', $post_type_object->labels->edit_item, get_the_title() ); // esc_html() on output
		}
	}

	/**
	 * Filters the array of extra elements to list in the 'At a Glance'
	 * dashboard widget.
	 *
	 * @param array $items Array of extra 'At a Glance' widget items.
	 * @return array
	 */
	public function plugin_glance_items( $items ) {
		$post_type = 'plugin';
		$num_posts = wp_count_posts( $post_type );

		if ( $num_posts && $num_posts->publish ) {
			$text             = sprintf( _n( '%s Plugin', '%s Plugins', $num_posts->publish, 'wporg-plugins' ), number_format_i18n( $num_posts->publish ) );
			$post_type_object = get_post_type_object( $post_type );

			if ( $post_type_object && current_user_can( $post_type_object->cap->edit_posts ) ) {
				$item = sprintf( '<a class="plugin-count" href="edit.php?post_type=%1$s">%2$s</a>', $post_type, $text );
			} else {
				$item = sprintf( '<span class="plugin-count">%s</span>', $text );
			}

			$items[] = $item . '<style>#dashboard_right_now .plugin-count:before { content: "\f106"; }</style>';
		}

		return $items;
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
					wp_enqueue_style( 'plugin-admin-post-css', plugins_url( 'css/edit-form.css', Plugin_Directory\PLUGIN_FILE ), array( 'edit' ), 5 );
					wp_enqueue_script( 'plugin-admin-post-js', plugins_url( 'js/edit-form.js', Plugin_Directory\PLUGIN_FILE ), array( 'wp-util', 'wp-lists' ), 5 );
					wp_localize_script( 'plugin-admin-post-js', 'pluginDirectory', array(
						'approvePluginAYS'    => __( 'Are you sure you want to approve this plugin?', 'wporg-plugins' ),
						'rejectPluginAYS'     => __( 'Are you sure you want to reject this plugin?', 'wporg-plugins' ),
						'removeCommitterAYS'  => __( 'Are you sure you want to remove this committer?', 'wporg-plugins' ),
						'removeSupportRepAYS' => __( 'Are you sure you want to remove this support rep?', 'wporg-plugins' ),
					) );
					break;

				case 'edit.php':
					if ( ! current_user_can( 'plugin_approve' ) )  {
						wp_dequeue_script( 'inline-edit-post' );
					}
					break;
			}
		}
	}

	/**
	 * Filter the query in wp-admin to list only plugins relevant to the current user.
	 *
	 * @param \WP_Query $query
	 */
	public function pre_get_posts( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		if ( empty( $query->query['post_status'] ) ) {
			$query->query_vars['post_status'] = array( 'publish', 'future', 'new', 'pending', 'disabled', 'closed', 'rejected', 'approved' );
		}

		// Make it possible to search for plugins that were submitted from a specific IP.
		if ( current_user_can( 'plugin_review' ) && ! empty( $query->query['s'] ) && filter_var( $query->query['s'], FILTER_VALIDATE_IP ) ) {
			$query->query_vars['meta_key']   = '_author_ip';
			$query->query_vars['meta_value'] = $query->query_vars['s'];
			unset( $query->query_vars['s'] );

			add_filter( 'get_search_query', function() use ( $query ) {
				return esc_html( $query->query_vars['meta_value'] );
			} );
		}

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
	 * Rejects plugins in bulk.
	 */
	public function bulk_reject_plugins() {
		if ( empty( $_REQUEST['action'] ) || empty( $_REQUEST['action2'] ) || ! in_array( 'plugin_reject', array( $_REQUEST['action'], $_REQUEST['action2'] ) ) || 'plugin' !== $_REQUEST['post_type'] ) {
			return;
		}

		check_admin_referer( 'bulk-posts' );

		$rejected = 0;
		$plugins  = get_posts( array(
			'post_type'      => 'plugin',
			'post__in'       => array_map( 'absint', $_REQUEST['post'] ),
			'post_status'    => array( 'new', 'pending' ),
			'posts_per_page' => count( $_REQUEST['post'] ),
		) );

		foreach ( $plugins as $plugin ) {
			if ( ! current_user_can( 'plugin_reject', $plugin ) ) {
				wp_die( __( 'You are not allowed to reject this plugin.', 'wporg-plugins' ), '', array( 'back_link' => true ) );
			}

			$updated = wp_update_post( array(
				'ID'          => $plugin->ID,
				'post_status' => 'rejected',
			) );

			if ( $updated && ! is_wp_error( $updated ) ) {
				$rejected++;
			}
		}

		if ( $rejected ) {
			set_transient( 'settings_errors', array(
				array(
					'setting' => 'wporg-plugins',
					'code'    => 'plugins-bulk-rejected',
					'message' => sprintf( _n( '%d plugin rejected.', '%d plugins rejected.', $rejected, 'wporg-plugins' ), $rejected ),
					'type'    => 'updated',
				),
			) );
		}

		$send_back = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'locked', 'ids', 'action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), wp_get_referer() );
		wp_redirect( add_query_arg( array( 'settings-updated' => true ), $send_back ) );
		exit;
	}

	/**
	 * Displays a link to the plugin page when it's published.
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public function show_permalink( $post ) {
		if ( 'plugin' === $post->post_type && 'publish' === $post->post_status ) {
			echo get_sample_permalink_html( $post );
		}
	}

	/**
	 * Adds banners to provide a clearer status for a plugin.
	 *
	 * This is being displayed in the edit screen for a particular plugin,
	 * providing more context about the current status of a plugin for both
	 * Committers and Reviewers/Admins.
	 */
	public function add_post_status_notice() {
		if ( 'post.php' !== $GLOBALS['pagenow'] ) {
			return;
		}

		$message  = '';
		$type     = 'updated';

		switch ( get_post_status() ) {
			case 'new':
				$message = __( 'This plugin is newly requested and has not yet been reviewed.', 'wporg-plugins' );
				$type    = 'notice-info';
				break;

			case 'pending':
				$message = __( 'This plugin has been reviewed and is currently waiting on developer feedback.', 'wporg-plugins' );
				$type    = 'notice-warning';
				break;

			case 'rejected':
				$message = __( 'This plugin has been rejected and is not visible to the public.', 'wporg-plugins' );
				$type    = 'notice-error';
				break;

			case 'approved':
				$message = __( 'This plugin is approved and awaiting data upload. It is not yet visible to the public.', 'wporg-plugins' );
				break;

			case 'closed':
				$message = __( 'This plugin has been closed and is no longer available for download.', 'wporg-plugins' );
				$type    = 'notice-error';
				break;

			case 'disabled':
				$message = __( 'This plugin is disabled (closed, but actively serving updates).', 'wporg-plugins' );
				$type    = 'notice-warning';
				break;
		}

		if ( $message ) {
			printf( '<div class="notice %1$s"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
		}
	}

	/**
	 * Displays all admin notices registered to `wporg-plugins`.
	 */
	public function admin_notices() {
		settings_errors( 'wporg-plugins' );
	}

	/**
	 * Filter the default post display states used in the posts list table.
	 *
	 * @param array    $post_states An array of post display states.
	 * @param \WP_Post $post        The current post object.
	 * @return array
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
		if ( 'approved' == $post->post_status && 'approved' != $post_status ) {
			$post_states['approved'] = _x( 'Approved', 'plugin status', 'wporg-plugins' );
		}

		return $post_states;
	}

	/**
	 * Check if a plugin with the provided slug already exists.
	 *
	 * Runs on 'wp_insert_post_data' when editing a plugin on Edit Plugin screen.
	 *
	 * @param array $data    The data to be inserted into the database.
	 * @param array $postarr The raw data passed to `wp_insert_post()`.
	 * @return array The data to insert into the database.
	 */
	function check_existing_plugin_slug_on_post_update( $data, $postarr ) {
		if ( 'plugin' !== $data['post_type'] || ! isset( $postarr['ID'] ) ) {
			return $data;
		}

		$existing_plugin = Plugin_Directory\Plugin_Directory::get_plugin_post( $data['post_name'] );

		// Is there already a plugin with the same slug?
		if ( $existing_plugin && $existing_plugin->ID != $postarr['ID'] ) {
			wp_die( sprintf(
				/* translators: %s: plugin slug */
				__( 'Error: The plugin %s already exists.', 'wporg-plugins' ),
				$data['post_name']
			) );
		}

		return $data;
	}

	/**
	 * Check if a plugin with the provided slug already exists.
	 *
	 * Runs on 'wp_unique_post_slug' when editing a plugin via Quick Edit.
	 *
	 * @param string $slug          The unique post slug.
	 * @param int    $post_ID       Post ID.
	 * @param string $post_status   Post status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Post parent ID.
	 * @param string $original_slug The original post slug.
	 * @return string The unique post slug.
	 */
	function check_existing_plugin_slug_on_inline_save( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
		if ( 'plugin' !== $post_type || 'rejected' === $post_status ) {
			return $slug;
		}

		// Did wp_unique_post_slug() change the slug to avoid a conflict?
		if ( $slug !== $original_slug ) {
			wp_die( sprintf(
				/* translators: %s: plugin slug */
				__( 'Error: The plugin %s already exists.', 'wporg-plugins' ),
				$original_slug
			) );
		}

		return $slug;
	}

	/**
	 * Register the Admin metaboxes for the plugin management screens.
	 *
	 * @param string   $post_type The post type of the current screen.
	 * @param \WP_Post $post      Post object.
	 * @return void.
	 */
	public function register_admin_metaboxes( $post_type, $post ) {
		if ( 'plugin' !== $post_type ) {
			return;
		}

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

		add_meta_box(
			'plugin-release-confirmation',
			__( 'Plugin Release Confirmation', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Release_Confirmation', 'display' ),
			'plugin', 'normal', 'high'
		);

		add_meta_box(
			'plugin-author',
			__( 'Author Card', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Author_Card', 'display' ),
			'plugin', 'side'
		);

		add_meta_box(
			'authordiv',
			__( 'Author', 'wporg-plugins' ),
			array( __NAMESPACE__ . '\Metabox\Author', 'display' ),
			'plugin', 'normal'
		);

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

		if ( 'new' !== $post->post_status && 'pending' != $post->post_status ) {
			add_meta_box(
				'plugin-committers',
				__( 'Plugin Committers', 'wporg-plugins' ),
				array( __NAMESPACE__ . '\Metabox\Committers', 'display' ),
				'plugin', 'side'
			);

			add_meta_box(
				'plugin-support-reps',
				__( 'Plugin Support Reps', 'wporg-plugins' ),
				array( __NAMESPACE__ . '\Metabox\Support_Reps', 'display' ),
				'plugin', 'side'
			);

			add_meta_box(
				'plugin-commits',
				__( 'Plugin Commits', 'wporg-plugins' ),
				array( __NAMESPACE__ . '\Metabox\Commits', 'display' ),
				'plugin', 'normal', 'low'
			);

			add_meta_box(
				'plugin-author-notice',
				__( 'Author Notice (Displayed on the plugins page to Plugin Authors)', 'wporg-plugins' ),
				array( __NAMESPACE__ . '\Metabox\Author_Notice', 'display' ),
				'plugin', 'normal', 'high'
			);
		}

		// Remove unnecessary metaboxes.
		remove_meta_box( 'commentsdiv', 'plugin', 'normal' );
		remove_meta_box( 'commentstatusdiv', 'plugin', 'normal' );

		// Remove slug metabox unless the slug is editable for the current user.
		if ( ! in_array( $post->post_status, array( 'new', 'pending' ) ) || ! current_user_can( 'plugin_approve', $post ) ) {
			remove_meta_box( 'slugdiv', 'plugin', 'normal' );
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
	 * Changes the permalink for internal notes to link to the edit post screen.
	 *
	 * @param string      $link The comment permalink with '#comment-$id' appended.
	 * @param \WP_Comment $comment The current comment object.
	 * @return string The permalink to the given comment.
	 */
	public function link_internal_notes_to_admin( $link, $comment ) {
		if ( 'internal-note' === $comment->comment_type ) {
			$link = get_edit_post_link( $comment->comment_post_ID );
			$link = $link . '#comment-' . $comment->comment_ID;
		}

		return $link;
	}

	/**
	 * Saves a comment that is not built-in.
	 *
	 * We pretty much have to replicate all of `wp_ajax_replyto_comment()` to be able to comment on pending posts.
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
			wp_die( __( 'Sorry, you must be logged in to reply to a comment.', 'wporg-plugins' ) );
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
			wp_die( __( 'ERROR: please type a comment.', 'wporg-plugins' ) );
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

		// Inherit comment type from parent comment.
		if ( ! $comment_data['comment_type'] ) {
			$parent = get_comment( $comment_parent );
			if ( $parent && $parent->comment_post_ID == $comment_post_ID ) {
				$comment_data['comment_type'] = $parent->comment_type;
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
			require_once ABSPATH . 'wp-admin/includes/dashboard.php';
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
			'position' => $position,
		);

		$counts                   = wp_count_comments();
		$response['supplemental'] = array(
			'in_moderation'        => $counts->moderated,
			'i18n_comments_text'   => sprintf(
				_n( '%s Comment', '%s Comments', $counts->approved, 'wporg-plugins' ),
				number_format_i18n( $counts->approved )
			),
			'i18n_moderation_text' => sprintf(
				_nx( '%s in moderation', '%s in moderation', $counts->moderated, 'comments', 'wporg-plugins' ),
				number_format_i18n( $counts->moderated )
			),
		);

		if ( $comment_auto_approved && isset( $parent ) ) {
			$response['supplemental']['parent_approved'] = $parent->comment_ID;
			$response['supplemental']['parent_post_id']  = $parent->comment_post_ID;
		}

		$x = new \WP_Ajax_Response();
		$x->add( $response );
		$x->send();
	}

	/**
	 * Cache / Filter the keys to display in the dropdown list for custom post meta.
	 */
	function postmeta_form_keys( $keys ) {
		global $wpdb;
		if ( ! is_null( $keys ) ) {
			return $keys;
		}

		// We're going to cache this for 24hrs.. that might be enough.
		$keys = wp_cache_get( __METHOD__, 'distinct-meta-keys' );

		if ( ! $keys ) {
			// Exclude the translated meta fields.
			$keys = $wpdb->get_col(
				"SELECT DISTINCT meta_key
				FROM $wpdb->postmeta
				WHERE meta_key NOT BETWEEN '_' AND '_z'
				HAVING
					meta_key NOT LIKE '\_%' AND
					meta_key NOT LIKE 'title\_%' AND
					meta_key NOT LIKE 'block_title\_%' AND
					meta_key NOT LIKE 'excerpt\_%' AND
					meta_key NOT LIKE 'content\_%'
				ORDER BY meta_key
				LIMIT 300",
			);
		}

		wp_cache_set( __METHOD__, $keys, 'distinct-meta-keys', DAY_IN_SECONDS );

		return $keys;
	}
}
