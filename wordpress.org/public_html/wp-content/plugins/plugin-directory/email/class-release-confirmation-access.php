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
			/* translators: 1: Username; 1: Access URL */
			__( 'Howdy %1$s,

To manage your plugin releases, follow the link below:
%2$s', 'wporg-plugins' ),
			$this->user_text( $this->user ),
			esc_url( Release_Confirmation_Shortcode::generate_access_url( $this->user ) )
		);
	}
}
