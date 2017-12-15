<?php
/**
 * WordPress.org functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPressdotorg\Theme\Main
 */

namespace WordPressdotorg\MainTheme;

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function setup() {
	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'rosetta_main' => esc_html__( 'Rosetta', 'wporg' ),
	) );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );

/**
 * Registers theme-specific widgets.
 */
function widgets() {
	include_once get_stylesheet_directory() . '/widgets/class-wporg-widget-download.php';

	register_widget( __NAMESPACE__ . '\WPORG_Widget_Download' );
	register_widget( 'WP_Widget_Links' );

	add_filter( 'widget_links_args', function( $args ) {
		$args['categorize'] = 0;
		$args['title_li']   = __( 'Resources', 'wporg' );

		return $args;
	} );
}
add_action( 'widgets_init', __NAMESPACE__ . '\widgets' );

/**
 * Custom template tags.
 */
require_once get_stylesheet_directory() . '/inc/template-tags.php';
