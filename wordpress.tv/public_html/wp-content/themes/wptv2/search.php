<?php
global $wp_query;

// See improved improved (yes, improved twice) search.
/*if ( $wp_query->is_search() ) {
	$query = wptv_search_taxonomies( $wp_query->query_vars['s'] );
	$wp_query->posts = array_merge( $wp_query->posts, $query->posts );
	$wp_query->post_count = count( $wp_query->posts );
}*/

get_template_part( 'archive' );
