<?php wp_login_form(); ?>

<p class="nav">
	<a href="<?php echo wp_lostpassword_url( '/' ); ?>" title="<?php _e( 'Password Lost and Found', 'wporg-login' ); ?>"><?php _e( 'Forgot password?', 'wporg-login' ); ?></a> &nbsp; • &nbsp; 
	<a href="https://wordpress.org/support/register.php" title="<?php _e( 'Create an account', 'wporg-login' ); ?>"><?php _e( 'Create an account', 'wporg-login' ); ?></a>
</p>