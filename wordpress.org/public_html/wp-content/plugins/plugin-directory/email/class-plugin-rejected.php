<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;

class Plugin_Rejected extends Base {
	protected $required_args = [
		'slug',
		'submission_date'
	];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( '%s has been rejected', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function body() {
		/* translators: 1: plugin name, 2: plugin permalink, 3: date of submission, 4: plugins@wordpress.org */
		$email_text = __(
			'Unfortunately your plugin submission for %1$s (%2$s), submitted on %3$s, has been rejected from the WordPress Plugin Directory.

Plugins are rejected after six months when there has not been significant progress made on the review. If this is not the case for your plugin, you will receive a followup email explaining the reason for this decision within the next 24 hours. Please wait for that email before requesting further details.

If you believe this to be in error, please email %4$s with your plugin attached as a zip and explain why you feel your plugin should be accepted.',
			'wporg-plugins'
		);

		return sprintf(
			$email_text,
			$this->plugin->post_title,
			$this->args['slug'],
			$this->args['submission_date'],
			PLUGIN_TEAM_EMAIL
		);
	}
}
