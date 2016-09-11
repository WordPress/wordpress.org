<?php
namespace WordPressdotorg\Rosetta\Site;

use WP_Site;

interface Site {

	/**
	 * Tests whether this site manager is eligible for a site.
	 *
	 * @param WP_Site $site The site object.
	 *
	 * @return bool True if site is eligible, false otherwise.
	 */
	public static function test( WP_Site $site );

	/**
	 * Registers actions and filters.
	 */
	public function register_events();
}
