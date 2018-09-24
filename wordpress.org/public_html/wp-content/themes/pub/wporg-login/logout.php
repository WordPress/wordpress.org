<?php
/**
 * The logging-out Template
 *
 * @package wporg-login
 */

get_header();
?>
<p class="intro"><?php _e( 'You are attempting to log out of WordPress.org.', 'wporg' ); ?></p>

<?php

// This will be validated at redirect time.
$redirect_to = !empty( $_GET['redirect_to'] ) ? $_GET['redirect_to'] : home_url( '/loggedout/' );

printf(
	/* translators: %s: logout URL */
	__( 'Do you really want to <a href="%s">log out</a>?', 'wporg' ),
	wp_logout_url( $redirect_to )
);
?>

<?php get_footer(); ?>