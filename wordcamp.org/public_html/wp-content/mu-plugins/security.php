<?php

namespace WordCamp\Security;
use WP;

defined( 'WPINC' ) || die();

add_filter( 'wp_headers', __NAMESPACE__ . '\modify_front_end_http_headers', 10, 2 );

/**
 * Modify the response HTTP headers for front-end requests
 *
 * @param array $headers
 * @param WP    $wp
 *
 * @return array
 */
function modify_front_end_http_headers( $headers, $wp ) {
	/*
	 * Mitigate clickjacking
	 *
	 * Core does this automatically for wp-admin, and it's usefulness is debatable on the front-end. If nothing
	 * else, though, it cuts down on the number of HackerOne reports we get from researchers looking for
	 * low-hanging fruit.
	 *
	 * The oEmbed endpoints should remain embedable.
	 */
	if ( ! isset( $wp->query_vars['embed'] ) || ! $wp->query_vars['embed'] ) {
		$headers['X-Frame-Options'] = 'SAMEORIGIN';
	}

	return $headers;
}
