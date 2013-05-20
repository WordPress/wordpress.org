<?php

add_action( 'after_setup_theme', 'wporg_p2_after_setup_theme', 11 );
function wporg_p2_after_setup_theme() {
	register_nav_menu( 'wporg_header_subsite_nav', 'WP.org Header Sub-Navigation' );
}

add_action( 'wp_enqueue_scripts', 'wporg_p2_enqueue_scripts', 11 );
function wporg_p2_enqueue_scripts() {
	wp_deregister_style( 'p2' );
	wp_register_style( 'p2', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'wporg-p2', get_stylesheet_uri(), array( 'p2' ), '2013-05-20' );
}
