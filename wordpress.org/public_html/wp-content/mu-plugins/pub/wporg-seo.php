<?php
namespace WordPressdotorg\SEO;
/**
 * Plugin Name: WordPress.org Generic SEO fixes.
 */

/**
 * Output things that belong in the <head> element.
 */
function wp_head() {
	meta_robots();
}
add_action( 'wp_head', __NAMESPACE__ . '\wp_head', 10, 1 );

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

	$noindex = apply_filters( 'wporg_noindex_request', $noindex );

	if ( $noindex ) {
		echo '<meta name="robots" content="noindex,follow" />' . "\n";
	}
}
