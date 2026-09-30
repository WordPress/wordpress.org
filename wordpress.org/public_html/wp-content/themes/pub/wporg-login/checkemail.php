<?php
/**
 * The post-resetpassword Template
 *
 * @package wporg-login
 */

get_header();
?>

<p class="center singleline"><?php esc_html_e( 'Check your email for a confirmation link.', 'wporg' ); ?></p>

<p id="nav">
	<a href="/"><?php esc_html_e( '&larr; Back to login', 'wporg' ); ?></a> &nbsp; • &nbsp;
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php esc_html_e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>