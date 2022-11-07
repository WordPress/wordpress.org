<?php
/**
 * The post-pending-email-confirm profile-fields Template
 *
 * @package wporg-login
 */

$sso = WPOrg_SSO::get_instance();

// Migrate to cookies.
if ( ! empty( $sso::$matched_route_params['profile_user'] ) ) {
	$cookie_host = $sso->get_cookie_host();

	setcookie( 'wporg_profile_user', $sso::$matched_route_params['profile_user'], time()+DAY_IN_SECONDS, '/register/', $cookie_host, true, true );
	setcookie( 'wporg_profile_key',  $sso::$matched_route_params['profile_key'],  time()+DAY_IN_SECONDS, '/register/', $cookie_host, true, true );

	wp_safe_redirect( '/register/create-profile' );
	die();
}

$profile_user = $_COOKIE['wporg_profile_user'] ?? false;
$profile_key  = $_COOKIE['wporg_profile_key']  ?? false;

$pending_user = wporg_get_pending_user( $profile_user );

// Already logged in.. Warn about duplicate accounts, etc.
if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/linkexpired/register-logged-in' ) );
	exit;
}

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
	wp_safe_redirect( home_url( '/linkexpired/account-created/' . urlencode( $pending_user['user_login'] ) ) );
	die();
} elseif ( ! $can_access ) {
	wp_safe_redirect( home_url( '/linkexpired' ) );
	die();
}

if ( wporg_login_save_profile_fields( $pending_user, 'pending' ) ) {
	// re-fetch the user, it's probably changed.
	$pending_user = wporg_get_pending_user( $profile_user );
}
wp_enqueue_script( 'wporg-registration' );

// Allow changing the email, if they've not already changed it once.
$email_change_available = empty( $pending_user['meta']['changed_email'] );

get_header();
?>
<form name="registerform" id="registerform" action="" method="post">

	<div class="message info">
		<p><?php
		if ( $pending_user['cleared'] ) {
			printf(
				/* translators: %s Email address */
				__( 'Please check your email %s for a confirmation link to set your password.', 'wporg' ) .
				'<br><br>' . '<a href="#" class="resend" data-account="%s">' . __( 'Resend confirmation email.', 'wporg' ) . '</a>' .
				( $email_change_available ? '<br>' . '<a href="#" class="change-email">' . __( 'Incorrect email? Update email address.', 'wporg' ) . '</a>' : '' ),
				'<code>' . esc_html( $pending_user['user_email'] ) . '</code>',
				esc_attr( $pending_user['user_email'] )
			);
		} else {
			printf(
				/* translators: %s Email address */
				__( 'Your account is pending approval. You will receive an email at %s to set your password when approved.', 'wporg' ) . 
				'<br>' . __( 'Please contact %s for more details.', 'wporg' ) .
				( $email_change_available ? '<br><br>' . '<a href="#" class="change-email">' . __( 'Incorrect email? Update email address.', 'wporg' ) . '</a>' : '' ),
				'<code>' . esc_html( $pending_user['user_email'] ) . '</code>',
				'<a href="mailto:' . $sso::SUPPORT_EMAIL . '">' . $sso::SUPPORT_EMAIL . '</a>'
			);
		}

		if ( 'local' === wp_get_environment_type() && ! empty( $_COOKIE['emailed_url'] ) ) {
			printf(
				'<br><br><strong>Local Development</strong>: The URL emailed to you is: <a href="%1$s">%1$s</a>.',
				wp_unslash( $_COOKIE['emailed_url'] )
			);
		}
		?></p>
	</div>

	<p class="intro">
		<?php _e( 'Complete your WordPress.org Profile information.', 'wporg' ); ?>
	</p>

	<p class="login-login">
		<label for="user_login"><?php _e( 'Username', 'wporg' ); ?></label>
		<input type="text" disabled="disabled" class=" disabled" value="<?php echo esc_attr( $profile_user ); ?>" size="20" />
	</p>

	<p class="login-email hidden">
		<label for="user_email"><?php _e( 'Email', 'wporg' ); ?></label>
		<input type="text" name="user_email" value="<?php echo esc_attr( $pending_user['user_email'] ); ?>" size="20" maxlength="100" />
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
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>
