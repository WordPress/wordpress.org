<?php
/**
 * The lost-password Template
 *
 * @package wporg-login
 */

$user = WP_WPOrg_SSO::$matched_route_params['user'] ?? false;

get_header();
?>

<form name="lostpasswordform" id="lostpasswordform" action="/wp-login.php?action=lostpassword" method="post">
	<p class="intro"><?php _e( 'Please enter your username or email address. You will receive a link to create a new password via email.', 'wporg' ); ?></p>
	<p>
		<label for="user_login"><?php _e( 'Username or Email', 'wporg' ); ?>
		<input type="text" name="user_login" id="user_login" value="<?php echo esc_attr( $user ); ?>" size="20"></label>
	</p>
	<input type="hidden" name="redirect_to" value="/checkemail/">
	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="<?php esc_attr_e( 'Get new password', 'wporg' ); ?>">
	</p>
</form>
<p id="nav">
	<a href="/"><?php _e( '&larr; Back to login', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>
