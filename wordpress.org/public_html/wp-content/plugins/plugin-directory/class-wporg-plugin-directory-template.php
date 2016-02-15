<?php
/**
 * @package WPorg_Plugin_Directory
 */

/**
 * Various helpers to retrieve data not stored within WordPress.
 */
class WPorg_Plugin_Directory_Template {

	/**
	 * @param string $plugin_slug
	 * @return int
	 */
	static function get_active_installs_count( $plugin_slug ) {
		if ( false === ( $count = wp_cache_get( $plugin_slug, 'plugin_active_installs' ) ) ) {
			global $wpdb;

			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT count FROM rev2_daily_stat_summary WHERE type = 'plugin' AND type_name = %s AND stat = 'active_installs' LIMIT 1",
				$plugin_slug
			) );
			wp_cache_add( $plugin_slug, $count, 'plugin_active_installs', 1200 );
		}

		if ( $count < 10 ) {
			return 0;
		}

		if ( $count >= 1000000 ) {
			return 1000000;
		}

		return strval( $count )[0] * pow( 10, floor( log10( $count ) ) );
	}

	/**
	 * @return int
	 */
	static function get_total_downloads() {
		if ( false === ( $count = wp_cache_get( 'plugin_download_count', 'plugin_download_count' ) ) ) {
			global $wpdb;

			$count = $wpdb->get_var( "SELECT SUM(downloads) FROM `plugin_2_stats`" );
			wp_cache_add( 'plugin_download_count', $count, 'plugin_download_count', DAY_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * @return array
	 */
	static function get_plugin_sections() {
		$plugin       = get_post();
		$plugin_slug  = $plugin->post_name;
		$raw_sections = get_post_meta( $plugin->ID, 'sections', true );
		$raw_sections = array_unique( array_merge( $raw_sections, array(
			'description',
			'stats',
			'support',
			'reviews',
			'developers',
		) ) );

		$sections = array();
		$title    = '';
		$url      = get_permalink();

		foreach ( $raw_sections as $section_slug ) {
			switch ( $section_slug ) {

				case 'description':
					$title = _x( 'Description', 'plugin tab title', 'wporg-plugins' );
					break;

				case 'installation':
					$title = _x( 'Installation', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;

				case 'faq':
					$title = _x( 'FAQ', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;

				case 'screenshots':
					$title = _x( 'Screenshots', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;

				case 'changelog':
					$title = _x( 'Changelog', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;

				case 'stats':
					$title = _x( 'Stats', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;

				case 'support':
					$title = _x( 'Support', 'plugin tab title', 'wporg-plugins' );
					$url   = 'https://wordpress.org/support/plugin/' . $plugin_slug;
					break;

				case 'reviews':
					$title = _x( 'Reviews', 'plugin tab title', 'wporg-plugins' );
					$url   = 'https://wordpress.org/support/view/plugin-reviews/' . $plugin_slug;
					break;

				case 'developers':
					$title = _x( 'Developers', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;
			}

			$sections[] = array(
				'slug'  => $section_slug,
				'url'   => $url,
				'title' => $title,
			);
		}

		return $sections;
	}
}
