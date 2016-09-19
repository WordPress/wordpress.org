<?php
namespace WordPressdotorg\Rosetta\Site;

use WordPressdotorg\Rosetta\Jetpack;
use WP_Site;

class Global_WordPress_Org implements Site {

	/**
	 * Domain of this site.
	 *
	 * @var string
	 */
	public static $domain = 'global.wordpress.org';

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
		if ( self::$domain === $site->domain ) {
			return true;
		}

		return false;
	}

	/**
	 * Registers actions and filters.
	 */
	public function register_events() {
		$jetpack_connector = new Jetpack\Connector();
		add_action( 'wpmu_new_blog', [ $jetpack_connector, 'schedule_connect_event' ], 20, 1 );
		add_action( $jetpack_connector->get_connect_event_name(), [ $jetpack_connector, 'connect_site' ] );
	}
}
