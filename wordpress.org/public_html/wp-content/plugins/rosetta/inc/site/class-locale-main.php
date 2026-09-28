<?php
namespace WordPressdotorg\Rosetta\Site;

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
			$current_site = get_site();

			if ( $current_site instanceof WP_Site ) {
				// Get the team site.
				$result = get_sites(
					[
						'domain' => $current_site->domain,
						'path'   => Locale_Team::$path,
						'number' => 1,
					]
				);
				$team_site = array_shift( $result );

				if ( $team_site ) {
					$user_sync = new User\Sync();
					$user_sync->set_destination_site( $team_site );
					$user_sync->set_roles_to_sync(
						[
							'editor'                        => 'editor',
							Role\Locale_Manager::get_name() => 'editor',
						]
					);
					$user_sync->setup();
				}
			}
		}

		$this->initialize_jetpack_customizations();
		$this->initialize_user_role_customizations();

		add_action( 'after_setup_theme', [ $this, 'register_resources_nav_menu' ] );
	}

	/**
	 * Registers a nav menu for storing resources for translation contributors.
	 */
	public function register_resources_nav_menu() {
		register_nav_menu( 'rosetta_translation_contributor_resources', __( 'Resources for translation contributors', 'rosetta' ) );
	}

	/**
	 * Initializes customizations for Jetpack.
	 */
	private function initialize_jetpack_customizations() {
		$jetpack_module_manager = new Jetpack\Module_Manager(
			[
				'stats',
				'videopress',
				'contact-form',
				'sharedaddy',
				'shortcodes',
				'subscriptions',
			]
		);

		$jetpack_module_manager->setup();

		// Options for Jetpack's sharing module.
		add_filter( 'pre_option_sharing-options',
			function () {
				return [
					'global' => [
						'button_style'  => 'icon-text',
						'sharing_label' => __( 'Share this:', 'rosetta' ),
						'open_links'    => 'same',
						'show'          => [ 'post' ],
						'custom'        => [],
					],
				];
			}
		);

		add_filter( 'pre_option_sharing-services',
			function () {
				return [
					'visible' => [ 'mastodon', 'twitter', 'facebook', 'linkedin' 'email' ],
					'hidden'  => [],
				];
			}
		);

		add_filter( 'option_stats_options',
			function ( $options ) {
				$options          = is_array( $options ) ? $options : [];
				$options['roles'] = [
					'administrator',
					'editor',
					'author',
					Role\Locale_Manager::get_name(),
				];
				return $options;
			}, 10, 1
		);

		// Options for Jetpack's subscription module.
		add_filter( 'pre_option_stb_enabled', '__return_zero' );
		add_filter( 'pre_option_stc_enabled', '__return_zero' );
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
			Role\General_Translation_Editor::get_name() !== $role
			&& in_array( Role\Translation_Editor::get_name(), (array) $old_roles, true )
		) {
			$user = new WP_User( $user_id );
			$user->add_role( Role\Translation_Editor::get_name() );
		}

		if (
			Role\Translation_Editor::get_name() !== $role
			&& in_array( Role\General_Translation_Editor::get_name(), (array) $old_roles, true )
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
