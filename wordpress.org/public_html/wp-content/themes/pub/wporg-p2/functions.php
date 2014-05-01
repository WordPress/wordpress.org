<?php

add_action( 'after_setup_theme', 'wporg_p2_after_setup_theme', 11 );
function wporg_p2_after_setup_theme() {
	register_nav_menu( 'wporg_header_subsite_nav', 'WP.org Header Sub-Navigation' );
}

add_action( 'wp_enqueue_scripts', 'wporg_p2_enqueue_scripts', 11 );
function wporg_p2_enqueue_scripts() {
	wp_deregister_style( 'p2' );
	wp_register_style( 'p2', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'wporg-p2', get_stylesheet_uri(), array( 'p2' ), '2014-05-01b' );
}

add_filter( 'get_comment_author_url', 'wporg_p2_comment_profile_urls' );
function wporg_p2_comment_profile_urls( $url ) {
	$comment = $GLOBALS['comment'];
	if ( $comment->user_id != 0 ) {
		$user = new WP_User( $comment->user_id );
		$nicename = $user->user_nicename;
		$url = "http://profiles.wordpress.org/{$nicename}/";
	}
	return $url;
}

// Return a list of link categories applied to the current post, unless it is uncategorized
function wporg_p2_get_cats_with_count( $post, $format = 'list', $before = '', $sep = '', $after = '' ) {
	$cat_links = array();
	$post_cats = get_the_terms( $post->ID, 'category' );

	if ( ! $post_cats )
		return '';

	foreach ( $post_cats as $cat ) {
		if ( 'uncategorized' == $cat->slug ) {
			continue;
		}

		if ( $cat->count > 1 && ! is_category( $cat->slug ) ) {
			$cat_link = '<a href="' . get_term_link( $cat, 'category' ) . '" rel="category">' . $cat->name . ' ( ' . number_format_i18n( $cat->count ) . ' )</a>';
		} else {
			$cat_link = $cat->name;
		}

		if ( $format == 'list' )
			$cat_link = '<li>' . $cat_link . '</li>';

		$cat_links[] = $cat_link;
	}

	if ( empty( $cat_links ) ) {
		return '';
	}

	return apply_filters( 'cats_with_count', $before . join( $sep, $cat_links ) . $after, $post );
}

// Add each site's slug to the body class, so that CSS rules can target specific sites. 
add_filter( 'body_class', 'wporg_add_site_slug_to_body_class' ); 
function wporg_add_site_slug_to_body_class( $classes ) { 
	global $current_blog;
	$classes[] = 'wporg-make';
	$classes[] = 'make-' . trim( $current_blog->path, '/' ); 
	return $classes; 
}

function wporg_p2_iphone_style_override() {
    if ( p2_is_iphone() ) {
		wp_deregister_style( 'p2-iphone-style' );
	}
	wp_enqueue_style(
		'p2-iphone-style',
		get_template_directory_uri() . '/style-iphone.css',
		array(),
		'20120402',
		'(max-width: 320px)'
    );
}
add_action( 'wp_enqueue_scripts', 'wporg_p2_iphone_style_override', 1001 );

// disable the P2 Mentions on the /core/handbook site
if ( 'make.wordpress.org' === DOMAIN_CURRENT_SITE && 0 === strpos( $_SERVER['REQUEST_URI'], '/core/handbook' ) ) {
	add_action( 'p2_found_mentions', '__return_empty_array' );
}


