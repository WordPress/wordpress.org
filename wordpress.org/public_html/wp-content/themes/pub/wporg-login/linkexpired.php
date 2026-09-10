<?php
/**
 * The expired link Template
 *
 * @package wporg-login
 */

$reason = WP_WPOrg_SSO::$matched_route_params['reason'] ?? false;
$user   = WP_WPOrg_SSO::$matched_route_params['user'] ?? false;

get_header();
?>

<h2 class="center"><?php esc_html_e( 'Link Expired', 'wporg' ); ?></h2>

<?php
if ( 'register' == $reason && $user ) {
	echo '<p class="center">' . esc_html__( 'The link you&#8217;ve followed has expired.', 'wporg' ) . '</p>';

	echo '<p class="center"><a href="' . esc_url( home_url( '/register/' . urlencode( $user ) ) ) . '">' .
		sprintf(
			/* translators: %s: An account name. */
			esc_html__( 'Start over, and register %s.', 'wporg' ),
			'<code>' . esc_html( $user ) . '</code>'
		) .
		'</a></p>';
} elseif ( 'lostpassword' == $reason && $user ) {
	echo '<p class="center">' . esc_html__( 'The link you&#8217;ve followed has expired.', 'wporg' ) . '</p>';
	echo '<p class="center"><a href="' . esc_url( home_url( '/lostpassword/'  . urlencode( $user ) ) ) . '">' .
			esc_html__( 'Reset your password.', 'wporg' ) .
			'</a></p>';
} elseif ( 'account-created' === $reason ) {
	echo '<p class="center">' . esc_html__( 'That account has already been created.', 'wporg' ) . '</p>';

	echo '<p class="center"><a href="' . esc_url( add_query_arg( 'user', $user, home_url() ) ) . '">' . esc_html__( 'Please log in to continue.', 'wporg' ) . '</a></p>';

	echo '<p class="center"><a href="' . esc_url( home_url( '/lostpassword/'  . urlencode( $user ) ) ) . '">' .
		esc_html__( 'Reset your password.', 'wporg' ) .
		'</a></p>';

} elseif ( 'register-logged-in' === $reason ) {
	echo '<p class="center">' . sprintf(
		/* translators: %s: Forum guidelines URL. */
		wp_kses_post( __( 'Please do not make multiple WordPress.org accounts. Please read the <a href="%s">Forum Guidelines</a> for more information.', 'wporg' ) ),
		'https://wordpress.org/support/guidelines/#do-not-create-multiple-accounts-sockpuppets'
	) . '</p>';

	echo '<p class="center">' . esc_html__( 'Please log out, and follow the link again to complete the registration.', 'wporg' ) . '</p>';

	echo '<p class="center">' . sprintf(
		/* translators: %s: logout URL */
		wp_kses_post( __( 'Do you really want to <a href="%s">log out</a>?', 'wporg' ) ),
		esc_url( wp_logout_url() )
	) . '</p>';

} else {
	echo '<p class="center">' . esc_html__( 'The link you&#8217;ve followed has expired.', 'wporg' ) . '</p>';
}
?>

<p id="nav">
	<a href="/"><?php esc_html_e( '&larr; Back to login', 'wporg' ); ?></a>  &nbsp; • &nbsp;
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php esc_html_e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>
