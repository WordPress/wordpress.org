<?php
/**
 * The login form Template
 *
 * @package wporg-login
 */

get_header();

$from = $_GET['from'] ?? 'wordpress.org';
?>
<p class="intro"><?php
if ( false !== stripos( $from, 'buddypress.org' ) ) {
	echo '<strong>' . __( 'BuddyPress is part of WordPress.org', 'wporg' ) . '</strong><br>';
	_e( 'Log in to your WordPress.org account to contribute to BuddyPress, or get help in the support forums.', 'wporg' );

} elseif ( false !== stripos( $from, 'bbpress.org' ) ) {
	echo '<strong>' . __( 'bbPress is part of WordPress.org', 'wporg' ) . '</strong><br>';
	_e( 'Log in to your WordPress.org account to contribute to bbPress, or get help in the support forums.', 'wporg' );

} elseif ( false !== stripos( $from, 'wordcamp.org' ) ) {
	echo '<strong>' . __( 'WordCamp is part of WordPress.org', 'wporg' ) . '</strong><br>';
	_e( 'Log in to your WordPress.org account to contribute to WordCamps and meetups around the globe.', 'wporg' );

} else {
	_e( 'Log in to your WordPress.org account to contribute to WordPress, get help in the support forum, or rate and review themes and plugins.', 'wporg' );
}
?>
</p>

<?php wp_login_form(); ?>

<p id="nav">
	<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" title="<?php _e( 'Password Lost and Found', 'wporg' ); ?>"><?php _e( 'Lost password?', 'wporg' ); ?></a> &nbsp; • &nbsp;
	<a href="<?php echo esc_url( wp_registration_url() ); ?>" title="<?php _e( 'Create an account', 'wporg' ); ?>"><?php _e( 'Create an account', 'wporg' ); ?></a>
</p>

<script type="text/javascript">
setTimeout( function() {
	try {
		d = document.getElementById( 'user_login' );
		d.focus();
		d.select();
	} catch( e ){}
}, 200 );
</script>


<?php get_footer(); ?>
