<?php
use function WordPressdotorg\Two_Factor\get_edit_account_url;
/**
 * The 'Recovery Codes' post-login screen.
 *
 * This template is used for two primary purposes:
 * 1. The user has logged in with a recovery code, we need to push them to verify their 2FA settings.
 * 2. The user is running low on recovery codes (or has none!), we need to remind them to generate new ones.
 *
 * @package wporg-login
 */

$account_settings_url = get_edit_account_url();
$redirect_to          = wporg_login_wordpress_url();
$user                 = wp_get_current_user();
$session              = WP_Session_Tokens::get_instance( $user->ID )->get( wp_get_session_token() );
$used_recovery_code   = str_contains( $session['two-factor-provider'] ?? '', 'Backup_Codes' );
$codes_available      = Two_Factor_Backup_Codes::codes_remaining_for_user( $user );
$can_ignore           = ! $used_recovery_code || ( $used_recovery_code && $codes_available > 1 );

if ( isset( $_REQUEST['redirect_to'] ) ) {
	$redirect_to = wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ), $redirect_to );
}

// If the user is here in error, redirect off.
if ( ! is_user_logged_in() || ! Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
	wp_safe_redirect( $redirect_to );
	exit;
}

/**
 * Record the last time we nagged the user about recovery codes, as we only want to do this once per code-use.
 */
update_user_meta( $user->ID, 'last_2fa_recovery_codes_nag', $codes_available );

get_header();
?>

<h2 class="center"><?php
	if ( $used_recovery_code ) {
		_e( 'Recovery Code used', 'wporg-login' );
	} else {
		_e( 'Account Recovery Codes', 'wporg-login' );
	}
?></h2>

<p>&nbsp;</p>

<p><?php
	if ( $used_recovery_code ) {
		_e( "You've logged in with a Recovery Code.<br>These codes are intended to be used when you lose access to your authentication device.<br>Please take a moment to review your account settings and ensure your Two-Factor settings are up-to-date.", 'wporg-login' );
	} else {
		if ( ! $codes_available ) {
			_e( "Warning! You don't have any Recovery Codes left.", 'wporg-login' );
		} else {
			printf(
				_n(
					"Warning! You've only got %s Recovery Code left.",
					"Warning! You've only got %s Recovery Codes left.",
					$codes_available,
					'wporg-login'
				),
				'<code>' . number_format_i18n( $codes_available ) . '</code>'
			);
		}

		// Direct to the backup codes screen.
		$account_settings_url = add_query_arg( 'screen', 'backup-codes', $account_settings_url );
	}
?></p>

<p>&nbsp;</p>

<p><?php
	_e( 'If you run out of Recovery Codes and no longer have access to your Authentication device, you are at risk of being locked out of your WordPress.org account if we are unable to verify account ownership.', 'wporg-login' );
?></p>

<p>&nbsp;</p>

<p><a href="<?php echo esc_url( $account_settings_url ); ?>"><button class="button-primary"><?php _e( 'Take me to Account Settings', 'wporg-login' ); ?></button></a></p>

<?php if ( $can_ignore ) { ?>
	<p id="nav">
		<a href="<?php echo esc_url( $redirect_to ); ?>" style="font-style: italic;"><?php _e( "I'll do this later", 'wporg-login' ); ?></a>
	</p>
<?php } ?>

<?php get_footer(); ?>
