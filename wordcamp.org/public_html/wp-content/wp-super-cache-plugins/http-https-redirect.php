<?php

namespace WordCamp\WPSC_Plugins\HTTP_HTTPS_Redirect;
defined( 'WPCACHEHOME' ) or die();

add_cacheaction( 'cache_init', __NAMESPACE__ . '\redirect_to_https' );

/**
 * Redirect HTTP requests to HTTPS
 *
 * Redirecting to HTTPS is normally done by `wcorg_force_ssl()`, and `mu-plugins/ssl.php` has another function
 * that sets `DONOTCACHEPAGE` on HTTP requests. Between those two functions, HTTP requests should never be
 * cached, and cached HTTPS pages should never be served for HTTP requests. Since HTTP requests don't get cached
 * pages, WP should be fully loaded, and `wcorg_force_ssl()` should be ran to redirect the request to HTTPS.
 *
 * However, after upgrading to Super Cache 1.4.9, HTTP requests started being served cached pages intermittenly.
 * I haven't been able to reproduce it in my dev environment, and tracking it down is taking time away from more
 * important things, so this is a workaround that should ensure that HTTP requests are always redirected to HTTPS
 * and never cached.
 */
function redirect_to_https() {
	if ( ! function_exists( 'is_ssl' ) || is_ssl() ) {
		return;
	}

	header( 'Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301 );
	exit;
}

