<?php

/*
 * Customizations related to HTTPS.
 */

/*
 * Disable secure cookies on domains that don't support HTTPS
 *
 * r28609 (shipped in 4.0) changes the behavior of FORCE_SSL_LOGIN so that it is identical to FORCE_SSL_ADMIN.
 * This causes a redirect loop when logging in.
 *
 * See https://core.trac.wordpress.org/ticket/10267#comment:22 for details.
 */
if ( ! empty( $_REQUEST['redirect_to'] ) && 'http://' == substr( $_REQUEST['redirect_to'], 0, 7 ) ) {
	// todo remove this now that all sites have certificates?
	
	add_filter( 'secure_signon_cookie',    '__return_false' );
	add_filter( 'secure_auth_cookie',      '__return_false' );
	add_filter( 'secure_logged_in_cookie', '__return_false' );
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
