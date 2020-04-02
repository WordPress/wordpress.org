<?php
namespace WordPressdotorg\SEO\Robots;

/**
 * Output a <meta name="robots"> tag when appropriate.
 */
function meta_robots() {
	global $wp_query;

	$noindex = false;

	if ( is_search() || is_author() ) {
		$noindex = true;
	} elseif ( is_singular() && 'publish' !== get_post_status() ) {
		$noindex = true;
	} elseif ( is_tax() && $wp_query->found_posts <= 3 ) {
		$noindex = true;
	}

	// Allow sites to alter this
	$noindex = apply_filters( 'wporg_noindex_request', $noindex );

	if ( $noindex ) {
		// Allow sites to override the value.
		if ( is_bool( $noindex ) ) {
			$noindex = 'noindex,follow';
		}

		echo '<meta name="robots" content="' . $noindex . '" />' . "\n";
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\meta_robots', 10, 1 );

/**
 * Add an X-Robots-Tag header when appropriate.
 */
function add_X_robots_tag( $headers ) {

	// Search and Taxonomy feeds should be noindexed.
	if ( is_feed() && ( is_tax() || is_search() ) ) {
		$headers['X-Robots-Tag'] = 'noindex, follow';
	}

	return $headers;
}
add_filter( 'wp_headers', __NAMESPACE__ . '\add_X_robots_tag' );