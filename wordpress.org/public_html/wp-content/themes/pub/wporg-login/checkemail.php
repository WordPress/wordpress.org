<?php
/**
 * The post-resetpassword Template
 *
 * @package wporg-login
 */

get_header();
?>

<p class="center singleline"><?php _e( 'Check your email for a confirmation link.', 'wporg' ); ?></p>

<p id="nav">
	<a href="/"><?php _e( '&larr; Back to login', 'wporg' ); ?></a> &nbsp; • &nbsp;
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>