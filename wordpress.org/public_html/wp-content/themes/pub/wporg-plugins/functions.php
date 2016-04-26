<?php
namespace WordPressdotorg\Plugin_Directory\Theme;

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

	register_sidebar( array(
		'name'          => 'Single Plugin View Sidebar',
		'id'            => 'single-plugin-sidebar',
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
	) );

	// No need for canonical lookups
	remove_action( 'template_redirect', __NAMESPACE__ . '\wp_old_slug_redirect' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\wporg_plugins_setup' );

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
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\wporg_plugins_scripts' );

function wporg_plugins_body_class( $classes ) {
	$classes[] = 'plugins-directory';
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\wporg_plugins_body_class' );
