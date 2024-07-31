<?php
use function WordPressdotorg\Two_Factor\{ user_requires_2fa, user_should_2fa, get_edit_account_url };
/**
 * The 'Enable 2FA' notification screen.
 *
 * @package wporg-login
 */

$user         = wp_get_current_user();
$requires_2fa = user_requires_2fa( $user );
$should_2fa   = user_should_2fa( $user ); // If they're on this page, this should be truthful.
$redirect_to  = wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ?? '' ), wporg_login_wordpress_url() );

/*
 * Record the last time we naged the user about 2FA.
 * See WPORG_SSO::maybe_redirect_to_enable_2fa().
 * Note, this isn't in the above function, incase the redirect ultimately filtered to elsewhere.
 */
update_user_meta( $user->ID, 'last_2fa_nag', time() );

get_header();
?>

<h2 class="center"><?php _e( 'Two-Factor Authentication', 'wporg-login' ); ?></h2>

<p>&nbsp;</p>

<p><?php
	if ( $requires_2fa ) {
		_e( 'WordPress.org now requires that your account be protected by two-factor authentication. Some capabilities may be limited until your account is protected.', 'wporg-login' );
	} else {
		_e( "WordPress.org supports two-factor authentication and you'll soon be required to configure it on your account.", 'wporg-login' );
	}
?></p>

<p>&nbsp;</p>

<p><?php printf( __( 'For more information on our two-factor options, please read the <a href="%s" target="_blank">documentation</a>.', 'wporg-login' ), 'https://make.wordpress.org/meta/handbook/tutorials-guides/configuring-two-factor-authentication/' ); ?></p>

<p>&nbsp;</p>

<p><a href="<?php echo esc_url( get_edit_account_url() ); ?>"><button class="button-primary"><?php _e( "OK, I'll setup Two-Factor now.", 'wporg-login' ); ?></button></a></p>

<?php if ( ! $requires_2fa ) { ?>
<p id="nav">
	<a href="<?php echo esc_url( $redirect_to ); ?>" style="font-style: italic;"><?php _e( "I'll do it later", 'wporg-login' ); ?></a>
</p>
<?php } ?>

<?php get_footer(); ?>
