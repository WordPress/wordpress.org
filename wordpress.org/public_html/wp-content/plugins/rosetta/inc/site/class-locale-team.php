<?php
namespace WordPressdotorg\Rosetta\Site;

use WordPressdotorg\Rosetta\Jetpack;
use WP_Customize_Manager;
use WP_Site;
use WP_User;

class Locale_Team implements Site {

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
	public static $path = '/team/';

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
		$this->initialize_jetpack_customizations();
		$this->initialize_user_role_customizations();
	}

	/**
	 * Initializes customizations for Jetpack.
	 */
	private function initialize_jetpack_customizations() {
		$jetpack_module_manager = new Jetpack\Module_Manager( [
			'stats',
			'markdown',
			'subscriptions',
			'sharedaddy',
		] );
		$jetpack_module_manager->setup();

		// Options for Jetpack's sharing module.
		add_filter(
			'pre_option_sharing-options',
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

		add_filter(
			'pre_option_sharing-services',
			function () {
				return [
					'visible' => [ 'mastodon', 'twitter', 'facebook', 'linkedin', 'email' ],
					'hidden'  => [],
				];
			}
		);

		add_filter(
			'option_stats_options',
			function ( $options ) {
				$options          = is_array( $options ) ? $options : [];
				$options['roles'] = [ 'administrator', 'editor', 'author' ];
				return $options;
			}, 10, 1
		);
	}

	/**
	 * Initializes user role customizations.
	 */
	private function initialize_user_role_customizations() {
		add_filter( 'user_has_cap', [ $this, 'extend_editors_capabilities' ], 10, 4 );
		add_filter( 'editable_roles', [ $this, 'remove_administrator_from_editable_roles' ] );
		add_action( 'customize_register', [ $this, 'allow_editors_to_change_site_title_in_customizer' ], 20 );
	}

	/**
	 * Extends editor's capabilities to be able to change theme settings
	 * and manage users.
	 *
	 * @param array   $allcaps An array of all the user's capabilities.
	 * @param array   $caps    Actual capabilities for meta capability.
	 * @param array   $args    Optional parameters passed to has_cap(), typically object ID.
	 * @param WP_User $user    The user object.
	 * @return array An array of user's capabilities.
	 */
	public function extend_editors_capabilities( $allcaps, $caps, $args, $user ) {
		if ( ! in_array( 'editor', (array) $user->roles, true ) ) {
			return $allcaps;
		}

		$allcaps['edit_theme_options'] = true;
		$allcaps['list_users']         = true;
		$allcaps['promote_users']      = true;
		$allcaps['remove_users']       = true;

		return $allcaps;
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

	/**
	 * Allows users with the 'edit_theme_options' capability to change the site title
	 * in the customizer.
	 *
	 * @param WP_Customize_Manager $wp_customize The customizer object.
	 */
	public function allow_editors_to_change_site_title_in_customizer( $wp_customize ) {
		$blogname = $wp_customize->get_setting( 'blogname' );
		if ( $blogname ) {
			$blogname->capability = 'edit_theme_options';
		}

		$blogdescription = $wp_customize->get_setting( 'blogdescription' );
		if ( $blogdescription ) {
			$blogdescription->capability = 'edit_theme_options';
		}
	}
}
