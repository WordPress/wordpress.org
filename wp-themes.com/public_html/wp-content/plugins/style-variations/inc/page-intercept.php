<?php

namespace WordPressdotorg\Theme_Preview\Style_Variations\Page_Intercept;

use function WordPressdotorg\Theme_Preview\Style_Variations\get_style_variations;
use function WordPressdotorg\Theme_Preview\Style_Variations\get_variation_query_value;

/**
 * Return the requested style variation title from the $_GET request.
 *
 * @return string
 */
function get_style_variation_from_url() {
	if ( ! isset( $_GET['style_variation'] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $_GET['style_variation'] ) );
}

/**
 * Retrieves the variation registered by the theme that matches the query string.
 *
 * The result is cached per theme, as the link filters below call this once per link on the page.
 *
 * @return false|array The variation, or false when the query string is absent or matches nothing.
 */
function get_variation_from_query() {
	static $cache = array();

	$stylesheet = get_stylesheet();
	if ( array_key_exists( $stylesheet, $cache ) ) {
		return $cache[ $stylesheet ];
	}

	// Written before the lookup on purpose: the lookup below reads the theme's styles and can
	// call home_url(), which runs persist_query_string() and lands back here. The early miss
	// turns that into a no-op instead of a recursion.
	$cache[ $stylesheet ] = false;

	$variation_title = get_style_variation_from_url();
	if ( empty( $variation_title ) ) {
		return false;
	}

	$variations = get_style_variations();
	if ( empty( $variations ) ) {
		return false;
	}

	$cache[ $stylesheet ] = current(
		array_filter(
			$variations,
			function ( $variation ) use ( $variation_title ) {
				return strtolower( $variation['title'] ) === strtolower( $variation_title );
			}
		)
	);

	return $cache[ $stylesheet ];
}

/**
 * Update the theme's variation if valid query string is present.
 *
 * @param WP_Theme_JSON_Data_Gutenberg $theme_json The theme JSON data.
 * @return WP_Theme_JSON_Data_Gutenberg
 */
function filter_theme_json_user( $theme_json ) {
	$variation_details = get_variation_from_query();

	if ( ! $variation_details ) {
		return $theme_json;
	}

	// Override styles with variation.
	$new_data = array(
		'version' => 2,
	);

	if ( ! empty( $variation_details['settings'] ) ) {
		$new_data['settings'] = $variation_details['settings'];
	}

	if ( ! empty( $variation_details['styles'] ) ) {
		$new_data['styles'] = $variation_details['styles'];
	}

	return $theme_json->update_with( $new_data );
}

/**
 * We need to call gutenberg's filter `theme_json_user` to make sure the styles are applied to the page.
 * This use to work for both the page and card but a core change stopped that.
 *
 * See: https://core.trac.wordpress.org/ticket/56812
 *
 * We now need to also call the core filter `wp_theme_json_data_user` to get the card preview to work.
 * Hopefully this code can be remove when we have a better component to use.
 *
 * Ref: https://github.com/WordPress/gutenberg/issues/44886
 */
add_filter( 'theme_json_user', __NAMESPACE__ . '\filter_theme_json_user' );
add_filter( 'wp_theme_json_data_user', __NAMESPACE__ . '\filter_theme_json_user' );

/**
 * Appends a query string to maintain the style variation state.
 *
 * Only a variation the theme actually registers is carried across, using the same
 * lower-cased, URL-encoded form the styles endpoint generates.
 *
 * @param string $link The link being filtered.
 * @return string URL
 */
function persist_query_string( $link ) {
	$variation = get_variation_from_query();

	if ( ! $variation || empty( $variation['title'] ) ) {
		return $link;
	}

	return add_query_arg( 'style_variation', get_variation_query_value( $variation ), $link );
}

add_filter( 'page_link', __NAMESPACE__ . '\persist_query_string' );
add_filter( 'post_link', __NAMESPACE__ . '\persist_query_string' );
add_filter( 'term_link', __NAMESPACE__ . '\persist_query_string' );
add_filter( 'home_url', __NAMESPACE__ . '\persist_query_string' );
