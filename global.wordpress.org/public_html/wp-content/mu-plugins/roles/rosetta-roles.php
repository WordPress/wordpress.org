<?php
/**
 * Plugin Name: Rosetta Roles
 * Plugin URI: https://wordpress.org/
 * Description: WordPress interface for managing roles.
 * Author: ocean90
 * Version: 1.0
 */

class Rosetta_Roles {
	/**
	 * Endpoint for profiles.wordpress.org updates.
	 */
	const PROFILES_HANDLER_URL = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';

	/**
	 * Holds the role of a translation editor.
	 *
	 * @var string
	 */
	public $translation_editor_role = 'translation_editor';

	/**
	 * Holds the meta key of the project access list.
	 *
	 * @var string
	 */
	public $project_access_meta_key = 'translation_editor_project_access_list';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Attaches hooks once plugins are loaded.
	 */
	public function plugins_loaded() {
		add_filter( 'editable_roles', array( $this, 'editable_roles' ) );
		add_filter( 'manage_users_columns',  array( $this, 'add_roles_column' ) );
		add_filter( 'manage_users_custom_column',  array( $this, 'display_user_roles' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'role_modifications' ) );
		add_action( 'set_user_role', array( $this, 'restore_translation_editor_role' ), 10, 3 );
		add_filter( 'gettext_with_context', array( $this, 'rename_user_roles' ), 10, 4 );
		add_action( 'admin_menu', array( $this, 'register_translation_editors_page' ) );
		add_filter( 'user_row_actions', array( $this, 'promote_user_to_translation_editor' ), 10, 2 );
	}

	/**
	 * Adds an action link to promote an user to a translation editor.
	 *
	 * @param array   $actions     An array of action links to be displayed.
	 * @param WP_User $user_object WP_User object for the currently-listed user.
	 * @return array $actions An array of action links to be displayed.
	 */
	public function promote_user_to_translation_editor( $actions, $user ) {
		if ( in_array( $this->translation_editor_role, $user->roles ) || ! current_user_can( 'promote_users' ) ) {
			return $actions;
		}

		$url = menu_page_url( 'translation-editors', false );
		$url = add_query_arg( array(
			'action' => 'add-translation-editor',
			'user'   => $user->ID,
		), $url );
		$url = wp_nonce_url( $url, 'add-translation-editor', '_nonce_add-translation-editor' );
		$actions['translation-editor'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			__( 'Promote to Translation Editor', 'rosetta' )
		);

		return $actions;
	}

	/**
	 * Registers "Translation Editor" role and modifies editor role.
	 */
	public function role_modifications() {
		if ( ! get_role( $this->translation_editor_role ) ) {
			add_role( $this->translation_editor_role, __( 'Translation Editor', 'rosetta' ), array( 'read' => true, 'level_0' => true ) );
		}

		$editor_role = get_role( 'editor' );
		if ( $editor_role && ! $editor_role->has_cap( 'remove_users' ) ) {
			$editor_role->add_cap( 'edit_theme_options' );
			$editor_role->add_cap( 'list_users' );
			$editor_role->add_cap( 'promote_users' );
			$editor_role->add_cap( 'remove_users' );
		}

		// Remove deprecated validator role.
		/*$validator_role = get_role( 'validator' );
		if ( $validator_role ) {
			remove_role( 'validator' );
		}*/
	}

	/**
	 * Restores the "Translation Editor" role if an user is promoted.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $role      The new role.
	 * @param array  $old_roles An array of the user's previous roles.
	 */
	public function restore_translation_editor_role( $user_id, $role, $old_roles ) {
		if ( ! in_array( $this->translation_editor_role, $old_roles ) ) {
			return;
		}

		$user = new WP_User( $user_id );
		$user->add_role( $this->translation_editor_role );
	}

	/**
	 * Removes "Translation Editor" role and "Administrator" role from
	 * the list of editable roles.
	 *
	 * The list used in wp_dropdown_roles() on users list table.
	 *
	 * @param array $all_roles List of roles.
	 * @return array Filtered list of editable roles.
	 */
	public function editable_roles( $roles ) {
		unset( $roles[ $this->translation_editor_role ] );

		if ( ! is_super_admin() && ! is_main_site() ) {
			unset( $roles['administrator'] );
		}

		return $roles;
	}

	/**
	 * Translates the "Translation Editor" role.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Text to translate.
	 * @param string $context     Context information for the translators.
	 * @param string $domain      Text domain.
	 * @return string Translated user role.
	 */
	public function rename_user_roles( $translation, $text, $context, $domain ) {
		if ( $domain !== 'default' || $context !== 'User role' ) {
			return $translation;
		}

		if ( 'Translation Editor' === $text ) {
			return __( 'Translation Editor', 'rosetta' );
		}

		return $translation;
	}

	/**
	 * Replaces the "Role" column with a "Roles" column.
	 *
	 * @param array $columns An array of column headers.
	 * @return array An array of column headers.
	 */
	public function add_roles_column( $columns ) {
		$posts = $columns['posts'];
		unset( $columns['role'], $columns['posts'] );
		reset( $columns );
		$columns['roles'] = __( 'Roles', 'rosetta' );
		$columns['posts'] = $posts;

		return $columns;
	}

	/**
	 * Displays a comma separated list of user's roles.
	 *
	 * @param string $output      Custom column output.
	 * @param string $column_name Column name.
	 * @param int    $user_id     ID of the currently-listed user.
	 * @return string Comma separated list of user's roles.
	 */
	public function display_user_roles( $output, $column_name, $user_id ) {
		global $wp_roles;

		if ( 'roles' == $column_name ) {
			$user_roles = array();
			$user = new WP_User( $user_id );
			foreach ( $user->roles as $role ) {
				$role_name = $wp_roles->role_names[ $role ];
				$role_name = translate_user_role( $role_name );
				$user_roles[] = $role_name;
			}

			return implode( ', ', $user_roles );
		}

		return $output;
	}

	/**
	 * Registers page for managing translation editors.
	 */
	public function register_translation_editors_page() {
		$this->translation_editors_page = add_users_page(
			__( 'Translation Editors', 'rosetta' ),
			__( 'Translation Editors', 'rosetta' ),
			'list_users',
			'translation-editors',
			array( $this, 'render_translation_editors_page' )
		);

		add_action( 'load-' . $this->translation_editors_page, array( $this, 'load_translation_editors_page' ) );
		add_action( 'admin_print_scripts-' . $this->translation_editors_page, array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueues scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'rosetta-roles', plugins_url( '/js/rosetta-roles.js', __FILE__ ), array( 'jquery' ), '1', true );
	}

	/**
	 * Loads either the overview or the edit handler.
	 */
	public function load_translation_editors_page() {
		if ( ! empty( $_REQUEST['user_id'] ) ) {
			$this->load_edit_translation_editor( $_REQUEST['user_id'] );
		} else {
			$this->load_translation_editors();
		}
	}

	/**
	 * Renders either the overview or the edit view.
	 */
	public function render_translation_editors_page() {
		if ( ! empty( $_REQUEST['user_id'] ) ) {
			$this->render_edit_translation_editor( $_REQUEST['user_id'] );
		} else {
			$this->render_translation_editors();
		}
	}

	/**
	 * Handler for overview page.
	 */
	private function load_translation_editors() {
		global $wpdb;

		$list_table = $this->get_translation_editors_list_table();
		$action = $list_table->current_action();
		$redirect = menu_page_url( 'translation-editors', false );

		if ( $action ) {
			switch ( $action ) {
				case 'add-translation-editor':
					check_admin_referer( 'add-translation-editor', '_nonce_add-translation-editor' );

					if ( ! current_user_can( 'promote_users' ) ) {
						wp_redirect( $redirect );
						exit;
					}

					$user_details = null;
					$user = wp_unslash( $_REQUEST['user'] );
					if ( false !== strpos( $user, '@' ) ) {
						$user_details = get_user_by( 'email', $user );
					} elseif ( is_numeric( $user ) ) {
						$user_details = get_user_by( 'id', $user );
					} else {
						$user_details = get_user_by( 'login', $user );
					}

					if ( ! $user_details ) {
						wp_redirect( add_query_arg( array( 'error' => 'no-user-found' ), $redirect ) );
						exit;
					}

					if ( ! is_user_member_of_blog( $user_details->ID ) ) {
						wp_redirect( add_query_arg( array( 'error' => 'not-a-member' ), $redirect ) );
						exit;
					}

					if ( in_array( $this->translation_editor_role, $user_details->roles ) ) {
						wp_redirect( add_query_arg( array( 'error' => 'user-exists' ), $redirect ) );
						exit;
					}

					$user_details->add_role( $this->translation_editor_role );
					$this->notify_translation_editor_update( $user_details->ID, 'add' );

					$projects = empty( $_REQUEST['projects'] ) ? '' : $_REQUEST['projects'];
					if ( 'custom' === $projects ) {
						$redirect = add_query_arg( 'user_id', $user_details->ID, $redirect );
						wp_redirect( add_query_arg( array( 'update' => 'user-added-custom-projects' ), $redirect ) );
						exit;
					}

					$meta_key = $wpdb->get_blog_prefix() . $this->project_access_meta_key;
					update_user_meta( $user_details->ID, $meta_key, array( 'all' ) );

					wp_redirect( add_query_arg( array( 'update' => 'user-added' ), $redirect ) );
					exit;
				case 'remove-translation-editors':
					check_admin_referer( 'bulk-translation-editors' );

					if ( ! current_user_can( 'promote_users' ) ) {
						wp_redirect( $redirect );
						exit;
					}

					if ( empty( $_REQUEST['translation-editors'] ) ) {
						wp_redirect( $redirect );
						exit;
					}

					$count = 0;
					$meta_key = $wpdb->get_blog_prefix() . $this->project_access_meta_key;
					$user_ids = array_map( 'intval', (array) $_REQUEST['translation-editors'] );
					foreach ( $user_ids as $user_id ) {
						$user = get_user_by( 'id', $user_id );
						$user->remove_role( $this->translation_editor_role );
						delete_user_meta( $user_id, $meta_key );
						$this->notify_translation_editor_update( $user_id, 'remove' );
						$count++;
					}

					wp_redirect( add_query_arg( array( 'update' => 'user-removed', 'count' => $count ), $redirect ) );
					exit;
				case 'remove-translation-editor':
					check_admin_referer( 'remove-translation-editor' );

					if ( ! current_user_can( 'promote_users' ) ) {
						wp_redirect( $redirect );
						exit;
					}

					if ( empty( $_REQUEST['translation-editor'] ) ) {
						wp_redirect( $redirect );
						exit;
					}

					$user_id = (int) $_REQUEST['translation-editor'];
					$user = get_user_by( 'id', $user_id );
					$user->remove_role( $this->translation_editor_role );
					$meta_key = $wpdb->get_blog_prefix() . $this->project_access_meta_key;
					delete_user_meta( $user_id, $meta_key );
					$this->notify_translation_editor_update( $user_id, 'remove' );

					wp_redirect( add_query_arg( array( 'update' => 'user-removed' ), $redirect ) );
					exit;
			}
		}
	}

	/**
	 * Handler for editing a translation editor.
	 *
	 * @param  int $user_id User ID of a translation editor.
	 */
	private function load_edit_translation_editor( $user_id ) {
		global $wpdb;

		$redirect = menu_page_url( 'translation-editors', false );

		if ( ! current_user_can( 'promote_users' ) ) {
			wp_redirect( $redirect );
			exit;
		}

		$user_details = get_user_by( 'id', $user_id );

		if ( ! $user_details ) {
			wp_redirect( add_query_arg( array( 'error' => 'no-user-found' ), $redirect ) );
			exit;
		}

		if ( ! is_user_member_of_blog( $user_details->ID ) ) {
			wp_redirect( add_query_arg( array( 'error' => 'not-a-member' ), $redirect ) );
			exit;
		}

		if ( ! user_can( $user_details, $this->translation_editor_role ) ) {
			wp_redirect( add_query_arg( array( 'error' => 'user-cannot' ), $redirect ) );
			exit;
		}

		$action = empty( $_REQUEST['action'] ) ? '' : $_REQUEST['action'];
		switch ( $action ) {
			case 'update-translation-editor':
				check_admin_referer( 'update-translation-editor_' . $user_details->ID );

				$redirect = add_query_arg( 'user_id', $user_details->ID, $redirect );

				$all_projects = $this->get_translate_top_level_projects();
				$all_projects = wp_list_pluck( $all_projects, 'id' );
				$all_projects = array_map( 'intval', $all_projects );

				$projects = (array) $_REQUEST['projects'];
				if ( in_array( 'all', $projects ) ) {
					$projects = array( 'all' );
				} else {
					$projects = array_map( 'intval', $projects );
					$projects = array_values( array_intersect( $all_projects, $projects ) );
				}

				$meta_key = $wpdb->get_blog_prefix() . $this->project_access_meta_key;
				update_user_meta( $user_details->ID, $meta_key, $projects );

				wp_redirect( add_query_arg( array( 'update' => 'user-updated' ), $redirect ) );
				exit;
		}
	}

	/**
	 * Renders the overview page.
	 */
	private function render_translation_editors() {
		$list_table = $this->get_translation_editors_list_table();
		$list_table->prepare_items();

		$feedback_message = $this->get_feedback_message();

		require __DIR__ . '/views/translation-editors.php';
	}

	/**
	 * Renders the edit page.
	 */
	private function render_edit_translation_editor( $user_id ) {
		global $wpdb;

		$projects = $this->get_translate_top_level_projects();

		$meta_key = $wpdb->get_blog_prefix() . $this->project_access_meta_key;
		$project_access_list = get_user_meta( $user_id, $meta_key, true );
		if ( ! $project_access_list ) {
			$project_access_list = array();
		}

		$feedback_message = $this->get_feedback_message();

		require __DIR__ . '/views/edit-translation-editor.php';
	}

	/**
	 * Returns a feedback message based on the current request.
	 *
	 * @return string HTML formatted message.
	 */
	private function get_feedback_message() {
		$message = '';

		if ( ! empty( $_REQUEST['update'] ) && ! empty( $_REQUEST['error'] ) ) {
			return $message;
		}

		$count = empty( $_REQUEST['count'] ) ? 1 : (int) $_REQUEST['count'];

		$messages = array(
			'update' => array(
				'user-updated' => __( 'Translation editor updated.', 'rosetta' ),
				'user-added'   => __( 'New translation editor added.', 'rosetta' ),
				'user-added-custom-projects' => __( 'New translation editor added. You can select the projects now.', 'rosetta' ),
				'user-removed' => sprintf( _n( '%s translation editor removed.', '%s translation editors removed.', $count, 'rosetta' ), number_format_i18n( $count ) ),
			),

			'error' => array(
				'no-user-found' => __( 'The user couldn&#8217;t be found.', 'rosetta' ),
				'not-a-member'  => __( 'The user is not a member of this site.', 'rosetta' ),
				'user-cannot'   => __( 'The user is not a translation editor.', 'rosetta' ),
				'user-exists'   => __( 'The user is already a translation editor.', 'rosetta' ),
			),
		);

		if ( isset( $_REQUEST['error'], $messages['error'][ $_REQUEST['error'] ] ) ) {
			$message = sprintf(
				'<div class="notice notice-error"><p>%s</p></div>',
				$messages['error'][ $_REQUEST['error'] ]
			);
		} elseif( isset( $_REQUEST['update'], $messages['update'][ $_REQUEST['update'] ] ) ) {
			$message = sprintf(
				'<div class="notice notice-success"><p>%s</p></div>',
				$messages['update'][ $_REQUEST['update'] ]
			);
		}

		return $message;
	}

	/**
	 * Wrapper for the custom list table which lists translation editors.
	 *
	 * @return Rosetta_Translation_Editors_List_Table The list table.
	 */
	private function get_translation_editors_list_table() {
		global $wpdb;
		static $list_table;

		require_once __DIR__ . '/class-translation-editors-list-table.php';

		if ( isset( $list_table ) ) {
			return $list_table;
		}

		$args = array(
			'user_role'               => $this->translation_editor_role,
			'projects'                => $this->get_translate_top_level_projects(),
			'project_access_meta_key' => $wpdb->get_blog_prefix() . $this->project_access_meta_key,
		);
		$list_table = new Rosetta_Translation_Editors_List_Table( $args );

		return $list_table;
	}

	/**
	 * Notifies profiles.wordpress.org about a change.
	 *
	 * @param  int    $user_id User ID.
	 * @param  string $action  Can be 'add' or 'remove'.
	 */
	private function notify_translation_editor_update( $user_id, $action ) {
		$args = array(
			'body' => array(
				'action'      => 'wporg_handle_association',
				'source'      => 'polyglots',
				'command'     => $action,
				'user_id'     => $user_id,
				'association' => 'translation-editor',
			)
		);

		wp_remote_post( self::PROFILES_HANDLER_URL, $args );
	}

	/**
	 * Fetches all top level projects from translate.wordpress.org.
	 *
	 * @return array List of projects.
	 */
	private function get_translate_top_level_projects() {
		global $wpdb;

		$cache = get_site_transient( 'translate-top-level-projects' );
		if ( false !== $cache ) {
			return $cache;
		}

		$_projects = $wpdb->get_results( "
			SELECT id, name
			FROM translate_projects
			WHERE parent_project_id IS NULL
			ORDER BY name ASC
		" );

		$projects = array();
		foreach ( $_projects as $project ) {
			$projects[ $project->id ] = $project;
		}

		set_site_transient( 'translate-top-level-projects', $projects, DAY_IN_SECONDS );

		return $projects;
	}
}

$GLOBALS['rosetta_roles'] = new Rosetta_Roles();
