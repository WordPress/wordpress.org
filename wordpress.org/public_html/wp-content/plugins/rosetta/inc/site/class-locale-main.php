<?php
namespace WordPressdotorg\Rosetta\Site;

use WordPressdotorg\Rosetta\Filter;
use WordPressdotorg\Rosetta\Jetpack;
use WordPressdotorg\Rosetta\User;
use WP_Site;

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
			$user_sync = new User\Sync();
			$user_sync->set_destination_site( get_site_by_path( get_site()->domain, Locale_Team::$path ) );
			$user_sync->set_roles_to_sync( [ 'editor' ] );
			$user_sync->setup();
		}

		$jetpack_module_manager = new Jetpack\Module_Manager( [
			'stats',
			'videopress',
			'contact-form',
			'sharedaddy',
			'shortcodes',
		] );
		$jetpack_module_manager->setup();

		$options = new Filter\Options();
		// Options for Jetpack's sharing module.
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
}
