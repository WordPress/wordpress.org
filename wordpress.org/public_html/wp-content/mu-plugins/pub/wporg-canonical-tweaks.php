<?php

/**
 * Custom Canonical redirect for Facebook and Twitter referrers.
 */
add_action( 'template_redirect', function() {

	// Only run on pages with canonical enabled.
	if ( ! has_action( 'template_redirect', 'redirect_canonical' ) ) {
		return;
	}

	$url = false;
	if ( isset( $_GET['fbclid'] ) ) {
		$url = remove_query_arg( 'fbclid' ) . '#utm_medium=referral&utm_source=facebook.com&utm_content=social';
	} elseif ( isset( $_GET['__twitter_impression'] ) ) {
		$url = remove_query_arg( '__twitter_impression' ) . '#utm_medium=referral&utm_source=twitter.com&utm_content=social';
	}

	if ( $url ) {
		wp_safe_redirect( $url, 301 );
		exit;
	}

}, 9 ); // Before redirect_canonical();

/*
 * WordPress.org/-specific redirects
 */
if ( 1 === get_current_blog_id() ) {
	add_action( 'template_redirect', function() {
		if ( is_feed() ) {
			// WordPress.org/feed/* should redirect to WordPress.org/news/feed/*
			wp_safe_redirect( '/news/feed/' . ( 'feed' !== get_query_var('feed') ? get_query_var('feed') : '' ), 301 );
			exit;
		} elseif ( is_search() ) {
			wp_safe_redirect( '/search/' . urlencode( get_query_var('s') ), 301 );
			exit;
		}
	}, 9 ); // Before redirect_canonical();
}
