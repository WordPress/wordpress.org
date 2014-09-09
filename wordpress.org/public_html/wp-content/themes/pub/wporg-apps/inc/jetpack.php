<?php
/**
 * Jetpack Compatibility File
 * See: http://jetpack.me/
 *
 * @package wpmobileapps
 */

/**
 * Add theme support for Infinite Scroll.
 * See: http://jetpack.me/support/infinite-scroll/
 */
function wpmobileapps_jetpack_setup() {
	add_theme_support( 'infinite-scroll', array(
		'container'      => 'main',
		'footer'         => 'page',
		'wrapper'        => false,
		'footer_widgets' => array(
								'sidebar-1',
								'sidebar-2',
								'sidebar-3',
							),
	) );
}
add_action( 'after_setup_theme', 'wpmobileapps_jetpack_setup' );
