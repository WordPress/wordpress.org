<?php

namespace WordPressdotorg\Openverse\Theme;

use function WordPressdotorg\Locales\get_locales;

/**
 * This is the URL on which the frontend site of Openverse is hosted. Unless
 * overridden from the Customizer UI, this is the URL for the embedded `iframe`.
 *
 * Note: Do not put a trailing slash '/' in this url, as it will cause problems.
 */
if ( !defined( 'OPENVERSE_URL' ) ) {
	define( 'OPENVERSE_URL', 'https://search.openverse.engineering' );
}

/**
 * This is subdirectory on WordPress.org which loads the Openverse site. This is
 * prefixed in front of all path changes sent by the embedded `iframe`.
 *
 * When used with the standalone redirect, it is removed from the path forwarded
 * to the standalone site.
 */
if ( !defined( 'OPENVERSE_SUBPATH' ) ) {
    define( 'OPENVERSE_SUBPATH', '/openverse' );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook.
 */
function setup() {
	// The parent wporg theme is designed for use on wordpress.org/* and assumes
	// locale-domains are available. Remove hreflang support.
	remove_action( 'wp_head', 'WordPressdotorg\Theme\hreflang_link_attributes' );

	// This page is not oEmbed'able
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );


	/**
	 * Override all possible templates with index.php
	 */
	foreach ( array( '404', 'archive', 'author', 'category', 'tag', 'taxonomy', 'date', 'embed', 'home', 'frontpage', 'privacypolicy', 'page', 'paged', 'search', 'single', 'singular', 'attachment' ) as $template_type ) {
		add_filter( $template_type . '_template', __NAMESPACE__ . '\use_index_php_as_template' );
	}
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );

/**
 * Disable the default WP_Query post/page queries
 * 
 * WordPress always queries for the matching posts,
 * but since this theme is nothing more than an iframe
 * disable those queries by returning an empty SQL
 * query for the main WP_Query instance.
 * 
 * Set no_found_rows to avoid querying for FOUND_ROWS().
 */
function disable_default_query( $sql, $query ) {
	if ( $query->is_main_query() ) {
		$sql = '';
		$query->set( 'no_found_rows', true );
	}

	return $sql;
}
add_filter( 'posts_request', __NAMESPACE__ . '\disable_default_query', 10, 2 );

/**
 * Disable request parsing.
 * 
 * This avoids `WP` parsing the requested URI to generate the WP_Query parameters.
 * As all URIs will be passed through to the iframe, there's no point in parsing
 * this or querying for posts that definitely do not exist within the WordPress site.
 */
function disable_parse_request( $return, $wp ) {
	// Avoid E_WARNING: array_keys() expects parameter 1 to be array, null given in wp-includes/class-wp.php:548
	$wp->query_vars = array();

	return false;
}
add_filter( 'do_parse_request', __NAMESPACE__ . '\disable_parse_request', 10, 2 );

/**
 * Get the slug of the given WP locale. This function returns a blank string if
 * the locale is `en_US` because that is considered the default and is not
 * prefixed in the URL paths. It also returns a blank string if `$wp_locale` is
 * not found in the locales list.
 */
function get_locale_slug( $curr_locale ) {
	if ( $curr_locale === 'en_US' ) {
		return '';
	}

	return get_locales()[ $curr_locale ]->slug ?? '';
}

/**
 * Enqueue styles & scripts.
 *
 * The wporg theme registers these with static versions, so we need to override
 * with dynamic versions for cache-busting. The version is set to the last
 * modified time during development.
 */
function enqueue_assets() {
	wp_enqueue_style(
		/* handle    */ 'openverse-style',
		/* src       */ get_theme_file_uri( '/css/openverse.css' ),
		/* deps      */ array(),
		/* ver       */ filemtime( __DIR__ . '/css/openverse.css' )
	);

	wp_enqueue_script(
		/* handle    */ 'openverse-message',
		/* src       */ get_theme_file_uri( '/js/message.js' ),
		/* deps      */ array(),
		/* ver       */ filemtime( __DIR__ . '/js/message.js' ),
		/* in_footer */ true
	);

	$use_path_based_locale_forwarding = get_theme_mod( 'ov_path_based_i18n', false );
	$curr_locale = get_locale();
	$locale_slug = '';
	if ( $use_path_based_locale_forwarding ) {
		$locale_slug = get_locale_slug( $curr_locale );
	}

	wp_add_inline_script(
		/* handle   */ 'openverse-message',
		/* JS       */ 'const openverseUrl = ' . wp_json_encode( get_theme_mod( 'ov_src_url', OPENVERSE_URL ) ) . ";\n" .
		/* JS       */ 'const openverseSubpath = ' . wp_json_encode( OPENVERSE_SUBPATH ) . ";\n" .
		/* JS       */ 'const currentLocale = ' . wp_json_encode( $curr_locale ) . ";\n" . /* Used for legacy cookie based locale forwarding */
		/* JS       */ 'const localeSlug = ' . wp_json_encode( $locale_slug ) . ";\n",
		/* position */ 'before'
	);

	wp_enqueue_script(
		/* handle    */ 'openverse-navigation',
		/* src       */ get_theme_file_uri( '/js/iframe_nav.js' ),
		/* deps      */ array( 'openverse-message' /* for the consts */ ),
		/* ver       */ filemtime( __DIR__ . '/js/iframe_nav.js' ),
		/* in_footer */ true
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );

/**
 * Use the index.php template for various WordPress views that would otherwise
 * be handled by the parent theme.
 */
function use_index_php_as_template() {
	// Force all pages to be a 200 response.
	status_header( 200 );

	return __DIR__ . '/index.php';
}

/**
 * Replace the <title> title with just the generic title.
 */
function title_no_title( $title ) {
	unset( $title['title'] );

	$title['site'] = get_bloginfo( 'name', 'display' );

	return $title;
}
add_filter( 'document_title_parts', __NAMESPACE__ . '\title_no_title' );

/*
	TODO: Delete this and everything related to it
	======================
	Openverse iframe embed
	======================
 */

/**
 * Enable the option to set the URL for the Openverse embed via a GUI.
 *
 * @param \WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function wporg_ov_customizer( $wp_customize ) {
	$wp_customize->add_section( 'ov_embed', array(
		'priority' => 10,
		'capability' => 'edit_theme_options',
		'title' => 'Openverse Embed',
		'description' => 'Configure the Openverse embed iframe.'
	) );

	$wp_customize->add_setting( 'ov_src_url', array(
		'type' => 'theme_mod',
		'capability' => 'edit_theme_options',
		'default' => OPENVERSE_URL,
		'sanitize_callback' => function( $val, $setting ) {
			if ( empty( $val ) ) {
				return $setting->default;
			}
			return $val;
		}
	) );

	$wp_customize->add_control( 'ov_src_url', array(
		'section' => 'ov_embed',
		'type' => 'url',
		'id' => 'ov_src_url',
		'label' => 'URL', 
		'description' => 'Default: ' . esc_html( OPENVERSE_URL ),
		'priority' => 10,
		'input_attrs' => array(
			'placeholder' => 'URL'
		)
	) );

	$wp_customize->add_setting( 'ov_path_based_i18n', array(
		'type' => 'theme_mod',
		'capability' => 'edit_theme_options',
		'default' => false
	) );

	$wp_customize->add_control( 'ov_path_based_i18n', array(
		'section' => 'ov_embed',
		'type' => 'checkbox',
		'id' => 'ov_path_based_i18n',
		'label' => 'Use path based locale forwarding',
		'priority' => 10
	) );
}
add_action( 'customize_register', __NAMESPACE__ . '\wporg_ov_customizer' );

/*
	=====================================
	Openverse standalone site redirection
	=====================================
 */

/**
 * This is the URL at which the standalone Openverse site is hosted. When
 * redirect is enabled (see setting `ov_is_redirect_enabled`), the theme
 * redirects all incoming requests to the right URL on this domain.
 *
 * Note: Do not put a trailing slash '/' in this URL. Paths start with a leading
 * slash so a trailing slash here will lead to two slashes in the final URL.
 */
if ( !defined( 'OPENVERSE_STANDALONE_URL' ) ) {
	define( 'OPENVERSE_STANDALONE_URL', 'https://openverse.wordpress.net' );
}

/**
 * Determine the target URL of the redirect based on the Openverse standalone
 * URL, the requested path and the current locale.
 *
 * Examples:
 * - https://ru.wordpress.org/openverse → {ov_redirect_url}/ru/
 * - https://wordpress.org/openverse/search/?q=dog → {ov_redirect_url}/search/?q=dog
 */
function get_target_url() {
	$target_url = get_theme_mod( 'ov_redirect_url', OPENVERSE_STANDALONE_URL );

	$curr_locale = get_locale();
	$locale = get_locale_slug( $curr_locale );
	if ( $locale !== '' ) {
		$target_url .= '/' . $locale;
	}

	$path = $_SERVER['REQUEST_URI'];
	if ( $path ) {
		$count = 1; // Only replace the leading Openverse subpath.
		$target_url .= str_replace( OPENVERSE_SUBPATH, '', $path, $count );
	}

	return $target_url;
}

/**
* Provide configuration for the theme to redirect to the given standalone
* Openverse site. The destination URL can be configured and the behaviour can
* be dormant unless enabled.
*
* @param \WP_Customize_Manager $wp_customize Theme Customizer object.
*/
function wporg_ov_redir_customizer( $wp_customize ) {
	$wp_customize->add_section( 'ov_redir', array(
		'priority' => 10,
		'capability' => 'edit_theme_options',
		'title' => 'Openverse Redirect',
		'description' => 'Configure the redirection to the standalone Openverse site.'
	) );

	$wp_customize->add_setting( 'ov_redirect_url', array(
		'type' => 'theme_mod',
		'capability' => 'edit_theme_options',
		'default' => OPENVERSE_STANDALONE_URL,
		'sanitize_callback' => function( $val, $setting ) {
			if ( substr( $val, -1 ) == '/' ) { // If the last character is a slash '/',...
				$val = substr( $val, 0, -1 );    // ...remove it.
			}
			if ( empty( $val ) ) {
				return $setting->default;
			}
			return $val;
		}
	) );

	$wp_customize->add_control( 'ov_redirect_url', array(
		'section' => 'ov_redir',
		'type' => 'url',
		'id' => 'ov_redirect_url',
		'label' => 'Redirect URL',
		'description' => '<b>Note</b>: '
									. 'Do not put a trailing slash \'/\' in this URL.<br/>'
									. '<b>Default</b>: '
									. esc_html( OPENVERSE_STANDALONE_URL ),
		'priority' => 10,
		'input_attrs' => array(
			'placeholder' => 'URL'
		)
	) );

	$wp_customize->add_setting( 'ov_is_redirect_enabled', array(
		'type' => 'theme_mod',
		'capability' => 'edit_theme_options',
		'default' => false
	) );

	$wp_customize->add_control( 'ov_is_redirect_enabled', array(
		'section' => 'ov_redir',
		'type' => 'checkbox',
		'id' => 'ov_is_redirect_enabled',
		'label' => 'Redirect to the standalone Openverse site.',
		'priority' => 10
	) );
}
add_action( 'customize_register', __NAMESPACE__ . '\wporg_ov_redir_customizer' );
