<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;

class Release_Confirmation_Enabled extends Base {
	protected $required_args = [];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( 'Release confirmation now required for %s', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function body() {
		/* translators: 1: Plugin Author, 2: Plugin Name, 3: URL to the handbook */
		return sprintf(
			__( 'Howdy %1$s,

Release confirmations are now enabled for %2$s.

This means that each time you release a new version of %2$s you\'ll be required to confirm the release by following a link in an automated email.

For more information, please read the following handbook article:
%3$s', 'wporg-plugins' ),
			$this->user_text( $this->user ),
			$this->plugin->post_title,
			'https://developer.wordpress.org/plugins/wordpress-org/' // TODO: Handbook page.
		);
	}
}
