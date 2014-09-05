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
