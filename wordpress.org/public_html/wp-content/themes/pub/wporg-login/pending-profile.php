<?php
/**
 * The post-pending-email-confirm profile-fields Template
 *
 * @package wporg-login
 */

$profile_user = WP_WPOrg_SSO::$matched_route_params['profile_user'] ?? false;
$profile_key  = WP_WPOrg_SSO::$matched_route_params['profile_key']  ?? false;

$pending_user = wporg_get_pending_user( $profile_user );

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
	wp_safe_redirect( 'https://wordpress.org/support/' );
	die();
} elseif ( ! $can_access ) {
	wp_safe_redirect( '/' );
	die();
}

if ( wporg_login_save_profile_fields( $pending_user ) ) {
	// re-fetch the user, it's probably changed.
	$pending_user = wporg_get_pending_user( $profile_user );
}
wp_enqueue_script( 'wporg-registration' );

get_header();
?>
<div class="message info">
	<p><?php
		printf(
			/* translators: %s Email address */
			__( 'Please check your email %s for a confirmation link to set your password.', 'wporg' ),
			'<code>' . esc_html( wp_get_current_user()->user_email ) . '</code>'
		);
	?></p>
</div>

<p class="intro">
<?php _e( 'Complete your WordPress.org Profile information.', 'wporg' ); ?>
</p>

<form name="registerform" id="registerform" action="" method="post">

	<?php
		$fields = &$pending_user['meta'];
		include __DIR__ . '/partials/register-profilefields.php';
	?>

	<p class="login-submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Save Profile Information', 'wporg' ); ?>" />
	</p>

</form>

<p id="nav">
	<a href="https://wordpress.org/"><?php _e( 'WordPress.org', 'wporg' ); ?></a>
</p>

<?php get_footer(); ?>
