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
 * See: https://jetpack.com/support/content-options/
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

	$featured_image_enabled = ! wcorg_skip_feature( 'cs17_display_featured_image' );
	add_theme_support(
		'jetpack-content-options',
		array(
			'featured-images'    => array(
				'archive'           => true,
				'archive-default'   => $featured_image_enabled,
				'post'              => true,
				'post-default'      => $featured_image_enabled,
				'page'              => true,
				'page-default'      => $featured_image_enabled,
				'fallback'          => false,
			),
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
