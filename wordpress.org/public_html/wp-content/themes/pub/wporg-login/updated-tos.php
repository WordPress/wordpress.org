<?php
/**
 * The Updated Terms of Service page.
 *
 * @package wporg-login
 */

$sso = WPOrg_SSO::get_instance();

$token_cookie      = $_COOKIE[ $sso::LOGIN_TOS_COOKIE ] ?? false;
$login_remember_me = $_COOKIE[ $sso::LOGIN_TOS_COOKIE . '_remember' ] ?? false;

$user_id = wp_validate_auth_cookie( $token_cookie, 'tos_token' );
if (
	! $user_id ||
	! ( $user = get_user_by( 'id', $user_id ) )
) {
	wp_safe_redirect( '/linkexpired' );
	exit;
}

// Set user context for this request only, since the cookies aren't yet set.
wp_set_current_user( $user->ID );

// Record the TOS agreement.
if (
	! empty( $_POST['_tos_nonce'] ) &&
	wp_verify_nonce( $_POST['_tos_nonce'], 'agree_to_tos' )
) {
	// Agreement has been reached.

	$token = wp_parse_auth_cookie( $token_cookie, 'tos_token' )['token'] ?? '';

	update_user_meta( $user->ID, $sso::TOS_USER_META_KEY, TOS_REVISION );
	wp_set_auth_cookie( $user->ID, $login_remember_me, true, $token );

	$sso->redirect_to_source_or_profile();
	exit;
}

// Localised domain for about pages.
$localised_domain = parse_url( wporg_login_wordpress_url(), PHP_URL_HOST );

get_header();
?>

<h2 class="center"><?php _eu( 'Updated Policies', 'wporg' ); ?></h2>

<p>&nbsp;</p>

<p><?php
	printf(
		__u( 'Welcome back %s, Some of our policies have been updated, please review the items below before continuing.', 'wporg' ),
		$user->display_name ?: $user->user_login
	);
?></p>

<p>&nbsp;</p>

<p>
	<a href="https://<?php echo $localised_domain; ?>/about/privacy/"><?php _e( 'Privacy Policy', 'wporg' ); ?></a>
</p>
<?php /* ?>
<p>
	<a href="https://<?php echo $localised_domain; ?>/about/terms-of-service/"><?php _eu( 'Terms of Service', 'wporg' ); ?></a>
</p>
<p>
	<a href="https://<?php echo $localised_domain; ?>/code-of-conduct/"><?php _eu( 'Code of Conduct', 'wporg' ); ?></a>
</p>
<?php //*/ ?>

<p>&nbsp;</p>

<form method="POST">
	<?php wp_nonce_field( 'agree_to_tos', '_tos_nonce', false ); ?>
	<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( wp_unslash( $_REQUEST['redirect_to'] ) ); ?>" />
	<p class="login-submit">
		<input type="submit" class="button-primary" value="<?php esc_attr_eu( 'I agree', 'wporg' ); ?>">
	</p>
</form>

<p class="center">
	<a href="<?php echo wporg_login_wordpress_url(); ?>" style="font-style: italic;"><?php _eu( 'I do not agree', 'wporg' ); ?></a>
</p>

<p id="nav">
	<a href="/"><?php _e( '&larr; Back to login', 'wporg' ); ?></a> &nbsp; • &nbsp;
	<a href="<?php echo wporg_login_wordpress_url(); ?>"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>
<?php

// Just in case.. this also prevents certain debug plugins outputting in the footer.
wp_set_current_user( 0 );

// This is just for during development until strings are finalised.
function _eu( $s ) {
	echo $s;
}
function __u( $s ) {
	return $s;
}
function esc_attr_eu( $s ) {
	echo esc_attr( __u( $s ) );
}
