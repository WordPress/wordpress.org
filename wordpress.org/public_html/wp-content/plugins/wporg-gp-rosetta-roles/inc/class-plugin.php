<?php

namespace WordPressdotorg\GlotPress\Rosetta_Roles;

use GP;
use GP_Locales;

class Plugin {

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	public static $cache_group = 'wporg-translate';

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
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Returns always the same instance of this plugin.
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
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		$GLOBALS['wpdb']->wporg_translation_editors = self::TRANSLATION_EDITORS_TABLE;

		add_filter( 'gp_pre_can_user', array( $this, 'pre_can_user' ), 9 , 2 );
		add_action( 'gp_project_created', array( $this, 'project_created' ) );
		add_action( 'gp_project_saved', array( $this, 'project_saved' ) );

		if ( is_admin() ) {
			$users = new Admin\Translators();
			add_action( 'admin_menu', [ $users, 'register_page' ] );
		}
	}

	/**
	 * Filter to check if the current user has permissions to approve strings, based
	 * on a role on the Rosetta site.
	 *
	 * @param string $verdict Verdict.
	 * @param array  $args    Array of arguments.
	 * @return bool True if user has permissions, false if not.
	 */
	public function pre_can_user( $verdict, $args ) {
		if ( 'delete' === $args['action'] ) {
			return false;
		}

		// Administrators on global.wordpress.org are considered global admins in GlotPress.
		if ( $this->is_global_administrator( $args['user_id'] ) ) {
			return true;
		}

		if ( 'approve' !== $args['action'] || ! in_array( $args['object_type'], array( 'project|locale|set-slug', 'translation-set' ) ) ) {
			return false;
		}

		// Get locale and current project ID.
		$locale_and_project_id = (object) $this->get_locale_and_project_id( $args['object_type'], $args['object_id'] );
		if ( ! $locale_and_project_id ) {
			return false;
		}

		$locale_slug = $locale_and_project_id->locale;
		$current_project_id = $locale_and_project_id->project_id;

		// Simple check to see if they're an approver or not.
		if ( ! $this->is_approver_for_locale( $args['user_id'], $locale_slug ) ) {
			return false;
		}

		// Grab the list of Projects (or 'all') that the user can approve.
		$project_access_list = $this->get_project_id_access_list( $args['user_id'], $locale_slug );
		if ( ! $project_access_list ) {
			return false;
		}

		// Short circuit the check if user can approve all projects.
		if ( in_array( 'all', $project_access_list ) ) {
			return true;
		}

		// If current project is a parent ID.
		if ( in_array( $current_project_id, $project_access_list ) ) {
			return true;
		}

		// A user is allowed to approve sub projects as well.
		$project_access_list = $this->get_project_id_access_list( $args['user_id'], $locale_slug, /* $include_children = */ true );
		if ( in_array( $current_project_id, $project_access_list ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Callback for when a project is created.
	 */
	public function project_created() {
		$this->clear_project_cache();
	}

	/**
	 * Callback for when a project is saved.
	 */
	public function project_saved() {
		$this->clear_project_cache();
	}

	/**
	 * Determines if a given user is a Global Admin.
	 *
	 * Users present as an administrator on global.wordpress.org are treated as a
	 * global administrator in GlotPress.
	 *
	 * @param int $user_id User ID.
	 * @return bool True, if user is an admin, false if not.
	 */
	public function is_global_administrator( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		// 115 = global.wordpress.org. Administrators on this site are considered global admins in GlotPress.
		if ( ! empty( $user->wporg_115_capabilities ) && is_array( $user->wporg_115_capabilities ) && ! empty( $user->wporg_115_capabilities['administrator'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines if a given user is a Translation Approver for a Locale.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $locale_slug The Locale for which we are checking.
	 * @return bool True, if user is an approver, false if not.
	 */
	public function is_approver_for_locale( $user_id, $locale_slug ) {
		static $cache = null;

		if ( null === $cache ) {
			$cache = array();
		}

		if ( isset( $cache[ $user_id ][ $locale_slug ] ) ) {
			return $cache[ $user_id ][ $locale_slug ];
		}

		if ( ! isset( $cache[ $user_id ] ) ) {
			$cache[ $user_id ] = array();
		}

		// Get blog prefix of the associated Rosetta site.
		if ( ! $blog_prefix = $this->get_blog_prefix( $locale_slug ) ) {
			$cache[ $user_id ][ $locale_slug ] = false;
			return false;
		}

		$user = get_user_by( 'id', $user_id );

		$cap_key = $blog_prefix . 'capabilities';
		if ( ! isset( $user->{$cap_key} ) ) {
			$cache[ $user_id ][ $locale_slug ] = false;
			return false;
		}

		$capabilities = $user->{$cap_key};
		$is_approver = ! empty( $capabilities[ self::TRANSLATION_EDITOR_ROLE ] ) || ! empty( $capabilities[ self::GENERAL_TRANSLATION_EDITOR_ROLE ] );
		$cache[ $user_id ][ $locale_slug ] = $is_approver;

		return $is_approver;
	}

	/**
	 * Retrieves a list of project ID's which a user can approve for.
	 *
	 * This is likely to be incorrrect in the event that the user is a Translation Editor or Global Admin.
	 * The array item 'all' is special, which means to allow access to all projects.
	 *
	 * @param int    $user_id          User ID.
	 * @param string $locale_slug      The Locale for which we are checking.
	 * @param bool   $include_children Whether to include the children project ID's in the return.
	 * @return array A list of the Project ID's for which the current user can approve translations for.
	 */
	public function get_project_id_access_list( $user_id, $locale_slug, $include_children = false ) {
		global $wpdb;
		static $cache = null;

		if ( null === $cache ) {
			$cache = array();
		}

		if ( isset( $cache[ $user_id ][ $locale_slug ] ) ) {
			$project_access_list = $cache[ $user_id ][ $locale_slug ];
		} else {
			$project_access_list = $wpdb->get_col( $wpdb->prepare( "
				SELECT project_id FROM
				{$wpdb->wporg_translation_editors}
				WHERE user_id = %d AND locale = %s
			", $user_id, $locale_slug ) );

			if ( ! isset( $cache[ $user_id ] ) ) {
				$cache[ $user_id ] = array();
			}

			$cache[ $user_id ][ $locale_slug ] = $project_access_list;
		}

		if ( ! $project_access_list ) {
			return array();
		}

		if ( in_array( '0', $project_access_list, true ) ) {
			$project_access_list = array( 'all' );
		}

		// If we don't want the children, or the user has access to all projects.
		if ( ! $include_children || in_array( 'all', $project_access_list ) ) {
			return $project_access_list;
		}

		// A user is allowed to approve sub projects as well.
		$allowed_sub_project_ids = array();
		foreach ( $project_access_list as $project_id ) {
			if ( 'all' === $project_id ) {
				continue;
			}
			$sub_project_ids = $this->get_sub_project_ids( $project_id );
			if ( $sub_project_ids ) {
				$allowed_sub_project_ids = array_merge( $allowed_sub_project_ids, $sub_project_ids );
			}
		}

		// $project_access_list contains parent project IDs, merge them with the sub-project IDs.
		$project_access_list = array_merge( $project_access_list, $allowed_sub_project_ids );

		return $project_access_list;
	}

	/**
	 * Fetches all projects from database.
	 *
	 * @return array List of projects with ID and parent ID.
	 */
	public function get_all_projects() {
		global $wpdb;
		static $projects = null;

		if ( null !== $projects ) {
			return $projects;
		}

		$_projects = $wpdb->get_results( "
			SELECT
				id, parent_project_id
			FROM {$wpdb->gp_projects}
			ORDER BY id
		" );

		$projects = array();
		foreach ( $_projects as $project ) {
			$project->sub_projects = array();
			$projects[ $project->id ] = $project;
		}

		return $projects;
	}

	/**
	 * Returns projects as a hierarchy tree.
	 *
	 * @return array The project tree.
	 */
	public function get_project_tree() {
		static $project_tree = null;

		if ( null !== $project_tree ) {
			return $project_tree;
		}

		$projects = $this->get_all_projects();

		$project_tree = array();
		foreach ( $projects as $project_id => $project ) {
			$projects[ $project->parent_project_id ]->sub_projects[ $project_id ] = &$projects[ $project_id ];
			if ( ! $project->parent_project_id ) {
				$project_tree[ $project_id ] = &$projects[ $project_id ];
			}
		}

		return $project_tree;
	}

	/**
	 * Returns all sub project IDs of a parent ID.
	 *
	 * @param int $project_id Parent ID.
	 * @return array IDs of the sub projects.
	 */
	public function get_sub_project_ids( $project_id ) {
		$cache_key = 'project:' . $project_id . ':childs';
		$cache = wp_cache_get( $cache_key, self::$cache_group );
		if ( false !== $cache ) {
			return $cache;
		}

		$project_tree = $this->get_project_tree();
		$project_branch = $this->get_project_branch( $project_id, $project_tree );

		$project_ids = array();
		if ( isset( $project_branch->sub_projects ) ) {
			$project_ids = Utils::array_keys_multi( $project_branch->sub_projects, 'sub_projects' );
		}

		wp_cache_set( $cache_key, $project_ids, self::$cache_group );

		return $project_ids;
	}

	/**
	 * Returns a specific branch of a hierarchy tree.
	 *
	 * @param int   $project_id Project ID.
	 * @param array $projects   Hierarchy tree of projects.
	 * @return mixed False if project ID doesn't exist, project branch on success.
	 */
	public function get_project_branch( $project_id, $projects ) {
		if ( ! is_array( $projects ) ) {
			return false;
		}

		foreach ( $projects as $project ) {
			if ( $project->id == $project_id ) {
				return $project;
			}

			if ( isset( $project->sub_projects ) ) {
				$sub = $this->get_project_branch( $project_id, $project->sub_projects );
				if ( $sub ) {
					return $sub;
				}
			}
		}

		return false;
	}

	/**
	 * Removes all of the project ids from the cache.
	 */
	public function clear_project_cache() {
		$projects = $this->get_all_projects();

		foreach ( $projects as $project ) {
			$cache_key = 'project:' . $project->id . ':childs';
			wp_cache_delete( $cache_key, self::$cache_group );
		}
	}

	/**
	 * Extracts project ID and locale slug from object type and ID.
	 *
	 * @param string $object_type Current object type.
	 * @param string $object_id   Current object ID.
	 * @return array|false Locale and project ID, false on failure.
	 */
	public function get_locale_and_project_id( $object_type, $object_id ) {
		switch ( $object_type ) {
			case 'translation-set' :
				$set = GP::$translation_set->get( $object_id );
				return array( 'locale' => $set->locale, 'project_id' => (int) $set->project_id );

			case 'project|locale|set-slug' :
				list( $project_id, $locale ) = explode( '|', $object_id );
				return array( 'locale' => $locale, 'project_id' => (int) $project_id );
		}
		return false;
	}

	/**
	 * Returns the blog prefix of a locale.
	 *
	 * @param string $locale_slug Slug of GlotPress locale.
	 * @return bool|string Blog prefix on success, false on failure.
	 */
	public function get_blog_prefix( $locale_slug ) {
		global $wpdb;
		static $ros_locale_assoc;

		$gp_locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $gp_locale || ! isset( $gp_locale->wp_locale ) ) {
			return false;
		}

		$wp_locale = $gp_locale->wp_locale;

		if ( ! isset( $ros_locale_assoc ) ) {
			$ros_locale_assoc = $wpdb->get_results( 'SELECT locale, subdomain FROM locales', OBJECT_K );
		}

		if ( isset( $ros_locale_assoc[ $wp_locale ] ) ) {
			$subdomain = $ros_locale_assoc[ $wp_locale ]->subdomain;
		} else {
			return false;
		}

		$result = get_sites( [
			'network_id' => get_current_network_id(),
			'domain'     => "$subdomain.wordpress.org",
			'path'       => '/',
			'number'     => 1,
		] );
		$site = array_shift( $result );

		if ( $site ) {
			return 'wporg_' . $site->blog_id . '_';
		}

		return false;
	}
}
