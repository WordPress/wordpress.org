<?php

/**
 * WP.org Themes' functions and definitions.
 *
 * @package wporg-themes
 */

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function wporg_themes_setup() {
	global $themes_allowedtags;

	load_theme_textdomain( 'wporg-themes' );

	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
	) );

	// No need for canonical lookups
	remove_action( 'template_redirect', 'redirect_canonical' );
	remove_action( 'template_redirect', 'wp_old_slug_redirect' );
}
add_action( 'after_setup_theme', 'wporg_themes_setup' );

/**
 * Enqueue scripts and styles.
 */
function wporg_themes_scripts() {
	$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	$suffix       = $script_debug ? '' : '.min';

	// Concatenates core scripts when possible.
	if ( ! $script_debug ) {
		$GLOBALS['concatenate_scripts'] = true;
	}

	wp_enqueue_style( 'wporg-themes', get_stylesheet_uri(), array(), '1' );

	if ( ! is_singular( 'page' ) ) {
		wp_enqueue_script( 'google-jsapi', '//www.google.com/jsapi', array( 'jquery' ), null, true );
		wp_enqueue_script( 'wporg-theme', get_template_directory_uri() . "/js/theme{$suffix}.js", array( 'wp-backbone' ), '2', true );

		wp_localize_script( 'wporg-theme', '_wpThemeSettings', array(
			'themes'   => false,
			'query'    => wporg_themes_get_themes_for_query(),
			'settings' => array(
				'title'        => __( 'WordPress &#8250; %s &laquo; Free WordPress Themes', 'wporg-themes' ),
				'isMobile'     => wp_is_mobile(),
				'postsPerPage' => 24,
				'path'         => trailingslashit( parse_url( home_url(), PHP_URL_PATH ) ),
			),
			'l10n' => array(
				'search'            => __( 'Search Themes', 'wporg-themes' ),
				'searchPlaceholder' => __( 'Search themes...', 'wporg-themes' ), // placeholder (no ellipsis)
				'error'             => __( 'An unexpected error occurred.', 'wporg-themes' ),

				// Downloads Graph
				'date'      => __( 'Date', 'wporg-themes' ),
				'downloads' => __( 'Downloads', 'wporg-themes' ),
			),
		) );
	}

	// No emoji support needed.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	// No Jetpack styles needed.
	add_filter( 'jetpack_implode_frontend_css', '__return_false' );

	// No dashicons needed.
	wp_deregister_style( 'dashicons' );
	wp_register_style( 'dashicons', '' );
}
add_action( 'wp_enqueue_scripts', 'wporg_themes_scripts' );

/**
 * Extend the default WordPress body classes.
 *
 * Adds body classes to
 * 1. denote singular themes.
 * 2. Identify IE8.
 *
 * @param array $classes A list of existing body class values.
 * @return array The filtered body class list.
 */
function wporg_themes_body_class( $classes ) {

	if ( ! is_page() && get_query_var( 'name' ) && ! is_404() ) {
		$classes[] = 'modal-open';
	}

	if ( $GLOBALS['is_IE'] && false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 8' ) ) {
		$classes[] = 'ie8';
	}

	return $classes;
}
add_filter( 'body_class', 'wporg_themes_body_class' );

/**
 * Prevent the default posts queries running, allowing pages to bypass
 * We do this as the themes are pulled from an API.
 */
function wporg_themes_prevent_posts_query( $query, $wp_query ) {
	if ( is_admin() || ! $wp_query->is_main_query() || $wp_query->get( 'pagename' ) ) {
		return $query;
	}
	$wp_query->set( 'no_found_rows', true );
	return ''; // Don't make a query
}
add_filter( 'posts_request', 'wporg_themes_prevent_posts_query', 10, 2 );

/**
 * Prevent 404 responses when we've got a theme via the API.
 */
function wporg_themes_prevent_404() {
	global $wp_query;
	if ( ! is_404() ) {
		return;
	}
	$themes = wporg_themes_get_themes_for_query();
	if ( $themes['total'] ) {
		$wp_query->is_404 = false;
		status_header( 200 );
	}
}
add_filter( 'template_redirect', 'wporg_themes_prevent_404' );

/**
 * Overrides feeds to use a custom RSS2 feed which contains the current requests themes.
 */
function wporg_themes_custom_feed() {
	if ( ! is_feed() ) {
		return;
	}
	include __DIR__ . '/rss.php';
	die();
}
add_filter( 'template_redirect', 'wporg_themes_custom_feed' );

/**
 * Include view templates in the footer.
 */
function wporg_themes_view_templates() {
	get_template_part( 'view-templates/theme' );
	get_template_part( 'view-templates/theme-preview' );
	get_template_part( 'view-templates/theme-single' );
}
add_action( 'wp_footer', 'wporg_themes_view_templates' );
