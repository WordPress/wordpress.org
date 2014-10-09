<?php
/* Plugin Name: Rosetta Downloads
 * Author: Nacin
 */

class Rosetta_Downloads {

	function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function admin_menu() {
		add_dashboard_page( 'Download Stats', 'Download Stats', 'read', 'download-stats', array( $this, 'render_download_stats' ) );
	}

	function get_counts() {
		global $wpdb;
		$orderby = isset( $_GET['desc'] ) ? 'SUM(`downloads`) DESC' : '`locale`';
		$stable_branch = implode( '.', array_map( 'absint', explode( '.', WP_CORE_STABLE_BRANCH ) ) );
		return $wpdb->get_results( "SELECT `locale`, SUM(`downloads`) FROM `download_counts` WHERE `release` LIKE '{$stable_branch}%' AND `release` NOT LIKE '%-%' GROUP BY `locale` ORDER BY $orderby", ARRAY_N );
	}

	function render_download_stats() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( 'Download Stats', 'rosetta' ) . '</h2>';
		echo '<table cellpadding=3 cellspacing=2>';

		echo '<p>' . sprintf( __( 'This page shows the <a href="%s">Download Counter</a> number &mdash; total downloads of WordPress %s &mdash; broken down by locale.', 'rosetta' ), 'https://wordpress.org/download/counter/', esc_html( WP_CORE_STABLE_BRANCH ) ) . '</p>';

		$rows = array();
		$total = 0;
		$this_locale = get_locale();
		foreach ( $this->get_counts() as $value ) {
			list( $locale, $sum ) = $value;
			$total += $sum;
			$sum = number_format_i18n( $sum );
			$highlight = $locale == $this_locale ? 'background:#ffffe0;border:1px solid #e6db55;font-weight:bold;' : '';
			$row = "<tr><td>$locale</td><td style='{$highlight}text-align:right'>$sum</td></tr>";
			if ( $locale == 'en' ) {
				$rows = array_merge( array( $row ), $rows );
			} else {
				$rows[] = $row;
			}
		}
		echo "<tr><td style='width:100px'><strong>" . _x( 'All', 'locales', 'rosetta' ) . "</strong></td><td style='text-align:right'><strong>" . number_format_i18n( $total ) . "</strong></td></tr>";
		echo implode( "\n", $rows );
		echo '</table>';
	}


}
new Rosetta_Downloads;
