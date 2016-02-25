<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The Plugin Committers admin metabox.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Committers {
	static function display() {
		$plugin_slug = get_post()->post_name;
		$existing_committers = Tools::get_plugin_committers( $plugin_slug );
		$existing_committers = array_map( function( $user ) { return new \WP_User( $user ); }, $existing_committers );

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
