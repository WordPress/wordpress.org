<?php
/**
 * Plugin name: Style Variation Preview
 * Description: Adds features to preview a page using its style variation.
 * Version:     1.0.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\Theme_Preview\Style_Variations;

defined( 'WPINC' ) || die();

/**
 * Get unique style variations.
 *
 * If there are multiple variations matching a title, use the longest. This is
 * a rough way to check for full (combined) style variations, as opposed to
 * color palettes or type sets.
 *
 * @return array Variation list, or empty array.
 */
function get_style_variations() {
	$variations = array();
	$all_variations = \WP_Theme_JSON_Resolver::get_style_variations();

	foreach ( $all_variations as $variation ) {
		// Safety check: variations must have a title and settings.
		if ( ! isset( $variation['title'], $variation['settings'] ) ) {
			continue;
		}
		$found_variations = wp_list_filter(
			$variations,
			array( 'title' => $variation['title'] )
		);
		if ( ! $found_variations ) {
			$variations[] = $variation;
		} else {
			// Loop over the found variations, even though there should only
			// ever be one, to keep track of the index value.
			foreach ( $found_variations as $i => $found ) {
				$found_settings = wp_json_encode( $found );
				$new_settings = wp_json_encode( $variation );
				if ( strlen( $new_settings ) > strlen( $found_settings ) ) {
					// Replace the item in the variations list.
					$variations[ $i ] = $variation;
				}
			}
		}
	}

	return $variations;
}

require_once __DIR__ . '/inc/global-style-page.php';
require_once __DIR__ . '/inc/page-intercept.php';
require_once __DIR__ . '/inc/styles-endpoint.php';
