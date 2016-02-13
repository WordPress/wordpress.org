<?php

/**
 * WP.org Themes' functions and definitions.
 *
 * @package wporg-plugins
 */

function wporg_plugins_setup() {
	global $themes_allowedtags;

	load_theme_textdomain( 'wporg-plugins' );

	include_once __DIR__ . '/template-tags.php';

	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
	) );

	// No need for canonical lookups
	//remove_action( 'template_redirect', 'redirect_canonical' );
	remove_action( 'template_redirect', 'wp_old_slug_redirect' );
}
add_action( 'after_setup_theme', 'wporg_plugins_setup' );

/**
 * Enqueue scripts and styles.
 */
function wporg_plugins_scripts() {
	$script_debug = true || defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	$suffix       = $script_debug ? '' : '.min';

	// Concatenates core scripts when possible.
	if ( ! $script_debug ) {
		$GLOBALS['concatenate_scripts'] = true;
	}

	$stylesheet = get_stylesheet_uri();
	if ( is_rtl() ) {
//		$stylesheet = str_replace( '.css', '-rtl.css', $stylesheet ); // TODO, not being generated yet
	}
	wp_enqueue_style( 'wporg-plugins', $stylesheet, array(), time() );

	// No Jetpack styles needed.
	add_filter( 'jetpack_implode_frontend_css', '__return_false' );
}
add_action( 'wp_enqueue_scripts', 'wporg_plugins_scripts' );

function wporg_plugins_body_class( $classes ) {
	$classes[] = 'plugins-directory';
	return $classes;
}
add_filter( 'body_class', 'wporg_plugins_body_class' );
