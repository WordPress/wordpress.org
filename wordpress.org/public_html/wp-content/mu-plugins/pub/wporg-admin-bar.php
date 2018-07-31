<?php
/**
 * Customizations to the Admin Bar across all networks.
 */

/**
 * Makes Logo and About menu items link to the w.org/about/ page for logged-out users.
 *
 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance, passed by reference.
 */
function wporg_logged_out_admin_bar( $wp_admin_bar ) {
	if ( is_user_logged_in() ) {
		return;
	}

	$nodes = [
		$wp_admin_bar->get_node( 'wp-logo' ),
		$wp_admin_bar->get_node( 'about' ),
	];

	foreach ( $nodes as $node ) {
		$node->href = '/about/';

		$wp_admin_bar->add_node( $node );
	}
}
add_action( 'admin_bar_menu', 'wporg_logged_out_admin_bar', 11 );
