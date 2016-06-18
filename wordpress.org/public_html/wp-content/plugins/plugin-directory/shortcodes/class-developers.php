<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The [wporg-plugins-developers] shortcode handler to display developer information.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Developers {

	/**
	 * @return string
	 */
	static function display() {
		$post         = get_post();
		$committers   = Tools::get_plugin_committers( $post->post_name );
		$contributors = get_post_meta( $post->ID, 'contributors', true );
		$contributors = array_unique( array_merge( $committers, $contributors ) );
		sort( $contributors, SORT_NATURAL );

		$output = '<ul class="plugin-developers">';
		foreach ( $contributors as $contributor_slug ) {
			$contributor = get_user_by( 'login', $contributor_slug );
			if ( ! $contributor ) {
				continue;
			}

			$output .= '<li>' . get_avatar( $contributor->ID, 32 ) . ' <a href="' . esc_url( 'https://profiles.wordpress.org/' . $contributor_slug ) . '">' . Template::encode( $contributor->display_name ) . '</a></li>';
		}
		$output .= '</ul>';

		return $output .
			'<h5>' . __( 'Browse the code', 'wporg-plugins' ) . '</h5>' .
			'<ul>' .
				'<li>' .
					'<a href="' . esc_url( "https://plugins.trac.wordpress.org/log/{$post->post_name}/" ) . '" rel="nofollow">' . __( 'Development Log', 'wporg-plugins' ) . '</a>' . "\n" .
					'<a href="' . esc_url( "https://plugins.trac.wordpress.org/log/{$post->post_name}/?limit=100&mode=stop_on_copy&format=rss" ) . '" rel="nofollow"><img src="//s.w.org/style/images/feedicon.png" /></a>' .
				'</li>' .
				'<li><a href="' . esc_url( "https://plugins.svn.wordpress.org/{$post->post_name}/" ) . '" rel="nofollow">' . __( 'Subversion Repository', 'wporg-plugins' ) . '</a></li>' .
				'<li><a href="' . esc_url( "https://plugins.trac.wordpress.org/browser/{$post->post_name}/" ) . '" rel="nofollow">' . __( 'Browse in Trac', 'wporg-plugins' ) . '</a></li>' .
				'<li><a href="' . esc_url( "https://translate.wordpress.org/projects/wp-plugins/{$post->post_name}/" ) . '" rel="nofollow">' . __( 'Translation Contributors', 'wporg-plugins' ) . '</a></li>' .
			'</ul>';
	}
}
