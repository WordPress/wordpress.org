<?php
/**
 * The login form Template
 *
 * @package wporg-login
 */

get_header();

// Prefill the username if possible.
$username = $_REQUEST['user'] ?? ( wp_parse_auth_cookie()['username'] ?? '' );
if ( ! is_string( $username ) ) {
	$username = '';
}

// Redirect is validated at redirect time, just pass through whatever we can.
if ( ! empty( $_REQUEST['redirect_to'] ) ) {
	$redirect = wp_unslash( $_REQUEST['redirect_to'] );
} elseif ( $referer = wp_get_referer() ) {
	$redirect = $referer;
} else {
	$redirect = 'https://profiles.wordpress.org/';
}
?>

<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
	<p class="intro"><?php echo wporg_login_wporg_is_starpress(); ?></p>
	<p class="login-username">
		<label for="user_login"><?php _e( 'Username or Email Address', 'wporg-login' ); ?></label>
		<input type="text" name="log" id="user_login" class="input" value="<?php echo esc_attr( $username ); ?>" size="20" />
	</p>
	<p class="login-password">
		<label for="user_pass"><?php _e( 'Password', 'wporg-login' ); ?></label>
		<span class="wp-pwd" style="display:block;">
			<input type="password" name="pwd" id="user_pass" class="input password-input" value="" size="20" />
			<button type="button" id="wp-hide-pw" class="button button-secondary wp-hide-pw hide-if-no-js" aria-label="<?php esc_attr_e( 'Show password', 'wporg-login' ); ?>">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
			</button>
		</span>
	</p>
	<p class="login-remember"><label><input name="rememberme" type="checkbox" id="rememberme" value="forever" /> <?php _e( 'Remember Me', 'wporg-login' ); ?></label></p>
	<p class="login-submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Log In', 'wporg-login' ); ?>" />
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />
	</p>
</form>

<p id="nav">
	<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" title="<?php _e( 'Password Lost and Found', 'wporg' ); ?>"><?php _e( 'Lost password?', 'wporg' ); ?></a> &nbsp; • &nbsp;
	<a href="<?php echo esc_url( wp_registration_url() ); ?>" title="<?php _e( 'Create an account', 'wporg' ); ?>"><?php _e( 'Create an account', 'wporg' ); ?></a>
</p>

<script type="text/javascript">
setTimeout( function() {
	try {
		var d = document.getElementById( 'user_login' );
		if ( d.value ) {
			d = document.getElementById( 'user_pass' );
		}
		d.focus();
		d.select();

		var h = document.getElementById( 'wp-hide-pw' );
		h.onclick = function() {
			var p = document.getElementById( 'user_pass' );
			if ( p.type === 'password' ) {
				p.type = 'text';
				h.ariaLabel = <?php echo json_encode( __( 'Hide password', 'wporg-login' ) ); ?>;
				h.children[0].className = 'dashicons dashicons-hidden';
			} else {
				p.type = 'password';
				h.ariaLabel = <?php echo json_encode( __( 'Show password', 'wporg-login' ) ); ?>;
				h.children[0].className = 'dashicons dashicons-visibility';
			}
		}
	} catch( e ){}
}, 200 );
</script>


<?php get_footer(); ?>
