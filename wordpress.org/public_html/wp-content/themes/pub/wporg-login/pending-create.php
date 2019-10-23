<?php
/**
 * The post-email-confirm Template
 *
 * @package wporg-login
 */

$activation_user = WP_WPOrg_SSO::$matched_route_params['confirm_user'] ?? false;
$activation_key  = WP_WPOrg_SSO::$matched_route_params['confirm_key']  ?? false;

$pending_user = wporg_get_pending_user( $activation_user );
if ( ! $pending_user ) {
	wp_safe_redirect( home_url( '/linkexpired/register/' . urlencode( $activation_user ) ) );
	exit;
}

$can_access = false;
if ( $pending_user && $pending_user['user_activation_key'] && ! $pending_user['created'] ) {
	$expiration_duration = 2 * WEEK_IN_SECONDS; // Time that the user has to confirm the account.

	list( $user_request_time, $hashed_activation_key ) = explode( ':', $pending_user['user_activation_key'], 2 );
	$expiration_time                                   = $user_request_time + $expiration_duration;

	$hash_is_correct = wp_check_password( $activation_key, $hashed_activation_key );

	if ( $hash_is_correct && time() < $expiration_time ) {
		$can_access = true;
	} elseif ( $hash_is_correct ) {
		wp_safe_redirect( home_url( '/linkexpired/register/' . urlencode( $activation_user ) ) );
		exit;
	}
} elseif ( $pending_user && $pending_user['created'] ) {
	wp_safe_redirect( 'https://wordpress.org/support/' );
	die();
}

if ( ! $can_access ) {
	wp_safe_redirect( "/" );
	die();
}

// Check reCaptcha status
$error_recapcha_status = false;
if ( isset( $_POST['user_pass'] ) ) {
	if ( ! wporg_login_check_recapcha_status( 'pending_create' ) ) {
		// No no. "Please try again."
		$error_recapcha_status = true;
		unset( $_POST['user_pass'] );
	}
}

if ( wporg_login_save_profile_fields( $pending_user ) ) {
	// re-fetch the user, it's probably changed.
	$pending_user = wporg_get_pending_user( $activation_user );
}

if ( isset( $_POST['user_pass'] ) ) {
	$user_pass = wp_unslash( $_POST['user_pass'] );

	if ( $pending_user && ! $pending_user['created'] ) {
		$user = wporg_login_create_user_from_pending( $pending_user, $user_pass );
		if ( $user ) {
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true );
		}
	}

	wp_safe_redirect( 'https://wordpress.org/support/' );
	die();
}

wp_enqueue_script( 'zxcvbn' );
wp_enqueue_script( 'user-profile' );
wp_enqueue_script( 'wporg-registration' );

get_header();
?>

<p class="intro">
<?php _e( 'Set your password and complete your WordPress.org Profile information.', 'wporg' ); ?>
</p>

<form name="registerform" id="registerform" action="" method="post">

		<div class="user-pass1-wrap">
		<p>
			<label for="pass1"><?php _e( 'Password', 'wporg' ); ?></label>
		</p>

		<div class="wp-pwd">
			<span class="password-input-wrapper">
				<input type="password" data-reveal="1" data-pw="<?php echo esc_attr( wp_generate_password( 16 ) ); ?>" name="user_pass" id="pass1" class="input" size="20" value="" autocomplete="off" aria-describedby="pass-strength-result" />
			</span>
			<div id="pass-strength-result" class="hide-if-no-js" aria-live="polite"><?php _e( 'Strength indicator', 'wporg' ); ?></div>
		</div>
	</div>

<!--	<p class="description indicator-hint"><?php _e( 'Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).', 'wporg' ); ?></p> -->

	<?php
		$fields = &$pending_user['meta'];
		include __DIR__ . '/partials/register-profilefields.php';
	?>
	<?php
		if ( $error_recapcha_status ) {
			echo '<div class="message error"><p>' . __( 'Please try again.', 'wporg' ) . '</p></div>';
		}
	?>

	<p class="login-submit">
		<input data-sitekey="<?php echo esc_attr( RECAPTCHA_INVIS_PUBKEY ); ?>" data-callback='onSubmit' type="submit" name="wp-submit" id="wp-submit" class="g-recaptcha button button-primary button-large" value="<?php esc_attr_e( 'Create Account', 'wporg' ); ?>" />
	</p>

</form>

<p id="nav">
	<a href="https://wordpress.org/"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer();
