<?php

function wporg_login_body_class( $classes ) {
//	$classes[] = 'wporg-responsive';
	$classes[] = 'wporg-login';
	return $classes;
}
add_filter( 'body_class', 'wporg_login_body_class' );

function wporg_login_init() {
	// We don't need the toolbar on this site.
	show_admin_bar( false );
}
add_action( 'init', 'wporg_login_init' );