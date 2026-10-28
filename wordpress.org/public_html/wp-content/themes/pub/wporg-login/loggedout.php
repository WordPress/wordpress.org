<?php
/**
 * The logged out Template
 *
 * @package wporg-login
 */

get_header();
?>

<p class="center"><?php esc_html_e( 'You are now logged out.', 'wporg' ); ?></p>

<?php
	$redirect_to = wp_unslash( $_REQUEST['redirect_to'] ?? '' );
	$redirect_to = wp_sanitize_redirect( $redirect_to );
	$redirect_to = wp_validate_redirect( $redirect_to );

	if ( $redirect_to ) {
		$hostname = parse_url( $redirect_to, PHP_URL_HOST );

		echo '<p>&nbsp;</p>';
		echo '<p class="center">';
		printf(
			/* translators: 1: url, 2: Hostname, ie. wordcamp.org */
			wp_kses_post( __( 'Return to <a href="%1$s">%2$s</a>.', 'wporg' ) ),
			esc_url( $redirect_to ),
			esc_html( $hostname )
		);
		echo '</p>';
	}
?>

<p id="nav">
	<a href="/"><?php esc_html_e( '&larr; Back to login', 'wporg' ); ?></a> &nbsp; • &nbsp;
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php esc_html_e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>