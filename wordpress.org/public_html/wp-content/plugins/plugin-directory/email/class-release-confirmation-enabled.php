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
		/* translators: 1: Plugin Author, 2: User Name, 5: Plugin Name, 4: Plugin URL, 5: Plugin slug, 6: URL to the handbook */
		return sprintf(
			__( 'Howdy %1$s,

%2$s has enabled release confirmations for the following plugin:
%3$s
%4$s

A new email will be sent to all committers when a new pending release exists for %5$s with a link to the Release Management dashboard.

You, or another committer to the plugin, will be required to confirm the release on that dashboard before WordPress.org processes the newly committed plugin update.

For more information, please read the following handbook article:
%6$s', 'wporg-plugins' ),
			$this->user_text( $this->user ),
			$this->user_text( $this->who ),
			$this->plugin->post_title,
			get_permalink( $this->plugin ),
			$this->plugin->post_name,
			'https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/'
		);
	}
}
