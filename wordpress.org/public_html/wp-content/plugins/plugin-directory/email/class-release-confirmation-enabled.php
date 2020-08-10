<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;

class Release_Confirmation_Enabled extends Base {
	protected $required_args = [];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( 'Release confirmations now required for %s', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function body() {
		/* translators: 1: plugin name, 2: plugin author's username, 3: plugin slug */
		$email_text = "TODO Describe what release confirmations are, etc..";

		return $email_text . print_r( $this->args, true );

	/*	return sprintf(
			$email_text,
			$this->plugin->post_title,
			$this->user->user_login,
			$this->plugin->post_name
		); */
	}
}
