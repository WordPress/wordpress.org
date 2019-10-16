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
 * WordPress.org/feed/* should redirect to WordPress.org/news/feed/*
 */
if ( 'wordpress.org' === $_SERVER['HTTP_HOST'] && '/feed' === substr( $_SERVER['REQUEST_URI'], 0, 5 ) ) {
	add_action( 'template_redirect', function() {
		if ( is_feed() ) {
			wp_safe_redirect( '/news' . $_SERVER['REQUEST_URI'], 301 );
			exit;
		}
	}, 9 ); // Before redirect_canonical();
}
