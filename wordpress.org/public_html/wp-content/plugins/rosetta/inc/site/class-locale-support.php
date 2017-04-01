<?php
namespace WordPressdotorg\Rosetta\Site;

use WP_Site;
use WP_User;

class Locale_Support implements Site {

	/**
	 * Domain of this site.
	 *
	 * @var string
	 */
	public static $domain = '#[a-z-]{2,5}\.wordpress\.org#';

	/**
	 * Path of this site.
	 *
	 * @var string
	 */
	public static $path = '/support/';

	/**
	 * Tests whether this site manager is eligible for a site.
	 *
	 * @param \WP_Site $site The site object.
	 * @return bool True if site is eligible, false otherwise.
	 */
	public static function test( WP_Site $site ) {
		if ( self::$path === $site->path ) {
			return true;
		}

		return false;
	}

	/**
	 * Registers actions and filters.
	 */
	public function register_events() {
		add_action( 'bbp_loaded', [ $this, 'initialize_bbpress_customizations' ] );
	}

	/**
	 * Initializes customizations for bbPress.
	 */
	public function initialize_bbpress_customizations() {
		if ( is_admin() ) {
			add_action( 'bbp_init', [ $this, 'set_minimum_capability_for_bbpress' ], 11 ); // after add_action( 'bbp_init', 'bbp_admin' );
		}

		add_filter( 'user_has_cap', [ $this, 'extend_bbpress_roles' ], 10, 4 );
		add_filter( 'editable_roles', [ $this, 'limit_editable_roles' ] );
		add_action( 'set_user_role', [ $this, 'restore_bbpress_role_on_bulk_edit' ], 10, 3 );
	}

	/**
	 * Only allow super admins to access tools and settings of bbPress.
	 * Default is 'keep_gate' for any keymaster.
	 */
	public function set_minimum_capability_for_bbpress() {
		if ( ! is_super_admin() ) {
			bbpress()->admin->minimum_capability = 'do_not_allow';
		}
	}

	/**
	 * Restores the bbPress role if an user has been promoted via bulk action.
	 *
	 * @link https://bbpress.trac.wordpress.org/ticket/2597
	 * @link https://core.trac.wordpress.org/ticket/17924
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $role      The new role.
	 * @param array  $old_roles An array of the user's previous roles.
	 */
	public function restore_bbpress_role_on_bulk_edit( $user_id, $role, $old_roles ) {
		$bbp_roles = array_keys( bbp_get_dynamic_roles() );

		// Do nothing if the new role is a bbPress role or if the user had no bbPress role.
		if ( in_array( $role, $bbp_roles ) || ! array_intersect( $old_roles, $bbp_roles ) ) {
			return;
		}

		$user = new WP_User( $user_id );
		foreach ( $old_roles as $old_role ) {
			// Add only bbPress roles.
			if ( ! in_array( $old_role, $bbp_roles ) ) {
				continue;
			}
			$user->add_role( $old_role );
		}
	}

	/**
	 * Extends capabilities for keymasters and moderators to allow access to the
	 * admin and to manage users and pages.
	 *
	 * @param array    $allcaps An array of all the user's capabilities.
	 * @param array    $caps    Actual capabilities for meta capability.
	 * @param array    $args    Optional parameters passed to has_cap(), typically object ID.
	 * @param \WP_User $user    The user object.
	 * @return array Extended capabilities
	 */
	public function extend_bbpress_roles( $allcaps, $caps, $args, $user ) {
		if ( in_array( bbp_get_keymaster_role(), $user->roles, true ) ) {
			$extra_caps = [
				// Access dashboard.
				'read'                   => true,
				// Manage users.
				'list_users'             => true,
				'promote_users'          => true,
				'remove_users'           => true,
				// Manage pages.
				'edit_pages'             => true,
				'edit_others_pages'      => true,
				'edit_published_pages'   => true,
				'publish_pages'          => true,
				'delete_pages'           => true,
				'delete_others_pages'    => true,
				'delete_published_pages' => true,
				'delete_private_pages'   => true,
				'edit_private_pages'     => true,
				'read_private_pages'     => true,
			];

			$allcaps = array_merge( $allcaps, $extra_caps );
		} elseif ( in_array( bbp_get_moderator_role(), $user->roles, true ) ) {
			$extra_caps = [
				// Access dashboard.
				'read'               => true,
				// Manage pages.
				'read_private_pages' => true,
			];

			$allcaps = array_merge( $allcaps, $extra_caps );
		}

		return $allcaps;
	}

	/**
	 * Filters the list of editable roles.
	 *
	 * Non-super admins can promote subscribers and administrators,
	 * others only subscribers.
	 *
	 * @param array $roles List of roles.
	 * @return array Filtered ist of roles.
	 */
	public function limit_editable_roles( $roles ) {
		if ( ! is_super_admin() ) {
			return [ 'subscriber' => $roles['subscriber'] ];
		}

		$roles = array_intersect_key( $roles, array_flip( [ 'subscriber', 'administrator' ] ) );

		return $roles;
	}
}
