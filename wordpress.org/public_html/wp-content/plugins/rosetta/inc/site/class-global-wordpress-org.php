<?php
namespace WordPressdotorg\Rosetta\Site;

use WordPressdotorg\Rosetta\Admin\Network\Locale_Associations;
use WordPressdotorg\Rosetta\Admin\Network\Locale_Associations_View;
use WordPressdotorg\Rosetta\Database\Tables;
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
		add_action( 'wpmu_new_blog', [ $this, 'set_new_blog_lang_id' ], 30, 1 );

		add_action( 'network_admin_menu', function() {
			$locale_associations = new Locale_Associations( new Locale_Associations_View() );
			$locale_associations->register();
		} );
	}

	/**
	 * Sets 'lang_id' for new sites.
	 *
	 * @param int $blog_id ID of the new site.
	 */
	public function set_new_blog_lang_id( $blog_id ) {
		global $wpdb;

		$site = get_site( $blog_id );
		$subdomain = strtok( $site->domain, '.' );

		$lang_id = $wpdb->get_var( $wpdb->prepare( 'SELECT locale_id FROM ' . Tables::LOCALES . ' WHERE subdomain = %s', $subdomain ) );
		if ( ! $lang_id ) {
			return;
		}

		update_blog_details( $blog_id, [ 'lang_id' => $lang_id ] );
	}
}
