<?php

namespace WordPressdotorg\API\Patterns;

/*
 * This is cached by nginx, so we don't have to worry about the performance costs of loading WP, and don't need to
 * do any any object caching.
 *
 */


/*
 * @todo
 *
 */

main( $_SERVER['QUERY_STRING'] );


/**
 * Proxy w.org/patterns API endpoints for reliability.
 *
 * Core needs to send requests to api.w.org, because it has more resources and better caching than w.org.
 *
 * @param string $query_string
 */
function main( $query_string ) {
	$api_url_base  = 'https://wordpress.org/patterns/wp-json';
	$wp_init_query = true;

	parse_str( $query_string, $query_args );

	switch ( $query_args['action'] ) {
		// List all patterns, or all with in category.
		// To restrict to a category, the client needs to provide `category={id}` param.
		default:
		case 'get_patterns':
			$endpoint = '/wp/v2/wporg-pattern';

			// `_links` is a workaround for https://core.trac.wordpress.org/ticket/49985. Related https://core.trac.wordpress.org/ticket/49538.
			$query_args['_fields'] = 'id,title,content,meta,_links';
			$query_args['_embed']  = 'wp:term';

			break;

		// Search patterns.
		// Client needs to provide `search={string}` param.
		case 'query_patterns':
			$endpoint              = '/wp/v2/search';
			$query_args['subtype'] = 'wporg-pattern';

			// `_links` is a workaround for https://core.trac.wordpress.org/ticket/49985. Related https://core.trac.wordpress.org/ticket/49538.
			$query_args['_fields'] = '_links';
			$query_args['_embed']  = 'self';

			break;
	}

	unset( $query_args['action'] );

	$wp_init_host = $api_url_base . $endpoint;

	if ( $query_args ) {
		$wp_init_host .= '?' . urldecode( http_build_query( $query_args ) );
	}

	require_once dirname( dirname( __DIR__ ) ) . '/wp-init.php';
}
