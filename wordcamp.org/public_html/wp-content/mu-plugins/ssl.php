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
function wcorg_use_http_url_scheme( $url, $scheme, $original_scheme ) {
	if ( is_network_admin() ) {
		$hostname = parse_url( $url, PHP_URL_HOST );

		if ( $hostname != $_SERVER['HTTP_HOST'] ) {
			$url = str_replace( 'https://', 'http://', $url );
		}
	}

	return $url;
}
add_filter( 'set_url_scheme', 'wcorg_use_http_url_scheme', 10, 3 );

/**
 * Load certain stylesheets from the WordPress.org CDN instead of their local copies.
 *
 * On the login screen and Network Dashboard, many stylesheets that are loaded from the canonical
 * WordCamp.org domain get redirected to central.wordcamp.org by an Nginx rule, and then blocked
 * by the browser because they're loading over HTTP instead of HTTPS.
 *
 * Note: s.w.org runs trunk while WordCamp.org runs the latest tag, so be careful to only do this for
 * stylesheets that are unlikely to have a significant impact if out of sync.
 *
 * @param WP_Styles $styles
 */
function wcorg_load_select_core_styles_from_cdn( $styles ) {
	global $pagenow;

	if ( ! is_network_admin() && 'wp-login.php' != $pagenow ) {
		return;
	}

	$targets = array( 'dashicons' );

	foreach ( $targets as $target ) {
		if ( isset( $styles->registered[ $target ]->src ) ) {
			$styles->registered[ $target ]->src = 'https://s.w.org' . $styles->registered[ $target ]->src;
		}
	}
}
add_action( 'wp_default_styles', 'wcorg_load_select_core_styles_from_cdn' );

/**
 * Load certain stylesheets from the WordPress.org CDN instead of their local copies.
 *
 * See notes in load_select_core_styles_from_cdn() for details.
 *
 * @param string $hook
 */
function wcorg_load_select_plugin_styles_from_cdn( $hook ) {
	if ( ! is_network_admin() ) {
		return;
	}

	global $wp_styles;
	$targets = array( 'debug-bar' );

	foreach ( $targets as $target ) {
		if ( isset( $wp_styles->registered[ $target ]->src ) ) {
			$wp_styles->registered[ $target ]->src = str_replace( 'https://' . $_SERVER['HTTP_HOST'], 'https://s.w.org', $wp_styles->registered[ $target ]->src );
		}
	}

}
add_action( 'admin_enqueue_scripts', 'wcorg_load_select_plugin_styles_from_cdn' );

/* The bbPress login widget POSTs to the HTTPS login page on individual sites, but the SSL certificate is broken on those, so we need it to post to the main site instead */
add_action( 'bbp_wp_login_action', 'wcorg_bbpress_post_login_to_main_site' );
function wcorg_bbpress_post_login_to_main_site( $form_action_url ) {
	return 'https://wordcamp.org/wp-login.php';
}

/**
 * WP Super Cache puts http and https requests in the same bucket which
 * generates mixed content warnings and generat breakage all around. The
 * following makes sure only HTTPS requests are cached.
 */
add_action( 'init', function() {
        if ( ! is_ssl() )
                define( 'DONOTCACHEPAGE', true );
});

/**
 * Force HTTPS on all WordCamp.org sites that support it.
 */
function wcorg_force_ssl() {
	if ( php_sapi_name() == 'cli' || is_ssl() )
		return;

	// Our SSL certificate covers only *.wordcamp.org but year.city.wordcamp.org rediercts should still work.
	if ( ! preg_match( '#^(?:[^.]+\.)?wordcamp\.org$#i', $_SERVER['HTTP_HOST'] ) )
		return;

	header( 'Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301 );
	exit;
}

add_action( 'muplugins_loaded', 'wcorg_force_ssl' );