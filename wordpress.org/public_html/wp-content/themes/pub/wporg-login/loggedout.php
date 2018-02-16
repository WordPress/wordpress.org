<?php
/**
 * The logged out Template
 *
 * @package wporg-login
 */

get_header();
?>

<p class="center"><?php _e( 'You are now logged out.', 'wporg' ); ?></p>

<p id="nav">
	<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( '&larr; Back to login', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>