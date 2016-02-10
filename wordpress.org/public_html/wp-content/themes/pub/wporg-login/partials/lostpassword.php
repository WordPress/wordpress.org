

<form name="lostpasswordform" id="lostpasswordform" action="/wp-login.php?action=lostpassword" method="post">
	<p class="intro"><?php _e( 'Please enter your username or email address. You will receive a link to create a new password via email.', 'wporg-login' ); ?></p>
	<p>
		<label for="user_login"><?php _e( 'Username or Email', 'wporg-login' ); ?>
		<input type="text" name="user_login" id="user_login" value="" size="20"></label>
	</p>
	<input type="hidden" name="redirect_to" value="/checkemail/">
	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="<?php esc_attr_e( 'Get new password', 'wporg-login' ); ?>">
	</p>
</form>
<p id="nav">
	<a href="/"><?php _e( '&larr; Back to login', 'wporg-login' ); ?></a>
</p>