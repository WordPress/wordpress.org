<?php
/**
 * The new registration Template
 *
 * @package wporg-login
 */

$user_login = isset( $_POST['user_login'] ) ? wp_unslash( $_POST['user_login'] ) : '';
$user_email = isset( $_POST['user_email'] ) ? wp_unslash( $_POST['user_email'] ) : '';
$user_mailinglist = isset( $_POST['user_mailinglist'] ) && 'true' == $_POST['user_mailinglist'];

$error_user_login = $error_user_email = $error_recapcha_status = false;
if ( $_POST ) {

	$error_user_login = rest_do_request( new WP_REST_Request( 'GET', '/wporg/v1/username-available/' . $user_login ) );
	if ( $error_user_login->get_data()['available'] ) {
		$error_user_login = false;
	}

	$error_user_email = rest_do_request( new WP_REST_Request( 'GET', '/wporg/v1/email-in-use/' . $user_email ) );
	if ( $error_user_email->get_data()['available'] ) {
		$error_user_email = false;
	}

	// handle user registrations.
	if ( ! $error_user_login && ! $error_user_email ) {
		if ( ! wporg_login_check_recapcha_status() ) {
			$error_recapcha_status = true;
		} else {
			wporg_login_create_user( $user_login, $user_email, $user_mailinglist );
			die();
		}
	}

}

wp_enqueue_script( 'wporg-registration' );

get_header();
?>

<p class="intro"><?php _e( 'Create a WordPress.org account to start contributing to WordPress, get help in the support forums, or rate and review themes and plugins.', 'wporg-login' ); ?></p>

<form name="registerform" id="registerform" action="/register" method="post">
	<p class="login-username">
		<label for="user_login"><?php _e( 'Username', 'wporg-login' ); ?></label>
		<input type="text" name="user_login" id="user_login" class="input <?php if ( $error_user_login ) echo 'error'; ?>" value="<?php echo esc_attr( $user_login ) ?>" size="20" maxlength="60" />
		<small><?php _e( 'Required, Only lower case letters (a-z) and numbers (0-9) are allowed.', 'wporg-login' ); ?></small>
	</p>
	<?php
		if ( $error_user_login ) {
			echo '<div class="message error"><p>' . $error_user_login->get_data()['avatar'] . $error_user_login->get_data()['error'] . '</p></div>';
		}
	?>

	<p class="login-email">
		<label for="user_email"><?php _e( 'Email', 'wporg-login' ); ?></label>
		<input type="email" name="user_email" id="user_email" class="input <?php if ( $error_user_email ) echo 'error'; ?>" value="<?php echo esc_attr( $user_email ) ?>" size="20" maxlength="100" />
		<small><?php _e( 'Required, Your password will be emailed here.', 'wporg-login' ); ?></small>
	</p>
	<?php
		if ( $error_user_email ) {
			echo '<div class="message error"><p>' . $error_user_email->get_data()['avatar'] . $error_user_email->get_data()['error'] . '</p></div>';
		}
	?>

	<p class="login-mailinglist">
		<label>
			<input name="user_mailinglist" type="checkbox" id="user_mailinglist" value="true" <?php checked( $user_mailinglist, true ); ?>>
			<?php _e( 'Subscribe to WordPress Announcements mailing list (a few messages a year)', 'wporg-login' ); ?>
		</label>
	</p>

	<p class="login-recaptcha">
		<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( RECAPTCHA_PUBKEY ); ?>"></div>
	</p>
	<?php
		if ( $error_recapcha_status ) {
			echo '<div class="message error"><p>' . __( 'Please try again.', 'wporg-login' ) . '</p></div>';
		}
	?>

	<p class="login-submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Create Account', 'wporg-login' ); ?>" />
	</p>

</form>

<p id="nav">
	<a href="/" title="<?php esc_attr_e( 'Already have an account?', 'wporg-login' ); ?>"><?php _e( 'Already have an account?', 'wporg-login' ); ?></a> &nbsp; • &nbsp;
	<a href="https://wordpress.org/"><?php _e( 'WordPress.org', 'wporg-login' ); ?></a>
	
</p>

<?php get_footer(); ?>
