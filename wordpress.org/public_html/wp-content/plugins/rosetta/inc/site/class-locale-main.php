<?php
namespace WordPressdotorg\Rosetta\Site;

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
	}
}
