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
			/* translators: 1: Name, 2: Committer, 3: Plugin Name, 4: Version identifier, 5: Access URL */
			__( 'Howdy %1$s,

%2$s has committed a new version of %3$s - %4$s.

An email confirmation is required before the new version will be released.

Follow the link below to login and confirm the release.

<%5$s>', 'wporg-plugins' ),
			$this->user_text( $this->user ),
			$this->user_text( $this->args['who'] ),
			$this->args['readme']->name,
			$this->args['headers']->Version,
			esc_url( Release_Confirmation_Shortcode::generate_access_url( $this->user ) )
		);
	}
}
