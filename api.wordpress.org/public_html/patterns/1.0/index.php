<?php

namespace WordPressdotorg\API\Patterns;

/*
 * Supply Block Pattern Directory data to the block editor.
 *
 * This is cached by nginx, so we don't have to worry about the performance costs of loading WP, and don't need to
 * do any any object caching.
 *
 * todo
 * - publish caching sysreq once query args settled for patterns and categories, etc -- https://make.wordpress.org/systems/wp-admin/post.php?post=1788&action=edit
 * - add docs to codex
 */

main( $_SERVER['QUERY_STRING'] );


/**
 * Proxy w.org/patterns API endpoints for reliability.
 *
 * Core clients need to send requests to api.w.org, because it has more resources and better caching than w.org.
 *
 * @param string $query_string
 */
function main( $query_string ) {
	$api_url_base  = 'https://wordpress.org/patterns/wp-json';
	$wp_init_query = true;

	/*
	 * Core clients should pass params for the desired action:
	 *
	 * @example Browse all patterns:         `/patterns/1.0/`
	 * @example Browse patterns by category: `/patterns/1.0/?pattern-categories={id}`
	 * @example Search patterns:             `/patterns/1.0/?search={query}`
	 *
	 * @example Browse all categories:       `/patterns/1.0/?categories`
	 *
	 * Other query args will be passed on to the w.org endpoint.
	 */
	parse_str( $query_string, $query_args );

	if ( isset( $query_args['categories'] ) ) { // Return categories.
		$endpoint              = '/wp/v2/pattern-categories';
		$query_args['_fields'] = 'id,name,slug';

	} else { // Return patterns.
		$endpoint              = '/wp/v2/wporg-pattern';
		$query_args['_fields'] = 'id,title,content,meta,category_slugs,keyword_slugs,pattern_content';

		// Sort alphabetically so that browsing is intuitive. Search will be sorted by rank.
		if ( ! isset( $query_args['search'] ) ) {
			$query_args['orderby'] = 'title';
			$query_args['order']   = 'asc';
		}
	}

	$wp_init_host = $api_url_base . $endpoint . '?' . urldecode( http_build_query( $query_args ) );

	// Load WordPress to process the request and output the response.
	require_once dirname( dirname( __DIR__ ) ) . '/wp-init.php';
}
