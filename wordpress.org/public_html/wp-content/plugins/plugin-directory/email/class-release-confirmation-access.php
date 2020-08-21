<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Shortcodes\Release_Confirmation as Release_Confirmation_Shortcode;

class Release_Confirmation_Access extends Base {
	protected $required_args = [];

	function subject() {
		return sprintf(
			/* translators: 1: User Login */
			__( 'Release Management for %s', 'wporg-plugins' ),
			$this->user->user_login
		);
	}

	function body() {
		return sprintf(
			/* translators: 1: Access URL */
			__( 'To manage your plugin releases, follow the link below:

<%1$s>', 'wporg-plugins' ),
			esc_url( Release_Confirmation_Shortcode::generate_access_url( $this->user ) )
		);
	}
}
