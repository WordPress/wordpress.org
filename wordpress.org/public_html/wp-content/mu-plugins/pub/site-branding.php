<?php
/**
 * Plugin Name: WordPress.org Site Branding
 * Description: Ensures that <title> tags on WordPress.org always include WordPress, and WordPress favicons.
 */

namespace WordPressdotorg\MU_Plugins\Site_Branding {

	/**
	 * Filter the document title parts to ensure that WordPress is replaced into it consistently.
	 */
	function document_title_parts( $parts ) {
		global $rosetta;

		// In some places on WordPress.org the document title is used within the theme, don't affect title calls after the </head>.
		if ( did_action( 'wp_body_open' ) ) {
			return $parts;
		}

		$combined = implode( ' ', $parts );

		// Ensure that 'WordPress' is present in the title of the URL
		if ( false === strpos( $combined, 'WordPress' ) ) {
			$parts['wporg-suffix'] = get_wordpress_brand();
		}

		// Override anything that set part of the title directly to WordPress.org on rosetta sites.
		if ( isset( $rosetta ) ) {
			foreach ( [ 'site', 'tagline' ] as $field ) {
				if ( ! empty( $parts[ $field ] ) && 'WordPress.org' === $parts[ $field ] ) {
					$parts[ $field ] = get_wordpress_brand();
				}
			}
		}

		return $parts;
	}
	add_filter( 'document_title_parts', __NAMESPACE__ . '\document_title_parts', 100 );

	/**
	 * Always suffix the WordPress brand to bbPress titles.
	 */
	function bbp_title( $title ) {
		return $title . get_wordpress_brand();
	}
	add_filter( 'bbp_title', __NAMESPACE__ . '\bbp_title', 100 );

	/**
	 * Filter Jetpack opengraph tags to reference the localised WordPress.org site.
	 */
	function jetpack_opengraph( $fields ) {
		if ( isset( $fields['og:site_name'] ) && 'WordPress.org' === $fields['og:site_name'] ) {
			$fields['og:site_name'] = get_wordpress_brand();
		}

		return $fields;
	}
	add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\jetpack_opengraph', 100 );

	/**
	 * Return the 'Brand' of the WordPress.org site.
	 * 
	 * This is "WordPress.org" or a localised variant such as "WordPress Deutch".
	 */
	function get_wordpress_brand() {
		global $rosetta;

		if ( ! isset( $rosetta ) ) {
			return 'WordPress.org';
		}

		$root_id = $rosetta->get_root_site_id();
		$name    = get_blog_option( $root_id, 'blogname' );

		if ( false !== strpos( $name, 'WordPress' ) ) {
			return $name;
		}

		return "WordPress.org $name";
	}

	/**
	 * Output the WordPress favicon on all WordPress.org themes.
	 */
	function favicon_icon() {
		echo '<link rel="icon" href="https://s.w.org/favicon.ico?2" type="image/x-icon" />', "\n";
	}
	add_action( 'wp_head', __NAMESPACE__ . '\favicon_icon', 1 );

}

namespace WordPressdotorg {
	/**
	 * Function to call as `\WordPressdotorg\site_brand()` in other plugins.
	 */
	function site_brand() {
		return \WordPressdotorg\MU_Plugins\Site_Branding\get_wordpress_brand();
	}
}
