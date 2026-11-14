<?php
namespace WordPressdotorg\SEO\Redirects;

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

/**
 * Redirects paginated requests for a static front page to the front page.
 *
 * A static front page has no further pages, so /page/N/ and ?paged=N only serve duplicates of it.
 * Core moves `paged` into `page` for a static front page, so `page` holds the number for both.
 */
function paginated_static_front_page() {
	if ( ! is_front_page() || is_home() || (int) get_query_var( 'page' ) < 2 ) {
		return;
	}

	// A front page split with <!--nextpage--> has real pages.
	if ( str_contains( get_post_field( 'post_content', get_queried_object_id() ), '<!--nextpage-->' ) ) {
		return;
	}

	wp_safe_redirect( home_url( '/' ), 301 );
	exit;
}
add_action( 'template_redirect', __NAMESPACE__ . '\paginated_static_front_page', 9 ); // Runs before redirect_canonical.
