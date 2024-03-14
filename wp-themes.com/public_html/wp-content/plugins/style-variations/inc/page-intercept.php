<?php

namespace WordPressdotorg\Theme_Preview\Style_Variations\Page_Intercept;

/**
 * Return the name of the pattern from the $_GET request.
 *
 * @return string
 */
function get_style_variation_from_url() {
	if ( ! isset( $_GET['style_variation'] ) ) {
		return '';
	}

	return sanitize_text_field( urldecode( wp_unslash( $_GET['style_variation'] ) ) );
}

/**
 * Retrieves the variation based on presense of query string.
 *
 * @return false|array
 */
function get_variation_from_query() {
	$variation_title = get_style_variation_from_url();
	if ( empty( $variation_title ) ) {
		return false;
	}

	/**
	 * Retrieve all variations and match to make sure we have one with the same title.
	 */
	$variations = \WP_Theme_JSON_Resolver::get_style_variations();
	if ( empty( $variations ) ) {
		return false;
	}

	return current(
		array_filter(
			$variations,
			function( $variation ) use ( $variation_title ) {
				return strtolower( $variation['title'] ) == strtolower( $variation_title );
			}
		)
	);
}

/**
 * Update the theme's variation if valid query string is present.
 *
 * @param WP_Theme_JSON_Data_Gutenberg $theme_json
 * @return WP_Theme_JSON_Data_Gutenberg
 */
function filter_theme_json_user( $theme_json ) {
	$variation_title = get_style_variation_from_url();
	if ( empty( $variation_title ) ) {
		return $theme_json;
	}

	/**
	 * Retrieve all variations and match to make sure we have one with the same title.
	 */
	$variations = \WP_Theme_JSON_Resolver::get_style_variations();
	if ( empty( $variations ) ) {
		return $theme_json;
	}

	$variation_details = current(
		array_filter(
			$variations,
			function( $variation ) use ( $variation_title ) {
				return strtolower( $variation['title'] ) == strtolower( $variation_title );
			}
		)
	);

	$variation_details = get_variation_from_query();

	if ( ! $variation_details ) {
		return $theme_json;
	}

	// Override styles with variation
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
 * @param string $link
 * @return string URL
 */
function persist_query_string( $link ) {
	$variation_title = get_style_variation_from_url();

	if ( $variation_title ) {
		return add_query_arg( 'style_variation', $variation_title, $link );
	}

	return $link;
}

add_filter( 'page_link', __NAMESPACE__ . '\persist_query_string', 10, 2 );
add_filter( 'post_link', __NAMESPACE__ . '\persist_query_string', 10, 2 );
add_filter( 'term_link', __NAMESPACE__ . '\persist_query_string', 10, 2 );
add_filter( 'home_url', __NAMESPACE__ . '\persist_query_string', 10, 2 );
