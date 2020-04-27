<?php
namespace WordPressdotorg\SEO\Archive_Rel_Next_Prev;
use function WordPressdotorg\SEO\Canonical\get_canonical_url;

/**
 * Prints <link rel="prev|next"> tags for archives.
 */
function output_rel_prev_next_links() {
	global $paged, $wp_query, $wp_rewrite;

	$current_url = get_canonical_url();
	if ( ! $current_url ) {
		return;
	}

	$max_page = $wp_query->max_num_pages;
	// Filters the maximum number of pages for a URL, when it's handled outside of WP_Query (ala bbPress).
	$max_page = apply_filters( 'wporg_rel_next_pages', $max_page );
	if ( ! $paged ) {
		$paged = 1;
	}

	$nextpage = intval( $paged ) + 1;
	$prevpage = intval( $paged ) - 1;

	$current_url = remove_query_arg( 'paged', $current_url );
	$current_url = preg_replace( "#/{$wp_rewrite->pagination_base}/\d+/?($|\?)#", '$1', $current_url );

	// Support Canonical URLs with query parameters.
	$current_url_query = '';
	if ( false !== stripos( $current_url, '?' ) ) {
		[ $current_url, $current_url_query ] = explode( '?', $current_url, 2 );
		if ( $current_url_query ) {
			$current_url_query = '?' . $current_url_query;
		}
	}

	// Just assume rewrites are in use everywhere.
	$next_url = rtrim( $current_url, '/' ) . "/{$wp_rewrite->pagination_base}/{$nextpage}/" . $current_url_query;
	$prev_url = rtrim( $current_url, '/' ) . ( $prevpage > 1 ? "/{$wp_rewrite->pagination_base}/{$prevpage}/" : '/' ) . $current_url_query;

	if ( $prevpage >= 1 ) {
		printf(
			'<link rel="prev" href="%s">' . "\n",
			esc_url( $prev_url )
		);
	}

	if ( $nextpage <= $max_page ) {
		printf(
			'<link rel="next" href="%s">' . "\n",
			esc_url( $next_url )
		);
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\output_rel_prev_next_links' );
