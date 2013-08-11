<?php

add_action( 'after_setup_theme', 'wporg_p2_after_setup_theme', 11 );
function wporg_p2_after_setup_theme() {
	register_nav_menu( 'wporg_header_subsite_nav', 'WP.org Header Sub-Navigation' );
}

add_action( 'wp_enqueue_scripts', 'wporg_p2_enqueue_scripts', 11 );
function wporg_p2_enqueue_scripts() {
	wp_deregister_style( 'p2' );
	wp_register_style( 'p2', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'wporg-p2', get_stylesheet_uri(), array( 'p2' ), '2013-07-01' );
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

