<?php
/**
 * The logging-out Template
 *
 * @package wporg-login
 */

// This will be validated at redirect time.
$redirect_to = !empty( $_GET['redirect_to'] ) ? $_GET['redirect_to'] : home_url( '/loggedout/' );

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( $redirect_to );
	exit;
}

get_header();
?>
<p class="intro"><?php _e( 'You are attempting to log out of WordPress.org.', 'wporg' ); ?></p>

<?php

printf(
	/* translators: %s: logout URL */
	__( 'Do you really want to <a href="%s">log out</a>?', 'wporg' ),
	wp_logout_url( $redirect_to )
);
?>

<?php get_footer(); ?>