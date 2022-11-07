<?php
/**
 * The post-email-confirm Template
 *
 * @package wporg-login
 */

$sso = WPOrg_SSO::get_instance();

// Migrate to cookies.
if ( ! empty( $sso::$matched_route_params['confirm_user'] ) ) {
	$cookie_host = $sso->get_cookie_host();

	setcookie( 'wporg_confirm_user', $sso::$matched_route_params['confirm_user'], time()+DAY_IN_SECONDS, '/register/', $cookie_host, true, true );
	setcookie( 'wporg_confirm_key',  $sso::$matched_route_params['confirm_key'],  time()+DAY_IN_SECONDS, '/register/', $cookie_host, true, true );

	wp_safe_redirect( '/register/create' );
	die();
}

$activation_user = $_COOKIE['wporg_confirm_user'] ?? false;
$activation_key  = $_COOKIE['wporg_confirm_key']  ?? false;

$pending_user = wporg_get_pending_user( $activation_user );
if ( ! $pending_user ) {
	wp_safe_redirect( home_url( '/linkexpired/register/' . urlencode( $activation_user ) ) );
	exit;
}

// Already logged in.. Warn about duplicate accounts, etc.
if ( is_user_logged_in() && $activation_user != wp_get_current_user()->user_login ) {
	wp_safe_redirect( home_url( '/linkexpired/register-logged-in' ) );
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
	wp_safe_redirect( home_url( '/linkexpired/account-created/' . urlencode( $pending_user['user_login'] ) ) );
	die();
}

if ( ! $can_access ) {
	wp_safe_redirect( '/linkexpired' );
	die();
}

if ( wporg_login_save_profile_fields( $pending_user, 'create' ) ) {
	// re-fetch the user, it's probably changed.
	$pending_user = wporg_get_pending_user( $activation_user );
}

$error_recapcha_status = false;
if ( isset( $_POST['user_pass'] ) && 2 !== $pending_user['cleared'] ) {

	// Check reCaptcha status
	if ( ! wporg_login_check_recapcha_status( 'pending_create', false ) ) {
		unset( $_POST['user_pass'] );
		$error_recapcha_status = true;
	}

	// Store for reference.
	if ( isset( $_POST['_reCaptcha_v3_token'] ) ) {
		$recaptcha_api = wporg_login_recaptcha_api(
			$_POST['_reCaptcha_v3_token'],
			RECAPTCHA_V3_PRIVKEY
		);
		if ( $recaptcha_api && $recaptcha_api['success'] && 'pending_create' == $recaptcha_api['action'] ) {
			$pending_user['scores']['create_attempt'] = $recaptcha_api['score'];
		} // else: probably `timeout-or-duplicate` or w.org network error.
	}

	// Allow a recaptcha fail to try again, but if they're blocked due to low score, mark them as needing approval.
	if (
		$error_recapcha_status &&
		! empty( $pending_user['scores']['create_attempt'] ) &&
		(float) $pending_user['scores']['create_attempt'] < (float) get_option( 'recaptcha_v3_threshold', 0.2 )
	) {
		$pending_user['cleared'] = 0;
	}

	wporg_update_pending_user( $pending_user );
}

if ( ! $pending_user['cleared'] ) {
	if ( ! empty( $_COOKIE['wporg_profile_user'] ) ) {
		// Throw the user back to the pending screen after being detected as spam at this point.
		wp_safe_redirect( '/register/create-profile/' );
		die();
	}

	unset( $_POST['user_pass'] );
}

if ( isset( $_POST['user_pass'] ) ) {
	$user_pass = wp_unslash( $_POST['user_pass'] );

	if ( $pending_user && ! $pending_user['created'] ) {
		$user = wporg_login_create_user_from_pending( $pending_user, $user_pass );
		if ( $user ) {
			$cookie_host = $sso->get_cookie_host();

			// Clear the cookies, they're no longer needed.
			setcookie( 'wporg_profile_user', false, time()-DAY_IN_SECONDS, '/register/', $cookie_host, true, true );
			setcookie( 'wporg_profile_key',  false, time()-DAY_IN_SECONDS, '/register/', $cookie_host, true, true );
			setcookie( 'wporg_confirm_user', false, time()-DAY_IN_SECONDS, '/register/', $cookie_host, true, true );
			setcookie( 'wporg_confirm_key',  false, time()-DAY_IN_SECONDS, '/register/', $cookie_host, true, true );

			// Log the user in
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true );
		}
	}

	if ( 'local' === wp_get_environment_type() ) {
		wp_safe_redirect( home_url() );
	} else {
		wp_safe_redirect( 'https://wordpress.org/support/' );
	}

	die();
}

wp_enqueue_script( 'zxcvbn' );
wp_enqueue_script( 'user-profile' );
wp_enqueue_script( 'wporg-registration' );

get_header();
?>

<form name="registerform" id="registerform" action="" method="post">

	<?php if ( ! $pending_user['cleared'] ) { ?>
	<div class="message info">
		<p><?php
			printf(
				/* translators: %s Email address */
				__( 'Your account is pending approval. You will receive an email at %s to set your password when approved.', 'wporg' ) . '<br>' .
				__( 'Please contact %s for more details.', 'wporg' ),
				'<code>' . esc_html( $pending_user['user_email'] ) . '</code>',
				'<a href="mailto:' . $sso::SUPPORT_EMAIL . '">' . $sso::SUPPORT_EMAIL . '</a>'
			);
		?></p>
	</div>
	<?php } ?>

	<p class="intro">
		<?php _e( 'Set your password and complete your WordPress.org Profile information.', 'wporg' ); ?>
	</p>

	<p class="login-login">
		<label for="user_login"><?php _e( 'Username', 'wporg' ); ?></label>
		<input type="text" disabled="disabled" class="disabled" value="<?php echo esc_attr( $activation_user ); ?>" size="20" />
	</p>

	<div class="user-pass1-wrap" <?php echo ( $pending_user['cleared'] ? '' : "style='display:none;'" ); ?>>
		<p>
			<label for="pass1"><?php _e( 'Password', 'wporg' ); ?></label>
		</p>

		<div class="wp-pwd">
			<span class="password-input-wrapper">
				<input type="password" data-reveal="1" data-pw="<?php echo esc_attr( wp_generate_password( 16 ) ); ?>" name="user_pass" id="pass1" class="input" size="20" value="" autocomplete="off" aria-describedby="pass-strength-result" />
			</span>

			<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" aria-label="<?php esc_attr_e( 'Hide password', 'wporg-login' ); ?>">
				<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
			</button>
			<div id="pass-strength-result" class="hide-if-no-js" aria-live="polite"><?php _e( 'Strength indicator', 'wporg' ); ?></div>
		</div>
	</div>

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
		<input data-sitekey="<?php echo esc_attr( RECAPTCHA_INVIS_PUBKEY ); ?>" data-callback='onSubmit' type="submit" name="wp-submit" id="wp-submit" class="g-recaptcha button button-primary button-large" value="<?php ( $pending_user['cleared'] ? esc_attr_e( 'Create Account', 'wporg' ) : esc_attr_e( 'Save Profile Information', 'wporg' ) ); ?>" />
	</p>

</form>

<p id="nav">
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer();
