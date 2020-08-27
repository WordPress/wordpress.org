<?php

add_action( 'wp_enqueue_scripts', 'make_enqueue_scripts' );
function make_enqueue_scripts() {
	wp_enqueue_style( 'make-style', get_stylesheet_uri(), array(), '20190605' );
	wp_enqueue_script( 'masonry' );
}

add_action( 'after_setup_theme', 'make_setup_theme' );
function make_setup_theme() {
	register_nav_menu( 'primary', __( 'Navigation Menu', 'make-wporg' ) );
	add_theme_support( 'post-thumbnails' );
}

add_action( 'pre_get_posts', 'make_query_mods' );
function make_query_mods( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_home() ) {
		$query->set( 'posts_per_page', 1 );
	}

	// There's nothing worth searching for on this site.
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		$query->set_404();
	}
}

add_filter( 'the_posts', function( $posts, $query ) {
	// Ensure all non-post routes 404, as this site isn't like most others.
	if (
		( ! is_admin() && $query->is_main_query() && ! $query->is_robots() && ! $posts ) ||
		( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( 'meeting' ) && $query->get('paged') > 1 ) // Pagination on the query is explicitly disabled, so this doens't 404
	) {
		$query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	return $posts;
}, 10, 2 );

add_filter('post_class','make_home_site_classes', 10, 3);
function make_home_site_classes($classes, $class, $id) {
	$classes[] = sanitize_html_class( 'make-' . get_post( $id )->post_name );
	return $classes;
}

/**
 * Omit page name from front page title.
 *
 * @param array $parts The document title parts.
 * @return array The document title parts.
 */
function make_remove_frontpage_name_from_title( $parts ) {
	if ( is_front_page() ) {
		$parts['title'] = '';
	}

	return $parts;	
}
add_filter( 'document_title_parts', 'make_remove_frontpage_name_from_title' );

