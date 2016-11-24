<?php
/**
 * WP.org login functions and definitions.
 *
 * @package wporg-login
 */

/**
 * No-cache headers.
 */
add_action( 'template_redirect', 'nocache_headers', 10, 0 );

/**
 * Registers support for various WordPress features.
 */
function wporg_login_setup() {
	load_theme_textdomain( 'wporg-login' );
}
add_action( 'after_setup_theme', 'wporg_login_setup' );

/**
 * Extend the default WordPress body classes.
 *
 * @param array $classes A list of existing body class values.
 * @return array The filtered body class list.
 */
function wporg_login_body_class( $classes ) {
//	$classes[] = 'wporg-responsive';
	$classes[] = 'wporg-login';
	return $classes;
}
add_filter( 'body_class', 'wporg_login_body_class' );

/**
 * Remove the toolbar.
 */
function wporg_login_init() {
	show_admin_bar( false );
}
add_action( 'init', 'wporg_login_init' );

/**
 * Replace cores login CSS with our own.
 */
function wporg_login_replace_css() {
	wp_enqueue_style( 'wporg-login', get_template_directory_uri() . '/stylesheets/login.css', array( 'login', 'dashicons', 'l10n' ), 2.3 );
}
add_action( 'login_init', 'wporg_login_replace_css' );

/**
 * Enqueue scripts and styles.
 */
function wporg_login_scripts() {
	$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	$suffix       = $script_debug ? '' : '.min';

	// Concatenates core scripts when possible.
	if ( ! $script_debug ) {
		$GLOBALS['concatenate_scripts'] = true;
	}

	wp_enqueue_style( 'wporg-normalize', get_template_directory_uri() . '/stylesheets/normalize.css', 3 );
	wp_enqueue_style( 'wporg-login', get_template_directory_uri() . '/stylesheets/login.css', array( 'login', 'dashicons', 'l10n' ), 2.3 );

	// No emoji support needed.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	// No Jetpack styles needed.
	add_filter( 'jetpack_implode_frontend_css', '__return_false' );

	// No embeds needed.
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
}
add_action( 'wp_enqueue_scripts', 'wporg_login_scripts' );
