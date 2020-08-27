<?php
/**
 * The post-pending-email-confirm profile-fields Template
 *
 * @package wporg-login
 */

 // Migrate to cookies.
if ( !empty( WP_WPOrg_SSO::$matched_route_params['profile_user'] ) ) {
	setcookie( 'wporg_profile_user', WP_WPOrg_SSO::$matched_route_params['profile_user'], time()+DAY_IN_SECONDS, '/register/', 'login.wordpress.org', true, true );
	setcookie( 'wporg_profile_key',  WP_WPOrg_SSO::$matched_route_params['profile_key'],  time()+DAY_IN_SECONDS, '/register/', 'login.wordpress.org', true, true );

	wp_safe_redirect( '/register/create-profile' );
	die();
}

$profile_user = $_COOKIE['wporg_profile_user'] ?? false;
$profile_key  = $_COOKIE['wporg_profile_key']  ?? false;

$pending_user = wporg_get_pending_user( $profile_user );

$can_access = false;
if ( $pending_user && $pending_user['user_profile_key'] ) {
	$expiration_duration = DAY_IN_SECONDS; // The profile-edit screen is short lived.

	list( $user_request_time, $hashed_profile_key ) = explode( ':', $pending_user['user_profile_key'], 2 );
	$expiration_time                                = $user_request_time + $expiration_duration;

	$hash_is_correct = wp_check_password( $profile_key, $hashed_profile_key );

	if ( $hash_is_correct && time() < $expiration_time ) {
		$can_access = true;
	}
}

if ( $can_access && $pending_user['created']  ) {
	wp_safe_redirect( 'https://wordpress.org/support/' );
	die();
} elseif ( ! $can_access ) {
	wp_safe_redirect( home_url( '/linkexpired' ) );
	die();
}

if ( wporg_login_save_profile_fields( $pending_user ) ) {
	// re-fetch the user, it's probably changed.
	$pending_user = wporg_get_pending_user( $profile_user );
}
wp_enqueue_script( 'wporg-registration' );

get_header();
?>
<form name="registerform" id="registerform" action="" method="post">

	<div class="message info">
		<p><?php
			printf(
				/* translators: %s Email address */
				__( 'Please check your email %s for a confirmation link to set your password.', 'wporg' ) . '<br>' .
				'<a href="#" class="resend" data-account="%s">' . __( 'Resend confirmation email.', 'wporg' ) . '</a>',
				'<code>' . esc_html( $pending_user['user_email'] ) . '</code>',
				esc_attr( $pending_user['user_email'] )
			);
		?></p>
	</div>

	<p class="intro">
	<?php _e( 'Complete your WordPress.org Profile information.', 'wporg' ); ?>
	</p>

	<p class="login-login">
		<label for="user_login"><?php _e( 'Username', 'wporg' ); ?></label>
		<input type="text" disabled="disabled" class=" disabled" value="<?php echo esc_attr( $profile_user ); ?>" size="20" />
	</p>


	<?php
		$fields = &$pending_user['meta'];
		include __DIR__ . '/partials/register-profilefields.php';
	?>

	<p class="login-submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Save Profile Information', 'wporg' ); ?>" />
	</p>

</form>

<p id="nav">
	<a href="https://wordpress.org/"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>
