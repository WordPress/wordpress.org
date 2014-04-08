<?php

/**
 * Plugin Name: bbPress.org Tweaks
 * Description: Code specific to bbPress.org
 * Version:     1.0.5
 * Author:      jjj
 * Author URI:  http://jaco.by
 */

// Include files
include_once( plugin_dir_path( __FILE__ ) . 'toolbar.php' );
include_once( plugin_dir_path( __FILE__ ) . 'tools.php'   );

/**
 * A cheap and effective way to keep non-admins out of wp-admin.
 *
 * @author johnjamesjacoby
 * @since 1.0
 * @todo flesh this out a bit more
 * @return if user is an admin
 */
function bbporg_admin_redirect() {
	if (	   ! current_user_can( 'contributor'   )
			|| ! current_user_can( 'author'        )
			|| ! current_user_can( 'editor'        )
			|| ! current_user_can( 'administrator' )
		)
		return;

	wp_safe_redirect( 'http://bbpress.org' );
	die;
}
add_action( 'bbp_admin_init', 'bbporg_admin_redirect' );

/**
 * Filter the users edit profile URL and use bbPress's theme-side edit instead
 *
 * @author johnjamesjacoby
 * @since 1.0
 * @param string $user_edit
 * @return string
 */
function bbporg_filter_profile_edit_url( $user_edit = '' ) {

	// Bail if bbPress is not active
	if ( ! function_exists( 'bbp_get_user_profile_edit_url' ) ) {
		return $user_edit;
	}

	$user_edit = bbp_get_user_profile_edit_url( bbp_get_current_user_id() );
	return $user_edit;
}
add_filter( 'edit_profile_url', 'bbporg_filter_profile_edit_url' );
