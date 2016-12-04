<?php
/**
 * The post-register profile-fields Template
 *
 * @package wporg-login
 */

$profile_user = isset( WP_WPOrg_SSO::$matched_route_params['profile_user'] ) ? WP_WPOrg_SSO::$matched_route_params['profile_user'] : false;
$profile_nonce  = isset( WP_WPOrg_SSO::$matched_route_params['profile_nonce'] ) ? WP_WPOrg_SSO::$matched_route_params['profile_nonce'] : false;

$can_access = false;
if (
	$profile_user && $profile_nonce &&
	( $user = get_user_by( 'login', $profile_user ) ) &&
	$user->exists()
) {
	wp_set_current_user( $user->ID );
	$can_access = wp_verify_nonce( $profile_nonce, 'login-register-profile-edit' );
}

if ( ! $can_access ) {
	wp_set_current_user( 0 );
	wp_safe_redirect( '/' );
	die();
}

wp_enqueue_script( 'wporg-registration' );

get_header();
?>
<div class="message info"><p><?php _e( "Please check your email for a confirmation link to set your password.", 'wporg-login' ); ?></p></div>

<p class="intro">
<?php _e( 'Complete your WordPress.org Profile information.', 'wporg-login' ); ?>
</p>

<form name="registerform" id="registerform" action="" method="post">

	<?php include __DIR__ . '/partials/register-profilefields.php'; ?>

	<p class="login-submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Save Profile Information', 'wporg-login' ); ?>" />
	</p>

</form>

<p id="nav">
	<a href="https://wordpress.org/"><?php _e( 'WordPress.org', 'wporg-login' ); ?></a>	
</p>

<?php get_footer(); ?>
