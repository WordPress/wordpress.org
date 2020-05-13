<?php

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
