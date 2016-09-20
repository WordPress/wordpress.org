<?php
namespace WordPressdotorg\Rosetta\Site;

use WordPressdotorg\Rosetta\Filter;
use WordPressdotorg\Rosetta\Jetpack;
use WordPressdotorg\Rosetta\User;
use WordPressdotorg\Rosetta\User\Role;
use WP_Site;
use WP_User;

class Locale_Main implements Site {

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
	public static $path = '/';

	/**
	 * Tests whether this site manager is eligible for a site.
	 *
	 * @param WP_Site $site The site object.
	 *
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
		if ( is_admin() ) {
			// Get the team site.
			$result = get_sites( [
				'domain' => get_site()->domain,
				'path'   => Locale_Team::$path,
				'number' => 1,
			] );
			$team_site = array_shift( $result );

			if ( $team_site ) {
				$user_sync = new User\Sync();
				$user_sync->set_destination_site( $team_site );
				$user_sync->set_roles_to_sync( [
					'editor' => 'editor',
					Role\Locale_Manager::get_name() => 'editor',
				] );
				$user_sync->setup();
			}
		}

		$this->initialize_jetpack_customizations();
		$this->initialize_user_role_customizations();
	}

	/**
	 * Initializes customizations for Jetpack.
	 */
	private function initialize_jetpack_customizations() {
		$jetpack_module_manager = new Jetpack\Module_Manager( [
			'stats',
			'videopress',
			'contact-form',
			'sharedaddy',
			'shortcodes',
		] );
		$jetpack_module_manager->setup();

		// Options for Jetpack's sharing module.
		$options = new Filter\Options();
		$options->add_option(
			( new Filter\Option() )
				->set_name( 'sharing-options' )
				->set_callback( function() {
					return [
						'global' => [
							'button_style'  => 'icon-text',
							'sharing_label' => __( 'Share this:', 'rosetta' ),
							'open_links'    => 'same',
							'show'          => [ 'post' ],
							'custom'        => [],
						],
					];
				} )
		);
		$options->add_option(
			( new Filter\Option() )
				->set_name( 'sharing-services' )
				->set_callback( function() {
					return [
						'visible' => [ 'facebook', 'twitter', 'google-plus-1', 'email' ],
						'hidden'  => [],
					];
				} )
		);
		$options->setup();
	}

	/**
	 * Initializes user role customizations.
	 */
	private function initialize_user_role_customizations() {
		$role_manager = new User\Role_Manager();
		$role_manager->add_role( new Role\Locale_Manager() );
		$role_manager->add_role( new Role\General_Translation_Editor() );
		$role_manager->add_role( new Role\Translation_Editor() );
		$role_manager->setup();

		add_action( 'set_user_role', [ $this, 'restore_translation_editor_role' ], 10, 3 );
		add_filter( 'editable_roles', [ $this, 'remove_administrator_from_editable_roles' ] );
	}

	/**
	 * Restores the "(General) Translation Editor" role if an user is promoted.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $role      The new role.
	 * @param array  $old_roles An array of the user's previous roles.
	 */
	public function restore_translation_editor_role( $user_id, $role, $old_roles ) {
		if (
			Role\General_Translation_Editor::get_name() !== $role &&
			in_array( Role\Translation_Editor::get_name(), $old_roles, true )
		) {
			$user = new WP_User( $user_id );
			$user->add_role( Role\Translation_Editor::get_name() );
		}

		if (
			Role\Translation_Editor::get_name() !== $role &&
			in_array( Role\General_Translation_Editor::get_name(), $old_roles, true )
		) {
			$user = new WP_User( $user_id );
			$user->add_role( Role\General_Translation_Editor::get_name() );
		}
	}

	/**
	 * Removes "Administrator" role from the list of editable roles.
	 *
	 * @param array $roles List of roles.
	 * @return array Filtered list of editable roles.
	 */
	public function remove_administrator_from_editable_roles( $roles ) {
		if ( ! is_super_admin() ) {
			unset( $roles['administrator'] );
		}

		return $roles;
	}
}
