<?php
namespace WordPressdotorg\Rosetta\Site;

use WordPressdotorg\Rosetta\Jetpack;
use WP_Site;

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
		$jetpack_module_manager = new Jetpack\Module_Manager( [
			'stats',
			'markdown',
		] );
		$jetpack_module_manager->setup();
	}
}
