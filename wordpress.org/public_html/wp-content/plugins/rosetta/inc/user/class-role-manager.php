<?php
namespace WordPressdotorg\Rosetta\User;

use WordPressdotorg\Rosetta\User\Role\Role;
use WP_Error;

class Role_Manager {

	/**
	 * Array of roles, keyed by role name.
	 *
	 * @var Role[]
	 */
	private $roles;

	/**
	 * Array of roles, keyed by display name.
	 *
	 * @var Role[]
	 */
	private $roles_by_display_name;

	/**
	 * Array of role names, which are not editable.
	 *
	 * @var array
	 */
	private $non_editable_roles;

	/**
	 * Constructor.
	 *
	 * @param array $roles An array of roles.
	 */
	public function __construct( array $roles = [] ) {
		foreach ( $roles as $role ) {
			$this->add_role( $role );
		}
	}

	/**
	 * Adds a role to the manager.
	 *
	 * @param Role $role The role.
	 */
	public function add_role( Role $role ) {
		$this->roles[ $role::get_name() ] = $role;
		$this->roles_by_display_name[ $role::get_display_name() ] = $role;
		if ( ! $role::is_editable_role() ) {
			$this->non_editable_roles[] = $role::get_name();
		}
	}

	/**
	 * Checks status of roles and registers the filters.
	 */
	public function setup() {
		if ( ! $this->roles ) {
			return;
		}

		foreach ( $this->roles as $role ) {
			$role_status = $this->role_exists( $role );
			if ( is_wp_error( $role_status ) ) {
				switch ( $role_status->get_error_code() ) {
					case 'role_does_not_exist' :
						$this->create_role( $role );
						break;
					case 'outdated_role_exists' :
						$this->upgrade_role( $role );
						break;
				}
			}
		}

		add_filter( 'user_has_cap', [ $this, 'add_dynamic_capabilities' ], 10, 4 );
		add_filter( 'gettext_with_context', [ $this, 'translate_user_roles' ], 10, 4 );
		add_filter( 'editable_roles', [ $this, 'filter_editable_roles' ] );
	}

	/**
	 * Checks the status of a role.
	 *
	 * @param Role $role The role to check.
	 *
	 * @return true|WP_Error True if role exists, WP_Error if role doesn't exist ('role_does_not_exist')
	 *                       or role needs an update ('outdated_role_exists').
	 */
	public function role_exists( Role $role ) {
		$existing_role = get_role( $role::get_name() );

		if ( null === $existing_role ) {
			return new WP_Error( 'role_does_not_exist' );
		}

		$current_caps = $existing_role->capabilities;
		$role_caps    = $role::get_capabilities();

		ksort( $current_caps );
		ksort( $role_caps );

		if ( $current_caps !== $role_caps ) {
			return new WP_Error( 'outdated_role_exists' );
		}

		return true;
	}

	/**
	 * Upgrades an existing roles.
	 *
	 * @param Role $role The role to upgrade.
	 *
	 * @return true|WP_Error True on success, WP_Error if role doesn't exist.
	 */
	public function upgrade_role( Role $role ) {
		$role_object = get_role( $role::get_name() );

		if ( null === $role_object ) {
			return new WP_Error( 'role_does_not_exists' );
		}

		$old_caps = array_keys( $role_object->capabilities );
		$new_caps = array_keys( $role::get_capabilities() );

		$removed_caps = array_diff( $old_caps, $new_caps );
		$added_caps   = array_diff( $new_caps, $old_caps );

		foreach ( $removed_caps as $removed_cap ) {
			$role_object->remove_cap( $removed_cap );
		}

		foreach ( $added_caps as $added_cap ) {
			$role_object->add_cap( $added_cap );
		}

		return true;
	}

	/**
	 * Creates a new role.
	 *
	 * @param Role $role The role to create.
	 *
	 * @return WP_Error|\WP_Role WP_Error if role exists, WP_Role if role was created.
	 */
	public function create_role( Role $role ) {
		$existing_role = get_role( $role::get_name() );

		if ( null !== $existing_role ) {
			return new WP_Error( 'role_exists' );
		}

		return add_role( $role::get_name(), $role::get_display_name(), $role::get_capabilities() );
	}

	/**
	 * Translates the display name of a role.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Text to translate.
	 * @param string $context     Context information for the translators.
	 * @param string $domain      Text domain.
	 * @return string Translated user role.
	 */
	public function translate_user_roles( $translation, $text, $context, $domain ) {
		if ( 'default' !== $domain || 'User role' !== $context ) {
			return $translation;
		}

		if ( isset( $this->roles_by_display_name[ $text ] ) ) {
			$role = $this->roles_by_display_name[ $text ];
			return $role::get_display_name( true /* $translated */ );
		}

		return $translation;
	}

	/**
	 * Filters user's capabilities.
	 *
	 * @param array   $allcaps An array of all the user's capabilities.
	 * @param array   $caps    Actual capabilities for meta capability.
	 * @param array   $args    Optional parameters passed to has_cap(), typically object ID.
	 * @param WP_User $user    The user object.
	 * @return array An array of user's capabilities.
	 */
	public function add_dynamic_capabilities( $allcaps, $caps, $args, $user ) {
		foreach ( $user->roles as $user_role ) {
			if ( isset( $this->roles[ $user_role ] ) ) {
				$role = $this->roles[ $user_role ];
				$allcaps = array_merge( $allcaps, $role::get_dynamic_capabilities() );
			}
		}

		return $allcaps;
	}

	/**
	 * Filters list of editable roles.
	 *
	 * @see get_editable_roles()
	 *
	 * @param array $roles Array of roles.
	 * @return array Filtered array of roles.
	 */
	public function filter_editable_roles( $roles ) {
		if ( empty( $this->non_editable_roles ) ) {
			return $roles;
		}

		return array_diff_key( $roles, array_flip( $this->non_editable_roles ) );
	}
}
