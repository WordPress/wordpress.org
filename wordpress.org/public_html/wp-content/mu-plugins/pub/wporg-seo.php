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

/**
 * Collect things that belong in HTTP headers.
 */
function wp_headers( $headers ) {
	if ( $value = maybe_add_X_robots_tag() ) {
		$headers[ 'X-Robots-Tag'] = $value;
	}

	return $headers;
}
add_filter( 'wp_headers', 'wp_headers' );

function maybe_add_X_robots_tag() {

	// Search and Taxonomy feeds should be noindexed.
	if ( is_feed() && ( is_tax() || is_search() ) ) {
		return 'noindex, follow';
	}

	return false;
}

/**
 * Custom Canonical redirect for Facebook and Twitter referrers.
 */
function facebook_twitter_referers() {
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
}
add_action( 'template_redirect', __NAMESPACE__ . '\facebook_twitter_referers', 9 ); // Before redirect_canonical();
