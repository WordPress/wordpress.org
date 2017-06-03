<?php
/**
 * Jetpack Compatibility File
 *
 * @link https://jetpack.com/
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

add_action( 'after_setup_theme', __NAMESPACE__ . '\jetpack_setup' );

/**
 * Jetpack setup function.
 *
 * See: https://jetpack.com/support/infinite-scroll/
 * See: https://jetpack.com/support/responsive-videos/
 */
function jetpack_setup() {
	add_theme_support(
		'infinite-scroll',
		array(
			'container' => 'main',
			'render'    => __NAMESPACE__ . '\infinite_scroll_render',
			'footer'    => 'page',
		)
	);

	add_theme_support( 'jetpack-responsive-videos' );
}

/**
 * Custom render function for Infinite Scroll.
 */
function infinite_scroll_render() {
	while ( have_posts() ) {
		the_post();

		if ( is_search() ) {
			get_template_part( 'template-parts/content', 'search' );
		} else {
			get_template_part( 'template-parts/content', get_post_format() );
		}
	}
}
