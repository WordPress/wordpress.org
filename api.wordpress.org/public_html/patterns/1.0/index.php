<?php

namespace WordPressdotorg\API\Patterns;

/*
 * Supply Block Pattern Directory data to the block editor.
 *
 * This is cached by nginx, so we don't have to worry about the performance costs of loading WP, and don't need to
 * do any any object caching.
 *
 * todo
 * - publish caching sysreq once query args settled, etc -- https://make.wordpress.org/systems/wp-admin/post.php?post=1788&action=edit
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
	$endpoint      = '/wp/v2/wporg-pattern';

	/*
	 * Core clients should pass params for the desired action:
	 *
	 * @example Browse all:        `/patterns/1.0/`
	 * @example Browse a category: `/patterns/1.0/?pattern-categories={id}`
	 * @example Search:            `/patterns/1.0/?search={query}`
	 */
	parse_str( $query_string, $query_args );

	/*
	 * Filter the returned fields down to only the ones Core uses.
	 *
	 * `_links` is necessary for `wp:term` to be embedded, see https://core.trac.wordpress.org/ticket/49985.
	 * Related https://core.trac.wordpress.org/ticket/49538.
	 */
	$query_args['_fields'] = 'id,title,content,meta,_links';
	$query_args['_embed']  = 'wp:term';

	// Sort alphabetically so that browsing is intuitive. Search will be sorted by rank.
	if ( ! isset( $query_args['search'] ) ) {
		$query_args['orderby'] = 'title';
		$query_args['order']   = 'asc';
	}

	$wp_init_host = $api_url_base . $endpoint . '?' . urldecode( http_build_query( $query_args ) );

	// Load WordPress to process the request and output the response.
	require_once dirname( dirname( __DIR__ ) ) . '/wp-init.php';
}
