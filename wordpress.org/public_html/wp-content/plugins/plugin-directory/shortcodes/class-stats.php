<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * The [wporg-plugins-stats] shortcode handler to display a plugins statistics.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Stats {

	/**
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return string
	 */
	static function display() {
		global $wpdb;

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

		$today     = $wpdb->get_var( $wpdb->prepare( "SELECT downloads FROM " . PLUGINS_TABLE_PREFIX . "stats WHERE stamp >= %s AND plugin_slug = %s", gmdate( 'Y-m-d' ), $post->post_name ) );
		$yesterday = $wpdb->get_var( $wpdb->prepare( "SELECT downloads FROM " . PLUGINS_TABLE_PREFIX . "stats WHERE stamp >= %s AND stamp < %s AND plugin_slug = %s",
			gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ), gmdate( 'Y-m-d' ), $post->post_name
		) );
		$last_week = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(downloads) FROM " . PLUGINS_TABLE_PREFIX . "stats WHERE stamp >= %s AND plugin_slug = %s",
			gmdate( 'Y-m-d', time() - WEEK_IN_SECONDS ), $post->post_name
		) );

		return
			'<h5>' . __( 'Active versions', 'wporg-plugins' ) . '</h5>' .
			'<div id="plugin-version-stats" class="chart"></div>' .

			'<h5>' . __( 'Downloads Per Day', 'wporg-plugins' ) . '</h5>' .
			'<div id="plugin-download-stats" class="chart"></div>' .

			'<h5>' . __( 'Downloads history', 'wporg-plugins' ) . '</h5>' .
			'<table>' .
				'<tr>' .
					'<th scope="row">' . __( 'Today', 'wporg-plugins' ) . '</th>' .
					'<td>' . number_format_i18n( $today ) . '</td>' .
				'</tr>' .
				'<tr>' .
					'<th scope="row">' . __( 'Yesterday', 'wporg-plugins' ) . '</th>' .
					'<td>' . number_format_i18n( $yesterday ) . '</td>' .
				'</tr>' .
				'<tr>' .
					'<th scope="row">' . __( 'Last Week', 'wporg-plugins' ) . '</th>'.
					'<td>' . number_format_i18n( $last_week ) . '</td>'.
				'</tr>' .
				'<tr>' .
					'<th scope="row">' . __( 'All Time', 'wporg-plugins' ) . '</th>' .
					'<td>' . number_format_i18n( Template::get_downloads_count( $post ) ) . '</td>' .
				'</tr>'.
			'</table>';
	}
}
