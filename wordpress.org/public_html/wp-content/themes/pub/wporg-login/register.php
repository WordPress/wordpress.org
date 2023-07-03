<?php
/**
 * The new registration Template
 *
 * @package wporg-login
 */

$user_login       = isset( $_POST['user_login'] ) && is_string( $_POST['user_login'] ) ? trim( wp_unslash( $_POST['user_login'] ) ) : '';
$user_email       = isset( $_POST['user_email'] ) && is_string( $_POST['user_email'] ) ? trim( wp_unslash( $_POST['user_email'] ) ) : '';
$user_mailinglist = isset( $_POST['user_mailinglist'] ) && 'true' == $_POST['user_mailinglist'];
$terms_of_service = isset( $_POST['terms_of_service'] ) ? intval( $_POST['terms_of_service'] ) : false;

if ( ! $user_login && ! empty( WP_WPOrg_SSO::$matched_route_params['user'] ) ) {
	$user_login = trim( WP_WPOrg_SSO::$matched_route_params['user'] );
}

// Already logged in.. Warn about duplicate accounts, etc.
if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/linkexpired/register-logged-in' ) );
	exit;
}

$error_user_login = $error_user_email = $error_recapcha_status = $terms_of_service_error = false;
if ( $_POST ) {

	/** This filter is documented in wp-includes/user.php */
	$user_login = apply_filters( 'pre_user_login', $user_login );

	/** This filter is documented in wp-includes/user.php */
	$user_email = apply_filters( 'pre_user_email', $user_email );

	$error_user_login = rest_do_request( new WP_REST_Request( 'GET', '/wporg/v1/username-available/' . urlencode( $user_login ) ) );
	if ( $error_user_login->get_data()['available'] ) {
		$error_user_login = false;
	}

	$error_user_email = rest_do_request( new WP_REST_Request( 'GET', '/wporg/v1/email-in-use/' . urlencode( $user_email ) ) );
	if ( $error_user_email->get_data()['available'] ) {
		$error_user_email = false;
	}

	// Don't validate that it's equal to the current revision, just that they've agreed to one.
	// Let the post-login interstitial handle TOS updates at time of registration.
	$terms_of_service_error = ! $terms_of_service || $terms_of_service > TOS_REVISION;

	// handle user registrations.
	if ( ! $error_user_login && ! $error_user_email && ! $terms_of_service_error ) {

		$recaptcha = wporg_login_check_recapcha_status( 'register', false /* Allow low scores to pass through */ );

		if ( ! $recaptcha ) {
			$error_recapcha_status = true;
		} else {
			$tos_meta_key = WPOrg_SSO::TOS_USER_META_KEY;
			$meta = [
				'user_mailinglist' => $user_mailinglist,
				$tos_meta_key      => $terms_of_service,
			];

			wporg_login_create_pending_user( $user_login, $user_email, $meta );
			die();
		}
	}

}

wp_enqueue_script( 'wporg-registration' );

get_header();
?>

<p class="intro"><?php _e( 'Create a WordPress.org account to start contributing to WordPress, get help in the support forums, or rate and review themes and plugins.', 'wporg' ); ?></p>

<form name="registerform" id="registerform" action="/register" method="post">

	<p class="login-username">
		<label for="user_login"><?php _e( 'Username', 'wporg' ); ?></label>
		<input type="text" name="user_login" id="user_login" class="input <?php if ( $error_user_login ) echo 'error'; ?>" value="<?php echo esc_attr( $user_login ) ?>" size="20" maxlength="60" data-pattern-after-blur="[0-9a-z]{0,60}" required />
		<span class="small"><?php _e( 'Only lower case letters (a-z) and numbers (0-9) are allowed.', 'wporg' ); ?></span>
	</p>
	<?php
	if ( $error_user_login ) {
		printf(
			'<div class="message error%s"><p>%s<span>%s</span></p></div>',
			$error_user_login->get_data()['avatar'] ? ' with-avatar' : '',
			$error_user_login->get_data()['avatar'],
			$error_user_login->get_data()['error']
		);
	}
	?>

	<p class="login-email">
		<label for="user_email"><?php _e( 'Email', 'wporg' ); ?></label>
		<input type="email" name="user_email" id="user_email" class="input <?php if ( $error_user_email ) echo 'error'; ?>" value="<?php echo esc_attr( $user_email ) ?>" size="20" maxlength="100" data-pattern-after-blur=".+@.+\..+" required />
		<span class="small"><?php _e( 'A link to set your password will be sent here.', 'wporg' ); ?></span>
	</p>
	<?php
	if ( $error_user_email ) {
		printf(
			'<div class="message error%s"><p>%s<span>%s</span></p></div>',
			$error_user_email->get_data()['avatar'] ? ' with-avatar' : '',
			$error_user_email->get_data()['avatar'],
			$error_user_email->get_data()['error']
		);
	}
	?>

	<p class="login-tos checkbox <?php if ( $terms_of_service_error ) { echo 'message error'; } ?>">
		<label for="terms_of_service">
			<input name="terms_of_service" type="checkbox" id="terms_of_service" value="<?php echo esc_attr( TOS_REVISION ); ?>" <?php checked( $terms_of_service, TOS_REVISION ); ?> required="required">
			<?php
				$localised_domain = parse_url( wporg_login_wordpress_url(), PHP_URL_HOST );
				printf(
					/* translators: %s: List of linked policies, for example: <a>Privacy Policy</a> and <a>Terms of Service</a> */
					_n( 'I have read and accept the %s.', 'I have read and accept the %s.', 1, 'wporg' ),
					wp_sprintf_l( '%l', [
						"<a href='https://{$localised_domain}/about/privacy/'>" . __( 'Privacy Policy', 'wporg' ) . '</a>',
						// "<a href='https://{$localised_domain}/about/terms-of-service/'>" . __( 'Terms of Service', 'wporg' ) . '</a>',
						// "<a href='https://{$localised_domain}/about/code-of-conduct/'>" . __( 'Code of Conduct', 'wporg' ) . '</a>',
					] )
				)
			?>
		</label>
	</p>

	<p class="login-mailinglist checkbox">
		<label for="user_mailinglist">
			<input name="user_mailinglist" type="checkbox" id="user_mailinglist" value="true" <?php checked( $user_mailinglist, true ); ?>>
			<?php _e( 'Subscribe to WordPress Announcements mailing list (a few messages a year)', 'wporg' ); ?>
		</label>
	</p>
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
	<a href="/" title="<?php esc_attr_e( 'Already have an account?', 'wporg' ); ?>"><?php _e( 'Already have an account?', 'wporg' ); ?></a> &nbsp; • &nbsp;
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer();
