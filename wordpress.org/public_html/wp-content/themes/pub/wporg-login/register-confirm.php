<?php
/**
 * The post-register profile-fields Template
 *
 * @package wporg-login
 */

	//		'register-confirm' => '/register/confirm/(?P<confirm_user>[^/]+)/(?P<confirm_key>[^/]+)',

$confirm_user = isset( WP_WPOrg_SSO::$matched_route_params['confirm_user'] ) ? WP_WPOrg_SSO::$matched_route_params['confirm_user'] : false;
$confirm_key  = isset( WP_WPOrg_SSO::$matched_route_params['confirm_key'] ) ? WP_WPOrg_SSO::$matched_route_params['confirm_key'] : false;

$can_access = true;
if (
	$confirm_user && $confirm_key &&
	( $user = get_user_by( 'login', $confirm_user ) ) &&
	$user->exists()
) {
	wp_set_current_user( $user->ID );
	list( $reset_time, $hashed_activation_key ) = explode( ':', $user->user_activation_key, 2 );

	$wp_hasher = new PasswordHash( 8, true );
	$can_access = $wp_hasher->CheckPassword( $confirm_key, $hashed_activation_key );

	// Keys are only valid for 7 days (or until used)
	$can_access = $can_access && ( $reset_time + ( 7*DAY_IN_SECONDS ) > time() );
}

if ( ! $can_access ) {
	wp_set_current_user( 0 );
	wp_safe_redirect( "/" );
	die();
} elseif ( !empty( $_POST['user_pass'] ) ) {
	$user_pass = wp_unslash( $_POST['user_pass'] );

	wporg_login_save_profile_fields();

	add_filter( 'send_email_change_email', '__return_false' );
	if ( wp_update_user( wp_slash( array(
		'ID' => $user->ID,
		'user_pass' => $user_pass,
	) ) ) ) {
		$wpdb->update( $wpdb->users, array( 'user_activation_key' => '' ), array( 'ID' => $user->ID ) );
		wp_set_auth_cookie( $user->ID, true );
		wp_safe_redirect( 'https://wordpress.org/support/' );
		die();
	}
}

wp_enqueue_script( 'zxcvbn' );
wp_enqueue_script( 'user-profile' );
wp_enqueue_script( 'wporg-registration' );

get_header();
?>

<p class="intro">
<?php _e( 'Set your password and complete your WordPress.org Profile information.', 'wporg-login' ); ?>
</p>

<form name="registerform" id="registerform" action="" method="post">

		<div class="user-pass1-wrap">
		<p>
			<label for="pass1"><?php _e( 'Password', 'wporg-login' ); ?></label>
		</p>

		<div class="wp-pwd">
			<span class="password-input-wrapper">
				<input type="password" data-reveal="1" data-pw="<?php echo esc_attr( wp_generate_password( 16 ) ); ?>" name="user_pass" id="pass1" class="input" size="20" value="" autocomplete="off" aria-describedby="pass-strength-result" />
			</span>
			<div id="pass-strength-result" class="hide-if-no-js" aria-live="polite"><?php _e( 'Strength indicator', 'wporg-login' ); ?></div>
		</div>
	</div>

<!--	<p class="description indicator-hint"><?php _e( 'Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).', 'wporg-login' ); ?></p> -->

	<?php include __DIR__ . '/partials/register-profilefields.php'; ?>

	<p class="login-submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Create Account', 'wporg-login' ); ?>" />
	</p>

</form>

<p id="nav">
	<a href="https://wordpress.org/"><?php _e( 'WordPress.org', 'wporg-login' ); ?></a>
</p>

<?php get_footer();
