<?php
namespace WordPressdotorg\SEO\Canonical;

/**
 * Outputs a <link rel="canonical"> on most pages.
 */
function wporg_themes_archive_rel_canonical_link() {
	if ( $url = get_canonical_url() ) {
		printf(
			'<link rel="canonical" href="%s">' . "\n",
			esc_url( $url )
		);
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\wporg_themes_archive_rel_canonical_link' );
remove_action( 'wp_head', 'rel_canonical' );

/**
 * Get the current Canonical URL.
 */
function get_canonical_url() {
    $queried_object = get_queried_object();
	$link = false;

    if ( is_tax() || is_tag() || is_category() ) {
		$url = get_term_link( $queried_object );
	} elseif ( is_singular() ) {
		$url = get_permalink( $queried_object );
	} elseif ( is_search() ) {
		$url = home_url( 'search/' . urlencode( get_query_var( 's' ) ) . '/' );
	} elseif ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_author() ) {
		// On WordPress.org get_author_posts_url() returns profile.wordpress.org links. Build it manually.
		$url = home_url( 'author/' . $queried_object->user_nicename . '/' );
	} elseif ( is_post_type_archive() ) {
		$canonical = get_post_type_archive_link( $queried_object->name ); 
	}

	if ( $url && is_paged() ) {
		if ( false !== stripos( $url, '?' ) ) {
			$url = add_query_arg( 'paged', (int) get_query_var( 'paged' ), $url );
		} else {
			$url = rtrim( $url, '/' ) . '/page/' . (int) get_query_var( 'paged' ) . '/';
		}
	}

    $url = apply_filters( 'wporg_canonical_link', $url );

    return $url;
}