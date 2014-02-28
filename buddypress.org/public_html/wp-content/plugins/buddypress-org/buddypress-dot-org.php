<?php

/**
 * Plugin Name: BuddyPress.org Tweaks
 * Description: Code specific to BuddyPress.org
 * Version:     1.0.5
 * Author:      jjj
 * Author URI:  http://jaco.by
 */

// Include files
include_once( plugin_dir_path( __FILE__ ) . 'toolbar.php'    );

if ( !function_exists( 'bporg_unhook_single_user_filter' ) )
	include_once( plugin_dir_path( __FILE__ ) . 'extensions.php' );

// Always show the toolbar
if ( 'profiles.wordpress.org' != $_SERVER['HTTP_HOST'] )
	add_filter( 'show_admin_bar', '__return_true' );

/**
 * This function forces the encoding to utf8
 */
function bporg_force_encoding() {
	global $wpdb;

	// Set dbhs to an empty array - unsetting it causes headaches
	$wpdb->dbhs    = array();
	$wpdb->charset = 'utf8';
}
bporg_force_encoding();

function bporg_maintenance() {
	if ( is_super_admin() )
		return;

	if ( 'buddypress.org' == $_SERVER['HTTP_HOST'] ) {
		header( 'Retry-After: 7200' );
		wp_die( 'BuddyPress.org is down for maintenance. See you tomorrow!', 'Be back soon!', array( 'response' => 503 ) );
	}
}
//add_action( 'init', 'bporg_maintenance', 99 );

/**
 * Remove the bbPress dashboard widget, since it uses get_users() and causes
 * major slow-down.
 *
 * @author johnjamesjacoby
 * @since 1.0
 * @param type $admin
 */
function bporg_remove_dashboard_widget( $admin ) {
	remove_action( 'wp_dashboard_setup', array( $admin, 'dashboard_widget_right_now' ) );
}
add_action( 'bbp_admin_loaded', 'bporg_remove_dashboard_widget' );

/**
 * A cheap and effective way to keep non-admins out of wp-admin.
 *
 * @author johnjamesjacoby
 * @since 1.0
 * @todo flesh this out a bit more
 * @return if user is an admin
 */
function bporg_admin_redirect() {
	if (       is_super_admin()
			|| current_user_can( 'contributor'   )
			|| current_user_can( 'author'        )
			|| current_user_can( 'editor'        )
			|| current_user_can( 'administrator' )
		)
		return;

	// Allow registered unprivileged admin-ajax.php requests for
	// profiles.wordpress.org to pass through.
	if (	'profiles.wordpress.org' == $_SERVER['HTTP_HOST']
			&& isset( $_REQUEST['action'] )
			&& has_action( 'wp_ajax_nopriv_' . $_REQUEST['action'] )
		)
		return;

	wp_safe_redirect( home_url( '/' ) );
	die;
}
add_action( 'admin_init', 'bporg_admin_redirect' );

/**
 * Prevent bbPress profiles from colliding with BuddyPress ones
 *
 * This is a temporary measure until we unify user profiles
 */
function bporg_no_bbpress_profiles() {
	if ( bp_is_user() ) {
		remove_filter( 'template_include', 'bbp_template_include', 10 );
	}
}
add_filter( 'bp_template_redirect', 'bporg_no_bbpress_profiles' );
