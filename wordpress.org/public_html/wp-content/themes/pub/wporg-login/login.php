<?php
/**
 * The login form Template
 *
 * @package wporg-login
 */

get_header();
?>

<?php
wp_login_form( [
	// pre-fill the last user if their session has simply timed out.
	'value_username' => wp_parse_auth_cookie()['username'] ?? ''
] );
?>

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
	} catch( e ){}
}, 200 );
</script>


<?php get_footer(); ?>
