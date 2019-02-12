<?php
/**
 * Jetpack Compatibility File
 * See: https://jetpack.me/
 *
 * @package wporg-developer
 */

/**
 * Add theme support for Infinite Scroll.
 * See: https://jetpack.me/support/infinite-scroll/
 */
function wporg_developer_jetpack_setup() {
	add_theme_support( 'infinite-scroll', array(
		'container' => 'main',
		'footer'    => 'page',
	) );
}
add_action( 'after_setup_theme', 'wporg_developer_jetpack_setup' );
