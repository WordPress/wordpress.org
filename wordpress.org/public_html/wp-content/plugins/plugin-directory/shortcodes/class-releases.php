<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * The [wporg-plugins-releases] shortcode handler to display developer information.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Releases {

	/**
	 * @return string
	 */
	static function display() {
		$plugin = Plugin_Directory::get_plugin_post();
		$releases = Plugin_Directory::get_releases( $plugin );

		$content = '<ul class="plugin-releases">';
		if ( ! $releases ) {
			$content .= '<li>' . __( 'No releases.', 'wporg-plugins' ) . '</li>';
		}

		foreach ( $releases as $data ) {
			if ( ! is_array( $data['committer'] ) ) {
				$data['committer'] = (array) $data['committer'];
			}
			foreach ( $data['committer'] as $i => $login ) {
				$data['committer'][ $i ] = sprintf(
					'<a href="%s">%s</a>',
					'https://profiles.wordpress.org/' . get_user_by( 'login', $login )->user_nicename . '/',
					esc_html( $login )
				);
			}

			$tag_url = esc_url( sprintf(
				'https://plugins.trac.wordpress.org/browser/%s/tags/%s/',
				$plugin->post_name,
				$data['tag']
			) );

			$tag_log_url = esc_url( sprintf(
				'https://plugins.trac.wordpress.org/log/%s/tags/%s/',
				$plugin->post_name,
				$data['tag']
			) );

			$content .= '<li class="plugin-releases-item">';
			$content .= '<div class="plugin-releases-item-header">';
			$content .= '<div>';
			$content .= sprintf(
					'<h3><a href="%s">%s</a></h3>',
					$tag_url,
					esc_html( $data['version'] )
			);

			if ( isset( $data['revision'][0] ) ) {
				$content .= sprintf( '<span>Revision <a href="%s">%s</a></span>', 
					$tag_log_url, 
					esc_attr( $data['revision'][0] ) 
				);
			}
			$content .= '</div>';

			$content .= sprintf(
				'<span>Released on %s</span>',
				esc_attr( gmdate( 'M d, Y', $data['date'] ) ),
			);

			$content .= sprintf( 
				'<div class="wp-block-button is-small"><a class="wp-block-button__link" href="%s">Download</a></div>',
				esc_attr( Template::download_link( $plugin, $data['version'] ) )
			);
			$content .= '</div>';
			$content .= '</li>';

		}

		$content .= '</ul>';

		return $content;
	}
}
