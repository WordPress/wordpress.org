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

	$stylesheet = get_stylesheet_uri();
	if ( is_rtl() ) {
		$stylesheet = str_replace( '.css', '-rtl.css', $stylesheet );
	}
	wp_enqueue_style( 'wporg-themes', $stylesheet, array(), 9 );

	if ( ! is_singular( 'page' ) ) {
		wp_enqueue_script( 'google-jsapi', '//www.google.com/jsapi', array( 'jquery' ), null, true );
		wp_enqueue_script( 'wporg-theme', get_template_directory_uri() . "/js/theme{$suffix}.js", array( 'wp-backbone' ), 9, true );

		wp_localize_script( 'wporg-theme', '_wpThemeSettings', array(
			'themes'   => false,
			'query'    => wporg_themes_get_themes_for_query(),
			'settings' => array(
				/* translators: %s: theme name */
				'title'        => __( '%s &mdash; Free WordPress Themes', 'wporg-themes' ),
				'isMobile'     => wp_is_mobile(),
				'postsPerPage' => 24,
				'path'         => trailingslashit( parse_url( home_url(), PHP_URL_PATH ) ),
				'locale'       => get_locale(),
				'favorites'    => array(
					'themes' => wporg_themes_get_user_favorites(),
					'user'   => wp_get_current_user()->user_login,
					'nonce'  => is_user_logged_in() ? wp_create_nonce( 'modify-theme-favorite' ) : false,
				),
			),
			'l10n' => array(
				'search'            => __( 'Search Themes', 'wporg-themes' ),
				'searchPlaceholder' => __( 'Search themes...', 'wporg-themes' ), // placeholder (no ellipsis)
				'error'             => __( 'An unexpected error occurred.', 'wporg-themes' ),

				// Downloads Graph
				'date'      => __( 'Date', 'wporg-themes' ),
				'downloads' => __( 'Downloads', 'wporg-themes' ),

				// Tags
				'tags' => wporg_themes_get_tag_translations(),

				// Active Installs
				'active_installs_less_than_10' => __( 'Less than 10', 'wporg-themes' ),
				'active_installs_1_million' => __( '1+ million', 'wporg-themes' ),
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
 * 3. denote if no themes were found.
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

	if ( empty( $GLOBALS['themes']['themes'] ) && ! is_singular( 'page' ) ) {
		$classes[] = 'no-results';
	}

	return $classes;
}
add_filter( 'body_class', 'wporg_themes_body_class' );

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
	if ( ! is_singular( 'page' ) ) {
		get_template_part( 'view-templates/theme' );
		get_template_part( 'view-templates/theme-preview' );
		get_template_part( 'view-templates/theme-single' );
	}
}
add_action( 'wp_footer', 'wporg_themes_view_templates' );

/**
 * This is a copy of get_theme_feature_list(), but with the wporg-themes text domain
 */
function wporg_themes_get_feature_list() {
	return array(
		__( 'Colors', 'wporg-themes' )   => array(
			'black'  => __( 'Black',  'wporg-themes' ),
			'blue'   => __( 'Blue',   'wporg-themes' ),
			'brown'  => __( 'Brown',  'wporg-themes' ),
			'gray'   => __( 'Gray',   'wporg-themes' ),
			'green'  => __( 'Green',  'wporg-themes' ),
			'orange' => __( 'Orange', 'wporg-themes' ),
			'pink'   => __( 'Pink',   'wporg-themes' ),
			'purple' => __( 'Purple', 'wporg-themes' ),
			'red'    => __( 'Red',    'wporg-themes' ),
			'silver' => __( 'Silver', 'wporg-themes' ),
			'tan'    => __( 'Tan',    'wporg-themes' ),
			'white'  => __( 'White',  'wporg-themes' ),
			'yellow' => __( 'Yellow', 'wporg-themes' ),
			'dark'   => __( 'Dark',   'wporg-themes' ),
			'light'  => __( 'Light',  'wporg-themes' ),
		),
		__( 'Layout', 'wporg-themes' ) => array(
			'fixed-layout'      => __( 'Fixed Layout',      'wporg-themes' ),
			'fluid-layout'      => __( 'Fluid Layout',      'wporg-themes' ),
			'responsive-layout' => __( 'Responsive Layout', 'wporg-themes' ),
			'one-column'        => __( 'One Column',        'wporg-themes' ),
			'two-columns'       => __( 'Two Columns',       'wporg-themes' ),
			'three-columns'     => __( 'Three Columns',     'wporg-themes' ),
			'four-columns'      => __( 'Four Columns',      'wporg-themes' ),
			'left-sidebar'      => __( 'Left Sidebar',      'wporg-themes' ),
			'right-sidebar'     => __( 'Right Sidebar',     'wporg-themes' ),
		),
		__( 'Features', 'wporg-themes' ) => array(
			'accessibility-ready'   => __( 'Accessibility Ready',   'wporg-themes' ),
			'blavatar'              => __( 'Blavatar',              'wporg-themes' ),
			'buddypress'            => __( 'BuddyPress',            'wporg-themes' ),
			'custom-background'     => __( 'Custom Background',     'wporg-themes' ),
			'custom-colors'         => __( 'Custom Colors',         'wporg-themes' ),
			'custom-header'         => __( 'Custom Header',         'wporg-themes' ),
			'custom-menu'           => __( 'Custom Menu',           'wporg-themes' ),
			'editor-style'          => __( 'Editor Style',          'wporg-themes' ),
			'featured-image-header' => __( 'Featured Image Header', 'wporg-themes' ),
			'featured-images'       => __( 'Featured Images',       'wporg-themes' ),
			'flexible-header'       => __( 'Flexible Header',       'wporg-themes' ),
			'front-page-post-form'  => __( 'Front Page Posting',    'wporg-themes' ),
			'full-width-template'   => __( 'Full Width Template',   'wporg-themes' ),
			'microformats'          => __( 'Microformats',          'wporg-themes' ),
			'post-formats'          => __( 'Post Formats',          'wporg-themes' ),
			'rtl-language-support'  => __( 'RTL Language Support',  'wporg-themes' ),
			'sticky-post'           => __( 'Sticky Post',           'wporg-themes' ),
			'theme-options'         => __( 'Theme Options',         'wporg-themes' ),
			'threaded-comments'     => __( 'Threaded Comments',     'wporg-themes' ),
			'translation-ready'     => __( 'Translation Ready',     'wporg-themes' ),
		),
		__( 'Subject', 'wporg-themes' ) => array(
			'holiday'       => __( 'Holiday',       'wporg-themes' ),
			'photoblogging' => __( 'Photoblogging', 'wporg-themes' ),
			'seasonal'      => __( 'Seasonal',      'wporg-themes' ),
		)
	);
}

/**
 * Returns an array of [ tag_slug => translated_tag_name] tags for translation within JS
 */
function wporg_themes_get_tag_translations() {
	$translations = array();
	foreach ( wporg_themes_get_feature_list() as $group => $tags ) {
		$translations = array_merge( $translations, $tags );
	}
	return $translations;
}

/**
 * Override the embed template with our own
 */
function wporg_themes_embed_template( $template ) {
	$theme_embed_template = locate_template( 'embed.php' );
	if ( $theme_embed_template ) {
		return $theme_embed_template;
	}
	return $template;
}
add_filter('embed_template', 'wporg_themes_embed_template');

