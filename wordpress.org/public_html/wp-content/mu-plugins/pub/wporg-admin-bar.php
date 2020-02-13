<?php
/**
 * Customizations to the Admin Bar across all networks.
 */

/**
 * Makes Logo and About menu items link to the localised w.org/about/ page for all users.
 *
 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance, passed by reference.
 */
function wporg_about_links_in_admin_bar( $wp_admin_bar ) {
	$nodes = [
		$wp_admin_bar->get_node( 'wp-logo' ),
		$wp_admin_bar->get_node( 'about' ),
	];

	$about_url = 'https://wordpress.org/about/';

	if ( class_exists( 'Rosetta_Sites' ) && is_object( $GLOBALS['rosetta'] ) ) {
		$about_url = 'https://' . $GLOBALS['rosetta']->current_site_domain . '/about/';
	}

	foreach ( $nodes as $node ) {
		if ( !empty( $node ) ) {
			$node->href = $about_url;

			$wp_admin_bar->add_node( $node );
		}
	}
}
add_action( 'admin_bar_menu', 'wporg_about_links_in_admin_bar', 11 );
