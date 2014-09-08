<?php
/**
 * Plugin Name: Disallow Password Change/Reset
 * Plugin Description: Disallows password change and reset across the WordCamp.org network
 */

add_filter( 'allow_password_reset', '__return_false' );
add_filter( 'show_password_fields', '__return_false' );

/**
 * Redirect users to WordPress.org to reset their passwords.
 *
 * Otherwise, there's nothing to indicate where they can reset it.
 */
function wcorg_reset_passwords_at_wporg() {
	wp_redirect( 'https://wordpress.org/support/bb-login.php' );
	die();
}
add_action( 'login_form_lostpassword', 'wcorg_reset_passwords_at_wporg' );
