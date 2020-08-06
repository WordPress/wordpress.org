<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;

class Committer_Added_Notification extends Base {
	protected $required_args = [ 'committer' ];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( 'New committer added to %s', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function body() {
		/* translators: 1: Receivers display name, 2: New Committer Name, 3: Plugin Name, 4: User who added committer, 5: List of users, 6: Plugin Management URL, 7: Plugins Team email */
		$email_text = __( 'Howdy %1$s,

%2$s has been added as a committer to %3$s by %4$s.

The following people now have write-access to %3$s:
%5$s

You can manage your plugin committers here:
%6$s

If you believe this to be in error, please contact %7$s.', 'wporg-plugins' );

		$committer_list = '';
		foreach ( Tools::get_plugin_committers( $this->plugin->post_name ) as $c ) {
			$committer_list .= ' * ' . $this->user_text( $c ) . "\n";
		}
		$committer_list = rtrim( $committer_list );

		$advanced_url = get_permalink( $this->plugin ) . 'advanced/';

		return sprintf(
			$email_text,
			$this->user_text( $this->user ),
			$this->user_text( $this->args['committer'] ),
			$this->plugin->post_title,
			$this->user_text( $this->who ),
			$committer_list,
			$advanced_url,
			PLUGIN_TEAM_EMAIL
		);
	}
}
