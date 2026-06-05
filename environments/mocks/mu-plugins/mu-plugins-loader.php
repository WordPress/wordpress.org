<?php
/**
 * Plugin Name: mu-plugins Loader
 * Description: Bootstraps mu-plugins from pub/ and wporg-mu-plugins/ directories.
 */

// Define WPORGPATH if it isn't already — translate.wordpress.org's wporg-gp-routes plugin
// references it on plugins_loaded, before the wporg theme (which defines it on prod) has loaded.
if ( ! defined( 'WPORGPATH' ) ) {
	define( 'WPORGPATH', ABSPATH );
}

// wporg-gp-customizations/templates/helper-functions.php calls get_sites() and references
// WPORG_GLOBAL_NETWORK_ID to look up sibling Rosetta sites. wp-env runs single-site, so
// stub both: an empty get_sites() returns no matches and the helper falls through cleanly.
if ( ! defined( 'WPORG_GLOBAL_NETWORK_ID' ) ) {
	define( 'WPORG_GLOBAL_NETWORK_ID', 1 );
}
if ( ! function_exists( 'get_sites' ) ) {
	function get_sites( $args = array() ) {
		return array();
	}
}

// Used by wporg-gp-routes, the plugin/theme directories, and the wporg-main
// theme — undefined it triggers fatals on any page that hits those code paths.
// Pin to the installed WordPress version (major.minor) so it tracks whatever
// wp-env pulled in.
if ( ! defined( 'WP_CORE_STABLE_BRANCH' ) ) {
	if ( ! isset( $wp_version ) ) {
		require_once ABSPATH . WPINC . '/version.php';
	}
	define( 'WP_CORE_STABLE_BRANCH', preg_replace( '/^(\d+\.\d+).*/', '$1', $wp_version ) );
}

// Load all mu-plugins from pub/.
foreach ( glob( __DIR__ . '/pub/*.php' ) as $file ) {
	require_once $file;
}

// Load the wporg-mu-plugins loader.
require_once __DIR__ . '/wporg-mu-plugins/mu-plugins/loader.php';
