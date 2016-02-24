<?php

class WPorg_Plugin_Directory_Admin_Metabox_Committers {
	function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
	}

	function register() {
		add_meta_box( 'plugin-committers', __( 'Plugin Committers', 'wporg-plugins' ), array( $this, 'display' ), 'plugin' );
	}

	function display() {
		$plugin_slug = get_post()->post_name;
		$existing_committers = WPorg_Plugin_Directory_Tools::get_plugin_committers( $plugin_slug );
		$existing_committers = array_map( function( $user ) { return new WP_User( $user ); }, $existing_committers );

		$output = '';
		foreach ( $existing_committers as $committer ) {
			$output .= sprintf(
				'<li title="%s"><span class="avatar">%s</span> %s</li>',
				esc_attr( $committer->user_login ),
				get_avatar( $committer, '24' ),
				esc_html( $committer->display_name )
			);
		}
		echo '<ul class="committers">' . $output . '</ul>';
	}

}
new WPorg_Plugin_Directory_Admin_Metabox_Committers();
