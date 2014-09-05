<?php

/*
 * Workarounds for our janky SSL setup. This can be probably removed after the subdirectory migration is complete.
 */

/*
 * We don't support SSL on most of WordCamp.org yet, so users with `use_ssl` turned on from previous years
 * are prevented from accessing the admin of their site. When they try, they're redirected to login again.
 */
add_action( 'get_user_option_use_ssl', '__return_false' );

/*
 * Disable secure cookies on domains that don't support HTTPS
 *
 * r28609 (shipped in 4.0) changes the behavior of FORCE_SSL_LOGIN so that it is identical to FORCE_SSL_ADMIN.
 * This causes a redirect loop when logging in.
 *
 * See https://core.trac.wordpress.org/ticket/10267#comment:22 for details.
 */
if ( ! empty( $_REQUEST['redirect_to'] ) && 'http://' == substr( $_REQUEST['redirect_to'], 0, 7 ) ) {
	add_filter( 'secure_signon_cookie',    '__return_false' );
	add_filter( 'secure_auth_cookie',      '__return_false' );
	add_filter( 'secure_logged_in_cookie', '__return_false' );
}

/**
 * Use the HTTP URL scheme when linking to sub-sites.
 *
 * Within the network admin screens, links to sites are being generated as HTTPS because the root domain
 * uses HTTPS. Most of the sites aren't covered by a cert yet, though, so the user gets a browser warning
 * when opening the link.
 */
function use_http_url_scheme( $url, $scheme, $original_scheme ) {
	if ( is_network_admin() ) {
		$hostname = parse_url( $url, PHP_URL_HOST );

		if ( $hostname != $_SERVER['HTTP_HOST'] ) {
			$url = str_replace( 'https://', 'http://', $url );
		}
	}

	return $url;
}
add_filter( 'set_url_scheme', 'use_http_url_scheme', 10, 3 );
