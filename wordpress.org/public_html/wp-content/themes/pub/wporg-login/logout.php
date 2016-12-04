<?php
/**
 * The logging-out Template
 *
 * @package wporg-login
 */

get_header();
?>
<p class="intro"><?php _e( 'You are attempting to log out of WordPress.org.', 'wporg-login' ); ?></p>

<?php
printf(
	/* translators: %s: logout URL */
	__( 'Do you really want to <a href="%s">log out</a>?', 'wporg-login' ),
	wp_logout_url( home_url( '/loggedout/ ' ) )
);
?>

<?php get_footer(); ?>