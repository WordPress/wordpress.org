<?php

/**
 * Redirect away from the login page if you're already logged in
 */
function bb_theme_login_redirect() {
	if ( is_user_logged_in() && is_page( 'login' ) ) {
		wp_safe_redirect( 'https://buddypress.org/support/' );
		exit();
	}
}
add_action( 'bbp_template_redirect', 'bb_theme_login_redirect', 11 );
