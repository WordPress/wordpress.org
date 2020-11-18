<?php
namespace WordPressdotorg\Theme_Preview;
/**
 * Plugin Name: Disallow Comments
 * Description: Routes comments to /dev/null, allows us to have an open comment form that does nothing.
 */

add_filter( 'pre_comment_content', function( $text ) {
	// Allow authenticated users to add comments
	if ( is_user_logged_in() ) {
		return $text;
	}

	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
	}

	die( '<h1>Comments are disabled.</h1>' );
}, 1 );
