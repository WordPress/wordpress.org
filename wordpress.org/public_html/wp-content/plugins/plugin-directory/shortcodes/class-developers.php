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
		$output = '';

		$contributors = get_terms( array(
			'taxonomy' => 'plugin_contributors',
			'object_ids' => array( $post->ID ),
			'orderby' => 'term_order',
			'fields' => 'names',
		) );

		if ( ! $contributors || is_wp_error( $contributors ) ) {
			$contributors = array();
			if ( $author = get_user_by( 'id', $post->post_author ) ) {
				$contributors[] = $author->user_nicename;
			}
		}

		$output .= '<p>' . __( 'This is open source software. The following people have contributed to this plugin.', 'wporg-plugins' ) . '</p>';

		$output .= '<ul class="plugin-developers">';
		foreach ( $contributors as $contributor_slug ) {
			$contributor = get_user_by( 'slug', $contributor_slug );
			if ( ! $contributor ) {
				continue;
			}

			$output .= '<li>' . get_avatar( $contributor->ID, 32 ) . '<a href="' . esc_url( 'https://profiles.wordpress.org/' . $contributor_slug ) . '">' . Template::encode( $contributor->display_name ) . '</a></li>';
		}
		$output .= '</ul>';

		if ( is_user_logged_in() ) {
			$subscribed = Tools::subscribed_to_plugin_commits( $post, get_current_user_id() );

			$subscribe_change_url = esc_url( add_query_arg(
				array(
					'_wpnonce' => wp_create_nonce( 'wp_rest' ),
					( $subscribed ? 'unsubscribe' : 'subscribe' ) => '1'
				),
				home_url( 'wp-json/plugins/v1/plugin/' . $post->post_name . '/commit-subscription' )
			) );
		}

		return $output .
			'<h3>' . __( 'Browse the code', 'wporg-plugins' ) . '</h3>' .
			'<ul>' .
				'<li>' .
					'<a href="' . esc_url( "https://plugins.trac.wordpress.org/log/{$post->post_name}/" ) . '" rel="nofollow">' . __( 'Development Log', 'wporg-plugins' ) . '</a>' . "\n" .
					'<a href="' . esc_url( "https://plugins.trac.wordpress.org/log/{$post->post_name}/?limit=100&mode=stop_on_copy&format=rss" ) . '" rel="nofollow"><img src="https://s.w.org/style/images/feedicon.png" /></a>' .
				'</li>' .
				'<li><a href="' . esc_url( "https://plugins.svn.wordpress.org/{$post->post_name}/" ) . '" rel="nofollow">' . __( 'Subversion Repository', 'wporg-plugins' ) . '</a></li>' .
				'<li><a href="' . esc_url( "https://plugins.trac.wordpress.org/browser/{$post->post_name}/" ) . '" rel="nofollow">' . __( 'Browse in Trac', 'wporg-plugins' ) . '</a></li>' .
				'<li><a href="' . esc_url( "https://translate.wordpress.org/projects/wp-plugins/{$post->post_name}/" ) . '" rel="nofollow">' . __( 'Translation Contributors', 'wporg-plugins' ) . '</a></li>' .
				 ( !is_user_logged_in() ? '' : (
				 	$subscribed ?
				 	"<li><a href='$subscribe_change_url'>" . __( 'Unsubscribe from plugin commits', 'wporg-plugins' ) . '</a></li>' :
				 	"<li><a href='$subscribe_change_url'>" . __( 'Subscribe to plugin commits via email', 'wporg-plugins' ) . '</a></li>'
				 ) ) .
			'</ul>';
	}
}
