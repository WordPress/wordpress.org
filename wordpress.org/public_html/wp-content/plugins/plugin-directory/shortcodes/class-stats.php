<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory;

/**
 * The [wporg-plugins-stats] shortcode handler to display a plugins statistics.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Stats {

	/**
	 *
	 * @return string
	 */
	static function display() {
		$post = get_post();

		wp_enqueue_script( 'google-jsapi', 'https://www.google.com/jsapi', array(), false, true );
		wp_enqueue_script( 'wporg-plugins-stats', plugins_url( 'js/stats.js', Plugin_Directory\PLUGIN_FILE ) , array( 'jquery', 'google-jsapi' ), time(), true );
		wp_localize_script( 'wporg-plugins-stats', 'pluginStats', array(
			'slug' => $post->post_name,
			'l10n' => array(
				'date'          => __( 'Date', 'wporg-plugins' ),
				'downloads'     => __( 'Downloads', 'wporg-plugins' ),
				'noData'        => __( 'No data yet', 'wporg-plugins' ),
				'otherVersions' => __( 'Other Versions', 'wporg-plugins' ),
			),
		) );

		return '<h5>' . __( 'Active versions', 'wporg-plugins' ) . '</h5>' .
			'<div id="plugin-version-stats" class="chart"></div>';
	}
}
