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

<h2 class="center"><?php _e( 'Link Expired', 'wporg' ); ?></h2>

<p class="center"><?php _e( "The link you've followed has expired.", 'wporg' ); ?></p>

<?php
if ( 'register' == $reason && $user ) {
		echo '<p class="center"><a href="' . esc_url( home_url( '/register/' . urlencode( $user ) ) ) . '">' .
			sprintf(
				/* translators: %s: An account name. */
				__( 'Start over, and register %s.', 'wporg' ),
				'<code>' . esc_html( $register_user ) . '</code>'
			) .
			'</a></p>';
} elseif ( 'lostpassword' == $reason && $user ) {
	echo '<p class="center"><a href="' . esc_url( home_url( '/lostpassword/'  . urlencode( $user ) ) ) . '">' .
			__( 'Reset your password.', 'wporg' ) .
			'</a></p>';
}
?>

<p id="nav">
	<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( '&larr; Back to login', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>
