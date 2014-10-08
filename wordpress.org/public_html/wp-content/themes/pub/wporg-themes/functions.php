<?php
function wporg_themes_setup() {
//	load_theme_textdomain( 'wporg-themes', get_template_directory() . '/languages' );
	add_theme_support( 'automatic-feed-links' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'wporg-themes' ),
	) );
	
	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
	) );
}
add_action( 'after_setup_theme', 'wporg_themes_setup' );

function wporg_themes_style() {
	//<link rel="stylesheet" href="http://localhost/repotest/wp-admin/css/themes.css" />
	wp_enqueue_style( 'wporg-themes-style', get_stylesheet_uri() );
}
add_action( 'wp_enqueue_scripts', 'wporg_themes_style' );

// force the post type to the repopackages
// TODO smarter
function wporg_themes_pregetposts( &$q ) {
	$q->set('post_type', 'repopackage');
}
add_action( 'pre_get_posts', 'wporg_themes_pregetposts' );
