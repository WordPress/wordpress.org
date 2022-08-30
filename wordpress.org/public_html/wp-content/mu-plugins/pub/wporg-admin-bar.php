<?php
namespace WordPressdotorg\MU_Plugins\Admin_Bar;
/**
 * Customizations to the Admin Bar across all networks.
 */

/**
 * Removes the Search, Logo, and About menu from the admin bar for all users.
 *
 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance, passed by reference.
 */
function admin_bar_menu( $wp_admin_bar ) {
	if ( is_admin() ) {
		return;
	}

	$wp_admin_bar->remove_node( 'wp-logo' );
	$wp_admin_bar->remove_node( 'search' );
}
add_action( 'admin_bar_menu', __NAMESPACE__ . '\admin_bar_menu', 11 );
