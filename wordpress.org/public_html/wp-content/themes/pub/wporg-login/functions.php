<?php
/**
 * WP.org login functions and definitions.
 *
 * @package wporg-login
 */

require __DIR__ . '/functions-restapi.php';
require __DIR__ . '/functions-registration.php';

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
	if ( WP_WPOrg_SSO::$matched_route ) {
		$classes[] = 'route-' . WP_WPOrg_SSO::$matched_route;
	}

	// Remove the 404 class..
	if ( false !== ( $pos = array_search( 'error404', $classes ) ) ) {
		unset( $classes[ $pos ] );
	}
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
	wp_enqueue_style( 'wporg-login', get_template_directory_uri() . '/stylesheets/login.css', array( 'login', 'dashicons' ), '20161215e' );
}
add_action( 'login_init', 'wporg_login_replace_css' );

/**
 * Enqueue scripts and styles.
 */
function wporg_login_scripts() {
	$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

	// Concatenates core scripts when possible.
	if ( ! $script_debug ) {
		$GLOBALS['concatenate_scripts'] = true;
	}

	wp_enqueue_style( 'wporg-normalize', get_template_directory_uri() . '/stylesheets/normalize.css', 3 );
	wp_enqueue_style( 'wporg-login', get_template_directory_uri() . '/stylesheets/login.css', array( 'login', 'dashicons' ), '20161215e' );
}
add_action( 'wp_enqueue_scripts', 'wporg_login_scripts' );

function wporg_login_register_scripts() {
	wp_register_script( 'recaptcha-api', 'https://www.google.com/recaptcha/api.js', array(), '2' );

	wp_register_script( 'wporg-registration', get_template_directory_uri() . "/js/registration.js", array( 'recaptcha-api', 'jquery' ), '20161215' );
	wp_localize_script( 'wporg-registration', 'wporg_registration', array(
		'rest_url' => esc_url_raw( rest_url( "wporg/v1" ) )
	) );
}
add_action( 'init', 'wporg_login_register_scripts' );

/**
 * Avoid sending a 404 header but send a 200 with nocache headers.
 */
function wporg_login_pre_handle_404( $false, $wp_query ) {
	$wp_query->set_404(); // Set the query as 404 to avoid things running thinking it's a real page
	status_header( 200 ); // but return a 200
	return true;
}
add_filter( 'pre_handle_404', 'wporg_login_pre_handle_404', 10, 2 );

/**
 * Filters the page template to load wporg-login/$route.php.
 *
 * @param array $templates The templates WordPress intends to load.
 * @return array The templates the theme intends to use.
 */
function wporg_login_filter_templates( $templates ) {
	$route = WP_WPOrg_SSO::$matched_route;

	if ( ! $route || 'root' === $route ) {
		$route = 'login';
	}

	return array( "{$route}.php", 'index.php' );
}
add_filter( 'index_template_hierarchy', 'wporg_login_filter_templates' );

// No emoji support needed.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

// No Jetpack styles needed.
add_filter( 'jetpack_implode_frontend_css', '__return_false' );

// No embeds needed.
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
remove_action( 'rest_api_init', 'wp_oembed_register_route' );

// Don't perform any WP_Query queries on this site..
add_filter( 'posts_request', '__return_empty_string' );
// Don't attempt to do canonical lookups..
remove_filter( 'template_redirect', 'redirect_canonical' );
// There's no need to edit the site..
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
// We don't need all the rest routes either..
remove_action( 'rest_api_init', 'create_initial_rest_routes', 99 );

// Don't need all the wp-admin specific user metas on user create/update
add_filter( 'insert_user_meta', '__return_empty_array', 1 );
