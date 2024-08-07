<?php

/*
 * This contains the Account section from the default `form-user-edit.php` on a separate screen. It's populated
 * dynamically from the WPORG Two Factor plugin.
 */

?>

<div id="bbp-your-profile">
	<h2 class="entry-title">
		<?php esc_html_e( 'Account', 'bbpress' ); ?>
	</h2>

	<?php do_action( 'bbp_user_edit_account' ); ?>
</div>
