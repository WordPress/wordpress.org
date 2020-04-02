<?php
namespace WordPressdotorg\SEO\Archive_Rel_Next_Prev;
use WordPressdotorg\SEO\Canonical\get_canonical_url;

/**
 * Prints <link rel="prev|next"> tags for archives.
 *
 * @static
 */
function output_rel_prev_next_links() {
    global $paged, $wp_query, $wp_rewrite;
    if ( ! is_archive() && ! is_search() ) {
        return;
    }

    $current_url = get_canonical_url();
    if ( ! $current_url ) {
        return;
    }

    $max_page = $wp_query->max_num_pages;
    if ( ! $paged ) {
        $paged = 1;
    }

    $nextpage = intval( $paged ) + 1;
    $prevpage = intval( $paged ) - 1;

    $current_url = remove_query_arg( 'paged', $current_url );
    $current_url = preg_replace( "|{$wp_rewrite->pagination_base}/\d+/?$|", '', $current_url );

    // Just assume pretty permalinks everywhere.
    $next_url = $current_url . "{$wp_rewrite->pagination_base}/{$nextpage}/";
    $prev_url = $current_url . ( $prevpage > 1 ? "{$wp_rewrite->pagination_base}/{$prevpage}/" : '' );

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