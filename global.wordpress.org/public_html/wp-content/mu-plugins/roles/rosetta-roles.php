<?php
/**
 * Plugin Name: Rosetta Roles
 * Plugin URI: https://wordpress.org/
 * Description: WordPress interface for managing roles.
 * Author: ocean90
 * Version: 1.1
 */

if ( ! class_exists( 'GP_Locales' ) ) {
	require_once GLOTPRESS_LOCALES_PATH;
}

class Rosetta_Roles {
	/**
	 * Endpoint for profiles.wordpress.org updates.
	 */
	const PROFILES_HANDLER_URL = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';

	/**
	 * Database table for translation editors.
	 */
	const TRANSLATION_EDITORS_TABLE = 'translate_translation_editors';

	/**
	 * Role of a per project translation editor.
	 */
	const TRANSLATION_EDITOR_ROLE = 'translation_editor';

	/**
	 * Role of a general translation editor.
	 */
	const GENERAL_TRANSLATION_EDITOR_ROLE = 'general_translation_editor';

	/**
	 * Capabaility to promote translation editor.
	 */
	const MANAGE_TRANSLATION_EDITORS_CAP = 'manage_translation_editors';

	/**
	 * Holds the GlotPress locale of current site.
	 *
	 * @var GP_Locale
	 */
	private $gp_locale = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

		$wpdb->wporg_translation_editors = self::TRANSLATION_EDITORS_TABLE;
	}

	/**
	 * Attaches hooks once plugins are loaded.
	 */
	public function plugins_loaded() {
		$locale = get_locale();
		$gp_locale = GP_Locales::by_field( 'wp_locale', $locale );
		if ( ! $gp_locale ) {
			return;
		}

		$this->gp_locale = $gp_locale;

		add_filter( 'editable_roles', array( $this, 'editable_roles' ) );
		add_action( 'admin_init', array( $this, 'role_modifications' ) );
		add_action( 'set_user_role', array( $this, 'restore_translation_editor_role' ), 10, 3 );
		add_filter( 'gettext_with_context', array( $this, 'rename_user_roles' ), 10, 4 );
		add_action( 'admin_menu', array( $this, 'register_translation_editors_page' ) );
		add_filter( 'set-screen-option', array( $this, 'save_custom_screen_options' ), 10, 3 );

		add_action( 'translation_editor_added', array( $this, 'update_wporg_profile_badge' ) );
		add_action( 'translation_editor_removed', array( $this, 'update_wporg_profile_badge' ) );

		add_action( 'wp_ajax_rosetta-get-projects', array( $this, 'ajax_rosetta_get_projects' ) );
	}

	/**
	 * Registers "(General) Translation Editor" role and modifies editor role.
	 */
	public function role_modifications() {
		if ( ! get_role( self::TRANSLATION_EDITOR_ROLE ) ) {
			add_role( self::TRANSLATION_EDITOR_ROLE, __( 'Translation Editor', 'rosetta' ), array( 'read' => true, 'level_0' => true ) );
		}

		if ( ! get_role( self::GENERAL_TRANSLATION_EDITOR_ROLE ) ) {
			add_role( self::GENERAL_TRANSLATION_EDITOR_ROLE, __( 'General Translation Editor', 'rosetta' ), array( 'read' => true, 'level_0' => true, self::MANAGE_TRANSLATION_EDITORS_CAP => true ) );
		}

		$editor_role = get_role( 'editor' );
		if ( $editor_role && ! $editor_role->has_cap( 'remove_users' ) ) {
			$editor_role->add_cap( 'edit_theme_options' );
			$editor_role->add_cap( 'list_users' );
			$editor_role->add_cap( 'promote_users' );
			$editor_role->add_cap( 'remove_users' );
		}
	}

	/**
	 * Restores the "(General) Translation Editor" role if an user is promoted.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $role      The new role.
	 * @param array  $old_roles An array of the user's previous roles.
	 */
	public function restore_translation_editor_role( $user_id, $role, $old_roles ) {
		if ( self::GENERAL_TRANSLATION_EDITOR_ROLE !== $role && in_array( self::TRANSLATION_EDITOR_ROLE, $old_roles ) ) {
			$user = new WP_User( $user_id );
			$user->add_role( self::TRANSLATION_EDITOR_ROLE );
		}

		if ( self::TRANSLATION_EDITOR_ROLE !== $role && in_array( self::GENERAL_TRANSLATION_EDITOR_ROLE, $old_roles ) ) {
			$user = new WP_User( $user_id );
			$user->add_role( self::GENERAL_TRANSLATION_EDITOR_ROLE );
		}
	}

	/**
	 * Removes "Translation Editor" role and "Administrator" role from
	 * the list of editable roles.
	 *
	 * The list used in wp_dropdown_roles() on users list table.
	 *
	 * @param array $roles List of roles.
	 * @return array Filtered list of editable roles.
	 */
	public function editable_roles( $roles ) {
		unset( $roles[ self::TRANSLATION_EDITOR_ROLE ], $roles[ self::GENERAL_TRANSLATION_EDITOR_ROLE ] );

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
		} elseif ( 'General Translation Editor' === $text ) {
			return __( 'General Translation Editor', 'rosetta' );
		}

		return $translation;
	}

	/**
	 * Registers page for managing translation editors.
	 */
	public function register_translation_editors_page() {
		$this->translation_editors_page = add_menu_page(
			__( 'Translation Editors', 'rosetta' ),
			__( 'Translation Editors', 'rosetta' ),
			self::MANAGE_TRANSLATION_EDITORS_CAP,
			'translation-editors',
			array( $this, 'render_translation_editors_page' ),
			'dashicons-translation',
			71 // After Users
		);

		add_action( 'load-' . $this->translation_editors_page, array( $this, 'load_translation_editors_page' ) );
		add_action( 'load-' . $this->translation_editors_page, array( $this, 'register_screen_options' ) );
		add_action( 'admin_print_scripts-' . $this->translation_editors_page, array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_footer-' . $this->translation_editors_page, array( $this, 'print_js_templates' ) );
		add_action( 'admin_print_styles-' . $this->translation_editors_page, array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueues scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'rosetta-roles', plugins_url( '/js/rosetta-roles.js', __FILE__ ), array( 'jquery', 'wp-backbone' ), '5', true );
	}

	/**
	 * Enqueues styles.
	 */
	public function enqueue_styles() {
		$suffix = is_rtl() ? '-rtl' : '';
		wp_enqueue_style( 'rosetta-roles', plugins_url( "/css/rosetta-roles$suffix.css", __FILE__ ), array(), '3' );
	}

	/**
	 * Prints JavaScript templates.
	 */
	public function print_js_templates() {
		?>
		<script id="tmpl-project-checkbox" type="text/html">
			<# if ( ! data.checkedSubProjects ) {
				#>
				<label>
					<input type="checkbox" class="input-checkbox" name="projects[]" value="{{data.id}}"
					<#
					if ( data.checked ) {
						#> checked="checked"<#
					}
					#>
					/>
					{{data.name}}
					<span class="project-slug">({{data.slug}})</span>
				</label>
			<# } else { #>
				<label>
					<input type="radio" class="input-radio" checked="checked" /> {{data.name}}
					<span class="project-slug">({{data.slug}})</span>
				</label>
			<# } #>
		</script>
		<?php
	}

	/**
	 * Registers a 'per_page' screen option for the list table.
	 */
	public function register_screen_options() {
		$option = 'per_page';
		$args   = array(
			'default' => 10,
			'option'  => 'translation_editors_per_page',
		);
		add_screen_option( $option, $args );
	}

	/**
	 * Adds the 'per_page' screen option to the whitelist so it gets saved.
	 *
	 * @param bool|int $new_value Screen option value. Default false to skip.
	 * @param string   $option    The option name.
	 * @param int      $value     The number of rows to use.
	 * @return bool|int New screen option value.
	 */
	public function save_custom_screen_options( $new_value, $option, $value ) {
		if ( 'translation_editors_per_page' !== $option ) {
			return $new_value;
		}

		$value = (int) $value;
		if ( $value < 1 || $value > 999 ) {
			return $new_value;
		}

		return $value;
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
		$list_table = $this->get_translation_editors_list_table();
		$action = $list_table->current_action();
		$redirect = menu_page_url( 'translation-editors', false );

		if ( $action ) {
			switch ( $action ) {
				case 'add-translation-editor':
					check_admin_referer( 'add-translation-editor', '_nonce_add-translation-editor' );

					if ( ! current_user_can( self::MANAGE_TRANSLATION_EDITORS_CAP ) ) {
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
						$added = add_existing_user_to_blog( array( 'user_id' => $user_details->ID, 'role' => 'subscriber' ) );
						if ( ! $added || is_wp_error( $added ) ) {
							wp_redirect( add_query_arg( array( 'error' => 'not-added-to-site' ), $redirect ) );
							exit;
						}

						// Refresh user data
						$user_details = get_user_by( 'id', $user_details->ID );
					}

					if ( in_array( self::TRANSLATION_EDITOR_ROLE, $user_details->roles ) || in_array( self::GENERAL_TRANSLATION_EDITOR_ROLE, $user_details->roles ) ) {
						wp_redirect( add_query_arg( array( 'error' => 'user-exists' ), $redirect ) );
						exit;
					}

					$projects = empty( $_REQUEST['projects'] ) ? '' : $_REQUEST['projects'];
					if ( 'custom' === $projects ) {
						$this->update_translation_editor( $user_details );

						$redirect = add_query_arg( 'user_id', $user_details->ID, $redirect );
						wp_redirect( add_query_arg( array( 'update' => 'user-added-custom-projects' ), $redirect ) );
						exit;
					} else {
						$this->update_translation_editor( $user_details, array( 'all' ) );

						wp_redirect( add_query_arg( array( 'update' => 'user-added' ), $redirect ) );
						exit;
					}
				case 'remove-translation-editors':
					check_admin_referer( 'bulk-translation-editors' );

					if ( ! current_user_can( self::MANAGE_TRANSLATION_EDITORS_CAP ) ) {
						wp_redirect( $redirect );
						exit;
					}

					if ( empty( $_REQUEST['translation-editors'] ) ) {
						wp_redirect( $redirect );
						exit;
					}

					$count = 0;
					$user_ids = array_map( 'intval', (array) $_REQUEST['translation-editors'] );
					foreach ( $user_ids as $user_id ) {
						$this->remove_translation_editor( $user_id );
						$count++;
					}

					wp_redirect( add_query_arg( array( 'update' => 'user-removed', 'count' => $count ), $redirect ) );
					exit;
				case 'remove-translation-editor':
					check_admin_referer( 'remove-translation-editor' );

					if ( ! current_user_can( self::MANAGE_TRANSLATION_EDITORS_CAP ) ) {
						wp_redirect( $redirect );
						exit;
					}

					if ( empty( $_REQUEST['translation-editor'] ) ) {
						wp_redirect( $redirect );
						exit;
					}

					$user_id = (int) $_REQUEST['translation-editor'];
					$this->remove_translation_editor( $user_id );

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
		$redirect = menu_page_url( 'translation-editors', false );

		if ( ! current_user_can( self::MANAGE_TRANSLATION_EDITORS_CAP ) ) {
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

		if ( ! in_array( self::TRANSLATION_EDITOR_ROLE, $user_details->roles ) && ! in_array( self::GENERAL_TRANSLATION_EDITOR_ROLE, $user_details->roles ) ) {
			wp_redirect( add_query_arg( array( 'error' => 'user-cannot' ), $redirect ) );
			exit;
		}

		$action = empty( $_REQUEST['action'] ) ? '' : $_REQUEST['action'];
		switch ( $action ) {
			case 'update-translation-editor':
				check_admin_referer( 'update-translation-editor_' . $user_details->ID );

				$redirect = add_query_arg( 'user_id', $user_details->ID, $redirect );

				$all_projects = $this->get_translate_projects();
				$all_projects = wp_list_pluck( $all_projects, 'id' );
				$all_projects = array_map( 'intval', $all_projects );

				$projects = (array) $_REQUEST['projects'];
				if ( in_array( 'all', $projects, true ) ) {
					$this->update_translation_editor( $user_details, array( 'all' ) );
				} else {
					$projects = array_map( 'intval', $projects );
					$projects = array_values( array_intersect( $all_projects, $projects ) );
					$this->update_translation_editor( $user_details, $projects );
				}

				wp_redirect( add_query_arg( array( 'update' => 'user-updated' ), $redirect ) );
				exit;
		}
	}

	/**
	 * Removes a translation editor.
	 *
	 * @param int|WP_User $user User ID or object.
	 * @return bool True on success, false on failure.
	 */
	private function remove_translation_editor( $user ) {
		global $wpdb;

		if ( ! $user instanceof WP_User ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! $user->exists() ) {
			return false;
		}

		if ( in_array( self::TRANSLATION_EDITOR_ROLE, $user->roles ) ) {
			$user->remove_role( self::TRANSLATION_EDITOR_ROLE );
		}

		if ( in_array( self::GENERAL_TRANSLATION_EDITOR_ROLE, $user->roles ) ) {
			$user->remove_role( self::GENERAL_TRANSLATION_EDITOR_ROLE );
		}

		$wpdb->query( $wpdb->prepare( "
			DELETE FROM {$wpdb->wporg_translation_editors}
			WHERE `user_id` = %d AND `locale` = %s
		", $user->ID, $this->gp_locale->slug ) );

		do_action( 'translation_editor_removed', $user->ID );

		return true;
	}

	/**
	 * Creates or updates a translation editor.
	 *
	 * @param int|WP_User $user     User ID or object.
	 * @param array       $projects The projects to which the user should get assigned.
	 *                              Pass `array( 'all' )` to make their a general translation
	 *                              editor.
	 * @return bool True on success, false on failure.
	 */
	private function update_translation_editor( $user, $projects = array() ) {
		global $wpdb;

		if ( ! $user instanceof WP_User ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! $user->exists() ) {
			return false;
		}

		$update = in_array( self::TRANSLATION_EDITOR_ROLE, $user->roles ) || in_array( self::GENERAL_TRANSLATION_EDITOR_ROLE, $user->roles );

		$projects = array_map( 'strval', $projects );
		$current_projects = $this->get_users_projects( $user->ID );
		$projects_to_add = $projects_to_remove = array();

		if ( in_array( 'all', $projects, true ) ) {
			$projects_to_remove = array_diff( $current_projects, array( '0' ) );
			if ( ! in_array( '0', $current_projects, true ) ) {
				$projects_to_add[] = '0';
			}

			if ( in_array( self::TRANSLATION_EDITOR_ROLE, $user->roles ) ) {
				$user->remove_role( self::TRANSLATION_EDITOR_ROLE );
			}

			if ( ! in_array( self::GENERAL_TRANSLATION_EDITOR_ROLE, $user->roles ) ) {
				$user->add_role( self::GENERAL_TRANSLATION_EDITOR_ROLE );
			}
		} else {
			$projects_to_remove = array_diff( $current_projects, $projects );
			$projects_to_add = array_diff( $projects, $current_projects );

			if ( in_array( self::GENERAL_TRANSLATION_EDITOR_ROLE, $user->roles ) ) {
				$user->remove_role( self::GENERAL_TRANSLATION_EDITOR_ROLE );
			}

			if ( ! in_array( self::TRANSLATION_EDITOR_ROLE, $user->roles ) ) {
				$user->add_role( self::TRANSLATION_EDITOR_ROLE );
			}
		}

		$values_to_add = array();
		foreach ( $projects_to_add as $project_id ) {
			$values_to_add[] = $wpdb->prepare( '(%d, %d, %s, %s)',
				$user->ID,
				$project_id,
				$this->gp_locale->slug,
				'default'
			);
		}

		if ( $values_to_add ) {
			$wpdb->query( "
				INSERT INTO {$wpdb->wporg_translation_editors}
				(`user_id`,`project_id`, `locale`, `locale_slug`)
				VALUES " . implode( ', ', $values_to_add ) . "
			" );
		}

		$values_to_remove = array_map( 'intval', $projects_to_remove );
		if ( $values_to_remove ) {
			$wpdb->query( $wpdb->prepare( "
				DELETE FROM {$wpdb->wporg_translation_editors}
				WHERE `user_id` = %d AND `locale` = %s
				AND project_id IN (" . implode( ', ', $values_to_remove ) . ")
			", $user->ID, $this->gp_locale->slug ) );
		}

		if ( $update ) {
			do_action( 'translation_editor_updated', $user->ID );
		} else {
			do_action( 'translation_editor_added', $user->ID );
		}

		return true;
	}

	/**
	 * Handles the update of the translation editor badges on
	 * profiles.wordpress.org.
	 *
	 * @param int $user_id User ID.
	 */
	public function update_wporg_profile_badge( $user_id ) {
		$action = 'translation_editor_added' === current_filter() ? 'add' : 'remove';

		$this->notify_profiles_wporg_translation_editor_update( $user_id, $action );
	}

	/**
	 * Retrieves the assigned projects of a user
	 *
	 * @param int $user_id User ID.
	 * @return array List of project IDs.
	 */
	public function get_users_projects( $user_id ) {
		global $wpdb;

		$projects = $wpdb->get_col( $wpdb->prepare( "
			SELECT project_id FROM
			{$wpdb->wporg_translation_editors}
			WHERE user_id = %d AND locale = %s
		", $user_id, $this->gp_locale->slug ) );

		if ( ! $projects ) {
			return array();
		}

		if ( in_array( '0', $projects, true ) ) {
			return array( 'all' );
		}

		return $projects;
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

		$project_access_list = $this->get_users_projects( $user_id );

		$last_updated = $wpdb->get_var( 'SELECT meta_value FROM translate_meta WHERE object_type = "gp_option" AND meta_key = "wporg_projects_last_updated"' );

		wp_localize_script( 'rosetta-roles', '_rosettaProjectsSettings', array(
			'l10n' => array(
				'searchPlaceholder' => esc_attr__( 'Search...', 'rosetta' ),
			),
			'lastUpdated' => $last_updated,
			'accessList' => $project_access_list,
		) );

		$feedback_message = $this->get_feedback_message();

		require __DIR__ . '/views/edit-translation-editor.php';
	}

	/**
	 * Ajax handler for retrieving projects.
	 */
	public function ajax_rosetta_get_projects() {
		$projects = $this->get_translate_projects();
		$project_tree = $this->get_project_tree( $projects, 0, 1 );

		// Sort the tree and remove array keys.
		usort( $project_tree, array( $this, '_sort_name_callback' ) );
		foreach ( $project_tree as $key => $project ) {
			if ( isset( $project->sub_projects ) ) {
				usort( $project->sub_projects, array( $this, '_sort_name_callback' ) );
				$project->sub_projects = array_values( $project->sub_projects );
			}
		}
		$project_tree = array_values( $project_tree );

		ob_start( 'ob_gzhandler' ); // Compress JSON.
		wp_send_json_success( $project_tree );
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
				'no-user-found'     => __( 'The user couldn&#8217;t be found.', 'rosetta' ),
				'not-a-member'      => __( 'The user is not a member of this site.', 'rosetta' ),
				'not-added-to-site' => __( 'The user couldn&#8217;t be added to this site.', 'rosetta' ),
				'user-cannot'       => __( 'The user is not a translation editor.', 'rosetta' ),
				'user-exists'       => __( 'The user is already a translation editor.', 'rosetta' ),
			),
		);

		if ( isset( $_REQUEST['error'], $messages['error'][ $_REQUEST['error'] ] ) ) {
			$message = sprintf(
				'<div class="notice notice-error"><p>%s</p></div>',
				$messages['error'][ $_REQUEST['error'] ]
			);
		} elseif ( isset( $_REQUEST['update'], $messages['update'][ $_REQUEST['update'] ] ) ) {
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
		static $list_table;

		require_once __DIR__ . '/class-translation-editors-list-table.php';

		if ( isset( $list_table ) ) {
			return $list_table;
		}

		$projects = $this->get_translate_projects();
		$project_tree = $this->get_project_tree( $projects, 0, 1 );

		$args = array(
			'user_roles'    => array( self::TRANSLATION_EDITOR_ROLE, self::GENERAL_TRANSLATION_EDITOR_ROLE ),
			'projects'      => $projects,
			'project_tree'  => $project_tree,
			'rosetta_roles' => $this,
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
	private function notify_profiles_wporg_translation_editor_update( $user_id, $action ) {
		$args = array(
			'body' => array(
				'action'      => 'wporg_handle_association',
				'source'      => 'polyglots',
				'command'     => $action,
				'user_id'     => $user_id,
				'association' => 'translation-editor',
			),
		);

		wp_remote_post( self::PROFILES_HANDLER_URL, $args );
	}

	/**
	 * Fetches all projects from translate.wordpress.org.
	 *
	 * @return array List of projects.
	 */
	private function get_translate_projects() {
		global $wpdb;
		static $projects = null;

		if ( null !== $projects ) {
			return $projects;
		}

		$ignore_project_ids = array(
			2804, // Waiting
		);

		$_projects = $wpdb->get_results( "
			SELECT id, name, parent_project_id, slug
			FROM translate_projects
			WHERE id NOT IN( " . implode( ',', $ignore_project_ids ) . " )
			ORDER BY id ASC
		" );

		$projects = array();
		foreach ( $_projects as $project ) {
			$project->name = html_entity_decode( $project->name, ENT_QUOTES, 'UTF-8' );
			$projects[ $project->id ] = $project;
		}

		return $projects;
	}

	/**
	 * Transforms a flat array to a hierarchy tree.
	 *
	 * @param array $projects  The projects.
	 * @param int   $parent_id Optional. Parent ID. Default 0.
	 * @param int   $max_depth Optional. Max depth to avoid endless recursion. Default 5.
	 * @return array The project tree.
	 */
	public function get_project_tree( $projects, $parent_id = 0, $max_depth = 5 ) {
		if ( $max_depth < 0 ) { // Avoid an endless recursion.
			return;
		}

		$tree = array();
		foreach ( $projects as $project ) {
			if ( $project->parent_project_id == $parent_id ) {
				$sub_projects = $this->get_project_tree( $projects, $project->id, $max_depth - 1 );
				if ( $sub_projects ) {
					$project->sub_projects = $sub_projects;
				}

				$tree[ $project->id ] = $project;
			}
		}
		return $tree;
	}

	private function _sort_name_callback( $a, $b ) {
		return strnatcasecmp( $a->name, $b->name );
	}
}
