<?php

add_action( 'wp_enqueue_scripts', 'make_enqueue_scripts' );
function make_enqueue_scripts() {
	wp_enqueue_style( 'make-style', get_stylesheet_uri() );
	wp_enqueue_style( 'dashicons', get_template_directory_uri() . '/dashicons.css' );
}

add_action( 'after_setup_theme', 'make_setup_theme' );
function make_setup_theme() {
	register_nav_menu( 'primary', __( 'Navigation Menu', 'make-wporg' ) );
}

add_action( 'pre_get_posts', 'make_query_mods' );
function make_query_mods( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_home() )
		$query->set( 'posts_per_page', 1 );
}
