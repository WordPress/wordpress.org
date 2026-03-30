<?php

/**
 * Plugin Name: bbPress.org Tweaks
 * Description: Code specific to bbPress.org
 * Version:     1.0.6
 * Author:      jjj
 * Author URI:  http://jaco.by
 */

// Include files
include_once( plugin_dir_path( __FILE__ ) . 'toolbar.php' );

/**
 * A cheap and effective way to keep non-admins out of wp-admin.
 *
 * @author johnjamesjacoby
 * @since 1.0
 * @todo flesh this out a bit more
 * @return if user is an admin
 */
function bbporg_admin_redirect() {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_safe_redirect( home_url( '/' ) );
	die;
}
add_action( 'bbp_admin_init', 'bbporg_admin_redirect' );
