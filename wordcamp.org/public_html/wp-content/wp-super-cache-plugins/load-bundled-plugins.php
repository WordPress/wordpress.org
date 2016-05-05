<?php

namespace WordCamp\WPSC_Plugins\Load_Bundled_Plugins;
defined( 'WPCACHEHOME' ) or die();

/*
 * We define a custom WPSC plugin directory in `wp-cache-config.php`, and WPSC only loads the plugins in that
 * directory. So, we need to manually load the bundled plugins. 
 */

$bundled_plugins = glob( WPCACHEHOME . '/plugins/*.php' );

if ( empty( $bundled_plugins ) ) {
	return;
}

foreach ( $bundled_plugins as $plugin ) {
	if ( is_file( $plugin ) ) {
		require_once( $plugin );
	}
}
