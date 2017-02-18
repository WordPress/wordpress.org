<?php
/**
 * Class used to implement a user that can approve and import translations in all
 * translation-sets of a project but cannot overwrite current translations by others.
 */
class Cross_Locale_PTE {

	/**
	 * The special locale name.
	 */
	const ALL_LOCALES = 'all-locales';

	/**
	 * The capability the user needs to have to manage Cross-Locale PTEs.
	 */
	const MANAGE_CROSS_LOCALE_PTES_CAP = 'manage_network_users';

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	public static $cache_group = 'wporg-translate';

	/**
	 * The admin page hook suffix.
	 *
	 * @var string
	 */
	private static $admin_page;

	/**
	 * The user that is being administered.
	 *
	 * @var WP_User
	 */
	private static $user;

	/**
	 * Init Admin hooks.
	 */
	public static function init_admin() {
		if ( current_user_can( self::MANAGE_CROSS_LOCALE_PTES_CAP ) ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		}
	}

	/**
	 * Register the Cross-Locale PTE Admin page in wp-admin.
	 */
	public static function register_admin_page() {
		self::$admin_page = add_menu_page(
			__( 'Cross-Locale PTE', 'rosetta' ),
			__( 'Cross-Locale PTE', 'rosetta' ),
			self::MANAGE_CROSS_LOCALE_PTES_CAP,
			'cross-locale-pte',
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-translation',
			71 // After Users.
		);

		add_action( 'load-' . self::$admin_page, array( __CLASS__, 'handle_admin_post' ) );
		add_action( 'admin_print_scripts-' . self::$admin_page, array( 'Rosetta_Roles', 'enqueue_scripts' ) );
		add_action( 'admin_footer-' . self::$admin_page, array( 'Rosetta_Roles', 'print_js_templates' ) );
		add_action( 'admin_print_styles-' . self::$admin_page, array( 'Rosetta_Roles', 'enqueue_styles' ) );
	}

	/**
	 * Handle POST requests for the admin page.
	 */
	public static function handle_admin_post() {
		$redirect = menu_page_url( 'cross-locale-pte', false );

		if ( ! current_user_can( self::MANAGE_CROSS_LOCALE_PTES_CAP ) ) {
			wp_redirect( $redirect );
			exit;
		}

		if ( ! empty( $_REQUEST['user'] ) ) {
			check_admin_referer( 'cross-locale-pte', '_nonce_cross-locale-pte' );

			self::$user = get_user_by( 'login', $_REQUEST['user'] );
			if ( ! self::$user ) {
				self::$user = get_user_by( 'email', $_REQUEST['user'] );
			}

			if ( self::$user ) {
				wp_redirect( add_query_arg( array( 'user_id' => self::$user->ID ), $redirect ) );
			} else {
				wp_redirect( add_query_arg( array( 'error' => 'no-user-found' ), $redirect ) );
			}
			exit;
		}

		if ( ! empty( $_REQUEST['user_id'] ) ) {
			self::$user = get_user_by( 'id', $_REQUEST['user_id'] );
			if ( ! self::$user ) {
				wp_redirect( add_query_arg( array( 'error' => 'no-user-found' ), $redirect ) );
				exit;
			}
		}

		if ( ! empty( $_REQUEST['action'] ) ) {
			switch ( $_REQUEST['action'] ) {
				case 'update-cross-locale-pte':
					check_admin_referer( 'update-cross-locale-pte_' . self::$user->ID );
					return self::update_cross_locale_pte();
			}

			return self::render_edit_page();
		}
	}

	/**
	 * Render the Cross-Locale PTE overview page in the admin.
	 */
	public static function render_admin_page() {
		if ( ! empty( $_REQUEST['user_id'] ) ) {
			return self::render_edit_page( $_REQUEST['user_id'] );
		}

		$feedback_message = '';
		$cross_locale_pte_users = self::get_all_users();
		require __DIR__ . '/views/cross-locale-pte.php';
	}

	/**
	 * Update the projects for the Cross-Locale PTE.
	 */
	public static function update_cross_locale_pte() {
		global $wpdb;

		$projects = array_map( 'strval', explode( ',', $_REQUEST['projects'] ) );
		$current_projects = self::get_users_projects( self::$user->ID );

		$projects_to_remove = array_diff( $current_projects, $projects );
		$projects_to_add = array_diff( $projects, $current_projects );

		$now = current_time( 'mysql', 1 );

		$values_to_add = array();
		foreach ( $projects_to_add as $project_id ) {
			$values_to_add[] = $wpdb->prepare( '(%d, %d, %s, %s)',
				self::$user->ID,
				$project_id,
				self::ALL_LOCALES,
				$now
			);
		}

		if ( $values_to_add ) {
			$wpdb->query( "
				INSERT INTO {$wpdb->wporg_translation_editors}
				( `user_id`,`project_id`, `locale`, `date_added` )
				VALUES " . implode( ', ', $values_to_add )
			);
		}

		$values_to_remove = array_map( 'intval', $projects_to_remove );
		if ( $values_to_remove ) {
			$wpdb->query( $wpdb->prepare( "
				DELETE FROM {$wpdb->wporg_translation_editors}
				WHERE `user_id` = %d AND `locale` = %s
				AND project_id IN (" . implode( ', ', $values_to_remove ) . ')',
			self::$user->ID, self::ALL_LOCALES ) );
		}

		wp_redirect( add_query_arg( array( 'user_id' => self::$user->ID ), menu_page_url( 'cross-locale-pte', false ) ) );
		exit;
	}

	/**
	 * Render the page to edit a single Cross-Locale PTE.
	 */
	public static function render_edit_page() {
		$user = self::$user;
		$project_access_list = self::get_users_projects( $user->ID );
		$last_updated = get_blog_option( WPORG_TRANSLATE_BLOGID, 'wporg_projects_last_updated' );

		wp_localize_script( 'rosetta-roles', '_rosettaProjectsSettings', array(
			'l10n' => array(
				'searchPlaceholder' => esc_attr__( 'Search...', 'rosetta' ),
			),
			'lastUpdated' => $last_updated,
			'accessList' => $project_access_list,
		) );

		$feedback_message = '';
		require __DIR__ . '/views/edit-cross-locale-pte.php';
	}

	/**
	 * Retrieves the projects for which a user has cross-locale PTE permissions.
	 *
	 * @param int $user_id User ID.
	 * @return array List of project IDs.
	 */
	public static function get_users_projects( $user_id ) {
		global $wpdb;

		$projects = $wpdb->get_col( $wpdb->prepare( "
			SELECT project_id FROM
			{$wpdb->wporg_translation_editors}
			WHERE user_id = %d AND locale = %s
		", $user_id, self::ALL_LOCALES ) );

		return $projects;
	}
	/**
	 * Retrieves the projects for which a user has cross-locale PTE permissions.
	 *
	 * @return array List of User IDs.
	 */
	public static function get_all_users() {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( "
			SELECT te.user_id, te.project_id, p.name AS project_name FROM
			{$wpdb->wporg_translation_editors} te
			JOIN translate_projects p ON te.project_id = p.id
			WHERE te.locale = %s
		", self::ALL_LOCALES ) );

		$user_ids = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $user_ids[ $row->user_id ] ) ) {
				$user = get_user_by( 'id', $row->user_id );
				if ( ! $user ) {
					continue;
				}
				$row->user_login = $user->user_login;
				$row->email = $user->user_email;
				$row->display_name = $user->display_name;
				$row->projects = array( $row->project_id => $row->project_name );
				$user_ids[ $row->user_id ] = $row;
			} else {
				$user_ids[ $row->user_id ]->projects[ $row->project_id ] = $row->project_name;
			}
		}

		return $user_ids;
	}

	/**
	 * Check for the Cross-Locale PTE permission for the project.
	 *
	 * @param WP_User $user   The user.
	 * @param int $project_id The Project ID.
	 * @return string|bool The verdict.
	 */
	public static function user_has_cross_locale_permission( $user, $project_id ) {
		static $cache = null;

		if ( null === $cache ) {
			$cache = array();
		}

		$user_id = intval( $user->ID );
		$project_id = intval( $project_id );

		if ( isset( $cache[ $user_id ][ $project_id ] ) ) {
			return $cache[ $user_id ][ $project_id ];
		}

		if ( ! isset( $cache[ $user_id ] ) ) {
			$cache[ $user_id ] = array();
		}

		global $wpdb;
		$result = $wpdb->get_col( $wpdb->prepare( "
			SELECT te.user_id FROM
			{$wpdb->wporg_translation_editors} te
			JOIN translate_projects p ON ( te.project_id = p.id OR te.project_id = p.parent_project_id )
			WHERE te.user_id = %d AND p.id = %d AND te.locale = %s
		", $user_id, $project_id, self::ALL_LOCALES ) );

		if ( $result && intval( $result[0] ) === $user_id ) {
			return $cache[ $user_id ][ $project_id ] = true;
		}

		return $cache[ $user_id ][ $project_id ] = false;
	}

	/**
	 * Enforce not-overwriting current translation by others while importing.
	 *
	 * @param string         $status          The desired status.
	 * @param GP_Translation $new_translation The new translation.
	 * @param GP_Translation $old_translation The old translation.
	 * @return string The new status.
	 */
	public static function gp_translation_set_import_status( $status, $new_translation, $old_translation ) {
		if ( ! isset( $old_translation->translation_set_id ) ) {
			return $status;
		}

		if ( 'current' !== $old_translation->translation_status ) {
			return $status;
		}

		if ( GP::$permission->current_user_can( 'cross-pte', 'translation-set', $old_translation->translation_set_id ) ) {
			// Set to waiting if a current translation exists by another user.
			if ( intval( $old_translation->user_id ) !== intval( get_current_user_id() ) ) {
				return 'waiting';
			}
		}
		return $status;
	}

	/**
	 * The GlotPress filter for Cross-Locale PTE.
	 *
	 * A Cross-Locale PTE is defined through an entry in the permission table 'cross-pte' with the
	 * object_id referring to a project id.
	 * A user with this permission will have 'approve' rights for all translation-sets within this
	 * project. Usually having approval rights for a translation-set also means that the user has
	 * approval rights for all translations, but not a Cross-Locale PTE:
	 * If a current translation exists by another user then overwriting (through UI or import) is not
	 * possible.
	 *
	 * @param string|bool $verdict The verdict from an earlier filter.
	 * @param array       $args    Arguments that describe the object to judge for.
	 * @return string|bool The verdict for the object.
	 */
	public static function gp_pre_can_user( $verdict, $args ) {
		if ( 'cross-pte' === $args['action'] ) {
			$verdict = self::gp_pre_can_user_cross_pte( $verdict, $args );

			if ( is_bool( $verdict ) ) {
				return $verdict;
			}
		}

		if ( 'approve' === $args['action'] ) {
			if ( 'translation' === $args['object_type'] ) {
				$verdict = self::gp_pre_can_user_approve_translation( $verdict, $args );
			} elseif ( 'translation-set' === $args['object_type'] ) {
				$verdict = self::gp_pre_can_user_approve_translation_set( $verdict, $args );
			}
		}

		return $verdict;

	}

	/**
	 * A GlotPress sub-filter for the permission 'cross-lte'.
	 *
	 * @param string|bool $verdict The verdict from an earlier filter.
	 * @param array       $args    Arguments that describe the object to judge for.
	 * @return string|bool The verdict for the object.
	 */
	public static function gp_pre_can_user_cross_pte( $verdict, $args ) {
		if ( GP::$permission->user_can( $args['user'], 'admin' ) ) {
			// Admins shouldn't have this because it will end up restricting them.
			return false;
		}

		if ( 'translation-set' === $args['object_type'] ) {
			if ( isset( $args['extra']['set']->id ) && intval( $args['extra']['set']->id ) === intval( $args['object_id'] ) ) {
				$set = $args['extra']['set'];
			} else {
				$set = GP::$translation_set->get( $args['object_id'] );
			}

			// Allow on all translation-sets within the project.
			if ( $set ) {
				return GP::$permission->user_can( $args['user'], 'cross-pte', 'project', $set->project_id );
			}
		} elseif ( 'project' === $args['object_type'] ) {
			return self::user_has_cross_locale_permission( $args['user'], $args['object_id'] );
		}

		return $verdict;
	}

	/**
	 * A GlotPress sub-filter for the permission 'approve' and object 'translation'.
	 *
	 * @param string|bool $verdict The verdict from an earlier filter.
	 * @param array       $args    Arguments that describe the object to judge for.
	 * @return string|bool The verdict for the object.
	 */
	public static function gp_pre_can_user_approve_translation( $verdict, $args ) {
		if ( isset( $args['extra']['translation']->translation_set_id ) && intval( $args['extra']['translation']->id ) === intval( $args['object_id'] ) ) {
			$translation = $args['extra']['translation'];
		} else {
			$translation = GP::$translation->get( $args['object_id'] );
		}

		if ( ! $translation ) {
			return $verdict;
		}

		static $current_translation_by_user;
		$cache_key = $args['user']->ID . '_' . $translation->original_id;

		if ( isset( $current_translation_by_user[ $cache_key ] ) ) {
			return $current_translation_by_user[ $cache_key ];
		}

		if ( GP::$permission->user_can( $args['user'], 'cross-pte', 'translation-set', $translation->translation_set_id ) ) {
			$current_translation = GP::$translation->find_one( array( 'translation_set_id' => $translation->translation_set_id, 'original_id' => $translation->original_id, 'status' => 'current' ) );
			if ( $current_translation && intval( $current_translation->user_id ) !== $args['user']->ID ) {
				// Current translation was authored by someone else. Disallow setting to current.
				return $current_translation_by_user[ $cache_key ] = false;
			}

			// No current translation exists or it was translated by me: allow.
			return $current_translation_by_user[ $cache_key ] = true;
		}

		// Allows usage of the re-implementation below.
		if ( GP::$permission->user_can( $args['user'], 'approve', 'translation-set', $translation->translation_set_id ) ) {
			return true;
		}

		return $verdict;
	}

	/**
	 * A GlotPress sub-filter for the permission 'approve' and object 'translation-set'.
	 *
	 * @param string|bool $verdict The verdict from an earlier filter.
	 * @param array       $args    Arguments that describe the object to judge for.
	 * @return string|bool The verdict for the object.
	 */
	public static function gp_pre_can_user_approve_translation_set( $verdict, $args ) {
		// Re-implementation of gp_route_translation_set_permissions_to_validator_permissions().
		if ( isset( $args['extra']['set']->id ) && intval( $args['extra']['set']->id ) === intval( $args['object_id'] ) ) {
			$set = $args['extra']['set'];
		} else {
			$set = GP::$translation_set->get( $args['object_id'] );
		}

		if ( $set ) {
			if ( GP::$permission->user_can( $args['user'], 'cross-pte', 'project', $set->project_id ) ) {
				return true;
			}

			return GP::$permission->user_can( $args['user'], 'approve', GP::$validator_permission->object_type, GP::$validator_permission->object_id( $set->project_id, $set->locale, $set->slug ) );
		}

		return $verdict;
	}
}
