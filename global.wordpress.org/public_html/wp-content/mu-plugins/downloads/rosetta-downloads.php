<?php
/**
 * Plugin Name: Rosetta Downloads
 * Plugin URI: https://wordpress.org/
 * Description: Dashboard page for download stats.
 * Author: Nacin, Dominik Schilling
 * Version: 1.0
 */

class Rosetta_Downloads {

	/**
	 * Class Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Attaches hooks once plugins are loaded.
	 */
	public function plugins_loaded() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Registers "Download Stats" page.
	 */
	public function admin_menu() {
		add_dashboard_page(
			__( 'Download Stats', 'rosetta' ),
			__( 'Download Stats', 'rosetta' ),
			'read',
			'download-stats',
			array( $this, 'render_download_stats' )
		);
	}

	/**
	 * Returns download counts for release packages of the current stable branch.
	 *
	 * @return array Download counts per locale.
	 */
	function get_release_download_counts() {
		global $wpdb;

		$cache = wp_cache_get( 'downloadcounts:release', 'rosetta' );
		if ( false !== $cache ) {
			return $cache;
		}

		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT
				`locale`,
				SUM(`downloads`) AS downloads
			FROM `download_counts`
			WHERE
				`release` LIKE %s AND
				`release` NOT LIKE '%%-%%'
			GROUP BY `locale`
		", WP_CORE_STABLE_BRANCH . '%' ) );

		if ( ! $results ) {
			return array();
		}

		$counts = wp_list_pluck( $results, 'downloads', 'locale' );

		wp_cache_add( 'downloadcounts:release', $counts, 'rosetta', 60 );

		return $counts;
	}

	/**
	 * Returns download counts for language packs of the current stable branch.
	 *
	 * @return array Download counts per locale.
	 */
	function get_translation_download_counts() {
		global $wpdb;

		$cache = wp_cache_get( 'downloadcounts:translation', 'rosetta' );
		if ( false !== $cache ) {
			return $cache;
		}

		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT `locale`, SUM(`downloads`) AS downloads
			FROM `translation_download_counts`
			WHERE
				`version` LIKE %s AND
				`type` = 'core' AND
				`domain` = 'default'
			GROUP BY `locale`
		", WP_CORE_STABLE_BRANCH . '%' ) );

		if ( ! $results ) {
			return array();
		}

		$counts = wp_list_pluck( $results, 'downloads', 'locale' );

		wp_cache_add( 'downloadcounts:translation', $counts, 'rosetta', 60 );

		return $counts;
	}

	/**
	 * Renders the "Download Stats" page.
	 */
	function render_download_stats() {
		$total_release_counts = $total_translation_counts = 0;
		$this_locale = get_locale();
		$release_counts = $this->get_release_download_counts();
		$translation_counts = $this->get_translation_download_counts();
		$locales = array_unique( array_merge( array_keys( $release_counts ), array_keys( $translation_counts ) ) );
		$rows = array();

		foreach ( $locales as $locale ) {
			$release_count = isset( $release_counts[ $locale ] ) ? $release_counts[ $locale ] : 0;
			$translation_count = isset( $translation_counts[ $locale ] ) ? $translation_counts[ $locale ] : 0;
			$total_release_counts += $release_count;
			$total_translation_counts += $translation_count;

			$highlight = ( $locale == $this_locale ) ? ' style="background:#ffffe0;font-weight:bold"' : '';
			$row = sprintf(
				'<tr%s><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td></tr>',
				$highlight,
				$locale,
				$release_count ? number_format_i18n( $release_count ) : '&mdash;',
				$translation_count ? number_format_i18n( $translation_count ) : '&mdash;'
			);

			if ( $locale == 'en' ) {
				$rows = array_merge( array( $row ), $rows );
			} else {
				$rows[] = $row;
			}
		}
		?>
		<div class="wrap">
			<h2><?php _e( 'Download Stats', 'rosetta' ); ?></h2>
			<p>
				<?php
				printf(
					__( 'This page shows the <a href="%s">Download Counter</a> number &mdash; total downloads of WordPress %s &mdash; broken down by locale.', 'rosetta' ),
					'https://wordpress.org/download/counter/',
					esc_html( WP_CORE_STABLE_BRANCH )
				);
				?>
			</p>

			<table class="widefat fixed striped" style="max-width:400px">
				<thead>
					<tr>
						<th scope="col" style="width:80px"><?php _e( 'Locale', 'rosetta' ); ?></th>
						<th scope="col" style="text-align:right"><?php _e( 'Release Package', 'rosetta' ); ?></th>
						<th scope="col" style="text-align:right"><?php _e( 'Language Pack', 'rosetta' ); ?> <abbr title="Since March 11, 2015.">*</abbr></th>
					</tr>
				</thead>

				<tbody>
					<tr>
						<td>
							<strong><?php _ex( 'All', 'locales', 'rosetta' ); ?></strong>
						</td>
						<td style="text-align:right">
							<strong><?php echo number_format_i18n( $total_release_counts ); ?></strong>
						</td>
						<td style="text-align:right">
							<strong><?php echo number_format_i18n( $total_translation_counts ); ?></strong>
						</td>
					</tr>
					<?php echo implode( "\n", $rows ); ?>
				</tbody>

				<tfoot>
					<tr>
						<th scope="col" style="width:80px"><?php _e( 'Locale', 'rosetta' ); ?></th>
						<th scope="col" style="text-align:right"><?php _e( 'Release Package', 'rosetta' ); ?></th>
						<th scope="col" style="text-align:right"><?php _e( 'Language Pack', 'rosetta' ); ?> <abbr title="Since March 11, 2015.">*</abbr></th>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php
	}
}

new Rosetta_Downloads;
