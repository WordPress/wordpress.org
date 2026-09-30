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
 * Note: This is not for security, this is a UX enhancement to prevent a user landing in wp-admin when they can't do anything.
 *
 * @author johnjamesjacoby
 * @since 1.0
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
