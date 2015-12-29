<?php

add_action( 'after_setup_theme', 'wporg_p2_after_setup_theme', 11 );
function wporg_p2_after_setup_theme() {
	register_nav_menu( 'wporg_header_subsite_nav', 'WP.org Header Sub-Navigation' );
}

add_action( 'wp_enqueue_scripts', 'wporg_p2_enqueue_scripts', 11 );
function wporg_p2_enqueue_scripts() {
	wp_deregister_style( 'p2' );
	wp_register_style( 'p2', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'wporg-p2', get_stylesheet_uri(), array( 'p2' ), '20151228-2' );
}

add_filter( 'get_comment_author_url', 'wporg_p2_comment_profile_urls', 10, 3 );
function wporg_p2_comment_profile_urls( $url, $comment_ID, $comment ) {
	if ( $comment->user_id != 0 ) {
		$user = new WP_User( $comment->user_id );
		$nicename = $user->user_nicename;
		$url = "//profiles.wordpress.org/{$nicename}/";
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

// Disable the P2 Mentions on any handbook page
function wporg_p2_disable_mentions_for_handbooks() {
	if ( is_singular( 'handbook' ) && ! is_single( 'credits' ) ) {
		add_action( 'p2_found_mentions', '__return_empty_array', 100 );
	}
}
add_action( 'wp', 'wporg_p2_disable_mentions_for_handbooks' );

function wporg_p2_fix_utf8_user_suggestions( $users ) {
	// PHP 5.5 fails when text contains non-utf-8 characters. Pre-encoding with JSON_PARTIAL_OUTPUT_ON_ERROR and then decoding it lets us skip those
	$encoded = json_encode( $users, JSON_PARTIAL_OUTPUT_ON_ERROR );
	$decoded = json_decode( $encoded );
	return $decoded;
}
add_filter( 'p2_user_suggestion', 'wporg_p2_fix_utf8_user_suggestions', 100 );

