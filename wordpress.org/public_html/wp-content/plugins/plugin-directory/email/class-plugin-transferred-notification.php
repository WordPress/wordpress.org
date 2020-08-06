<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;

class Plugin_Transferred_Notification extends Base {
	protected $required_args = [ 'owner' ];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( '%s has been transferred', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function body() {
		/* translators: 1: Author name 2: Date, 3: Plugin Name, 4: New Owners name, 5: Plugin team email address. */
		$email_text = __( 'As requested by %1$s on %2$s, the ownership of %3$s in the WordPress Plugin Directory has been transferred to %4$s.

If you believe this to be in error, please contact %5$s immediately.', 'wporg-plugins' );

		return sprintf(
			$email_text,
			$this->user_text( wp_get_current_user() ),
			gmdate( 'Y-m-d H:i:s \G\M\T' ),
			$this->plugin->post_title,
			$this->user_text( $this->args['owner'] ) . ' (' . $this->args['owner']->user_login . ')',
			PLUGIN_TEAM_EMAIL
		);
	}
}
