<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Shortcodes\Release_Confirmation as Release_Confirmation_Shortcode;

class Release_Confirmation extends Base {
	protected $required_args = [
		'who',
		'readme',
		'headers'
	];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( 'Pending release for %s', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function body() {
		return sprintf(
			/* translators: 1: Username, 2: Plugin Name, 3: Version identifier, 4: Access URL */
			__( '%1$s has committed a new version of %2$s - %3$s.

An email confirmation is required before the new version will be released.

Follow the link below to login and confirm the release.

<%4$s>', 'wporg-plugins' ),
			$this->args['who'],
			$this->args['readme']->name,
			$this->args['headers']->Version,
			esc_url( Release_Confirmation_Shortcode::generate_access_url( $this->user ) )
		);
	}
}
