<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;

class Support_Rep_Added_Notification extends Base {
	protected $required_args = [ 'rep' ];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( 'New support rep added to %s', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function body() {
		/* translators: 1: Receivers display name, 2: New Support Rep Name, 3: Plugin Name, 4: User who added rep, 5: List of reps, 6: Plugin Management URL, 7: Plugins Team email */
		$email_text = __( 'Howdy %1$s,

%2$s has been added as a support rep to %3$s by %4$s.

The following people are now suport representatives for %3$s:
%5$s

You can manage your plugin support representatives here:
%6$s

If you believe this to be in error, please contact %7$s.', 'wporg-plugins' );

		$rep_list = '';
		foreach ( Tools::get_plugin_support_reps( $this->plugin->post_name ) as $rep ) {
			$rep_list .= ' * ' . $this->user_text( $rep ) . "\n";
		}
		$rep_list = rtrim( $rep_list );

		$advanced_url = get_permalink( $this->plugin ) . 'advanced/';

		return sprintf(
			$email_text,
			$this->user_text( $this->user ),
			$this->user_text( $this->args['rep'] ),
			$this->plugin->post_title,
			$this->user_text( $this->who ),
			$rep_list,
			$advanced_url,
			PLUGIN_TEAM_EMAIL
		);
	}
}
