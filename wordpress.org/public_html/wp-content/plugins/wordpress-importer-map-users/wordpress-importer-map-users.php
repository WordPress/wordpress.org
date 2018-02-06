<?php
/**
 * Plugin Name: Importing to WordPress.org
 * Description: A plugin that extends the WordPress Importer to map users to WordPress.org accounts by matching email addresses.
 * Author: Andrew Nacin
 *
 * @package WPImporterMapUsers
 */

if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
	return;
}

/**
 * Sets up the plugin.
 *
 * @globals WordPress_Map_Users_Import $wordpress_map_import
 */
function wordpress_importer_map_users_init() {
	if ( ! class_exists( 'WP_Import' ) ) {
		return;
	}
	global $wordpress_map_import;

	require_once dirname( __FILE__ ) . '/class-wordpress-map-users-import.php';

	$wordpress_map_import = new WordPress_Map_Users_Import();

	register_importer(
		'wordpress-user-map',
		'WordPress.org',
		'Map users to WP.org users based on email-address during a WordPress import (good for importing from WP.org or WP.com)',
		[ $wordpress_map_import, 'dispatch' ]
	);
}
add_action( 'admin_init', 'wordpress_importer_map_users_init' );
