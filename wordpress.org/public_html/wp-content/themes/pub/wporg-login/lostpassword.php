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
	<p class="intro"><?php esc_html_e( 'Please enter your username or email address. You will receive a link to create a new password via email.', 'wporg' ); ?></p>
	<p>
		<label for="user_login"><?php esc_html_e( 'Username or Email', 'wporg' ); ?>
		<input type="text" name="user_login" id="user_login" value="<?php echo esc_attr( $user ); ?>" size="20"></label>
	</p>
	<?php do_action( 'lostpassword_form' ); ?>
	<input type="hidden" name="redirect_to" value="/checkemail/">
	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="<?php esc_attr_e( 'Get new password', 'wporg' ); ?>">
	</p>
</form>
<p id="nav">
	<a href="/"><?php esc_html_e( '&larr; Back to login', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>
