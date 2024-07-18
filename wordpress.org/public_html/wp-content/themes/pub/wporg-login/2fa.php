<?php
use function WordPressdotorg\Two_Factor\{ user_requires_2fa, user_should_2fa, get_edit_account_url };
/**
 * The 2FA notification screen.
 *
 * @package wporg-login
 */

$sso          = WPOrg_SSO::get_instance();
$user         = wp_get_current_user();
$requires_2fa = user_requires_2fa( $user );
$should_2fa   = user_should_2fa( $user );
$redirect_to  = wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ?? '' ), wporg_login_wordpress_url() );

get_header();
?>

<h2 class="center"><?php _e( 'Two-Factor Authentication', 'wporg' ); ?></h2>

<p>&nbsp;</p>

<p><?php _e( 'WordPress.org now requires that your account be protected by Two-Factor Authentication.', 'wporg' ); ?></p>

<p>&nbsp;</p>

<p><?php _e( 'Users who are Plugin or Theme Authors, or who have elevated access to any of our Sites &amp; Tools are required to setup Two-Factor Authentication.', 'wporg' ); ?></p>

<p>&nbsp;</p>

<a href="<?php echo esc_url( get_edit_account_url() ); ?>"><button class="button-primary"><?php _e( "OK, I'll setup 2FA now.", 'wporg' ); ?></button></a>

<p class="center">
	<a href="<?php echo esc_url( $redirect_to ); ?>" style="font-style: italic;"><?php _e( "I'll do it later", 'wporg' ); ?></a>
</p>

<p id="nav">
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>