<?php

namespace WordPressdotorg\API\Patterns;

/*
 * Supply Block Pattern Directory data to the block editor.
 *
 * This is cached by nginx, so we don't have to worry about the performance costs of loading WP, and don't need to
 * do any any object caching.
 */

main( $_SERVER['QUERY_STRING'] );

/**
 * Last minute rewrite of headers, to correct URLs set by the internal API endpoint.
 *
 * @param string $buffer
 */
function flush_handler( $buffer ) {
	$old_headers = headers_list();

	// Remove CORS header added by REST API.
	header_remove( 'access-control-allow-headers' );

	$replace = true;

	foreach ( headers_list() as $header ) {
		if ( 'Link: ' === substr( $header, 0, 6) ) {
			$new_header = str_replace( 'https://wordpress.org/patterns/wp-json/wp/v2/wporg-pattern', 'https://api.wordpress.org/patterns/1.0', $header );
			$new_header = str_replace( 'https://wordpress.org/patterns/wp-json/', 'https://api.wordpress.org/patterns/1.0/', $new_header );
			if ( $new_header !== $header ) {
				header( $new_header, $replace );
				$replace = false; // Only replace the first time.
			}
		}
	}

	return false; // Original buffer will be output with no changes.
}


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
		$endpoint               = '/wp/v2/wporg-pattern';
		$query_args['_fields']  = 'id,title,content,meta,category_slugs,keyword_slugs,pattern_content';
		$query_args['per_page'] = $query_args['per_page'] ?? 100;
	}

	// Mimic browser request, so that `wp_is_json_request()` is accurate.
	$_SERVER['HTTP_ACCEPT'] = 'application/json';

	$wp_init_host = $api_url_base . $endpoint . '?' . urldecode( http_build_query( $query_args ) );

	ob_start( __NAMESPACE__ . '\flush_handler' );

	// Load WordPress to process the request and output the response.
	require_once dirname( dirname( __DIR__ ) ) . '/wp-init.php';
}
