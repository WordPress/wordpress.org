<?php
/**
 * WordPress.org functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

// Register path to fallback files.
if ( ! defined( 'WPORGPATH' ) ) {
	define( 'WPORGPATH', get_parent_theme_file_path( '/inc/' ) );
}

// Make sure market share is available.
if ( ! defined( 'WP_MARKET_SHARE' ) ) {
	define( 'WP_MARKET_SHARE', 29 );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function setup() {

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	// Don't include Adjacent Posts functionality.
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'primary' => esc_html__( 'Primary', 'wporg' ),
	) );

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
	) );

	// Set up the WordPress core custom background feature.
	add_theme_support( 'custom-background', apply_filters( 'wporg_custom_background_args', array(
		'default-color' => 'ffffff',
		'default-image' => '',
	) ) );

	add_theme_support( 'wp4-styles' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );

/**
 * Sets the document title.
 *
 * The global $pagetitle is used by the global w.org header.
 *
 * @global string $pagetitle
 */
function set_document_title() {
	$GLOBALS['pagetitle'] = wp_get_document_title();
}
add_action( 'template_redirect', __NAMESPACE__ . '\set_document_title' );

/**
 * Set the separator for the document title.
 *
 * @return string Document title separator.
 */
function document_title_separator() {
	return '&#124;';
}
add_filter( 'document_title_separator', __NAMESPACE__ . '\document_title_separator' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function content_width() {
	$GLOBALS['content_width'] = apply_filters( 'wporg_content_width', 612 );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\content_width', 0 );

/**
 * Enqueue scripts and styles.
 */
function scripts() {
	$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	$suffix       = $script_debug ? '' : '.min';

	// Concatenates core scripts when possible.
	if ( ! $script_debug ) {
		$GLOBALS['concatenate_scripts'] = true;
	}

	wp_enqueue_style( 'wporg-style', get_theme_file_uri( '/css/style.css' ), [ 'dashicons', 'open-sans' ], '20180702' );
	wp_style_add_data( 'wporg-style', 'rtl', 'replace' );

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.InlineComment.InvalidEndChar
	// wp_enqueue_script( 'wporg-navigation', get_template_directory_uri() . "/js/navigation$suffix.js", array(), '20151215', true );
	wp_enqueue_script( 'wporg-plugins-skip-link-focus-fix', get_template_directory_uri() . "/js/skip-link-focus-fix$suffix.js", array(), '20151215', true );

	if ( ! is_front_page() && is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	// No Jetpack scripts needed.
	add_filter( 'jetpack_implode_frontend_css', '__return_false' );
	wp_dequeue_script( 'devicepx' );

	/*
	 * No Grofiles needed.
	 *
	 * Enqueued so that it's overridden in the global footer.
	 */
	wp_register_script( 'grofiles-cards', false );
	wp_enqueue_script( 'grofiles-cards' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts' );

/**
 * Filters an enqueued style's fully-qualified URL.
 *
 * @param string $src    The source URL of the enqueued style.
 * @param string $handle The style's registered handle.
 * @return string
 */
function style_src( $src, $handle ) {
	$cdn_handles = [
		'wporg-style',
		'dashicons',
	];

	if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
		return $src;
	}

	// Use CDN url.
	if ( in_array( $handle, $cdn_handles, true ) ) {
		$src = str_replace( get_home_url(), 'https://s.w.org', $src );
	}

	// Remove version argument.
	if ( in_array( $handle, [ 'open-sans' ], true ) ) {
		$src = remove_query_arg( 'ver', $src );
	}

	return $src;
}
add_filter( 'style_loader_src', __NAMESPACE__ . '\style_src', 10, 2 );

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param \WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport        = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport = 'postMessage';
}
add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function customize_preview_js() {
	wp_enqueue_script( 'wporg_plugins_customizer', get_template_directory_uri() . '/js/customizer.js', array( 'customize-preview' ), '20151215', true );
}
add_action( 'customize_preview_init', __NAMESPACE__ . '\customize_preview_js' );


/**
 * Adds hreflang link attributes to WordPress.org pages.
 *
 * @link https://support.google.com/webmasters/answer/189077?hl=en Use hreflang for language and regional URLs.
 * @link https://sites.google.com/site/webmasterhelpforum/en/faq-internationalisation FAQ: Internationalisation.
 */
function hreflang_link_attributes() {
	// No hreflangs on 404 pages.
	if ( is_404() ) {
		return;
	}

	wp_cache_add_global_groups( array( 'locale-associations' ) );

	// Google doesn't have support for a whole lot of languages and throws errors about it,
	// so we exclude them, as we're otherwise outputting data that isn't used at all.
	$unsupported_languages = array(
		'arq',
		'art',
		'art-xemoji',
		'ary',
		'ast',
		'az-ir',
		'azb',
		'bcc',
		'ff-sn',
		'frp',
		'fuc',
		'fur',
		'haz',
		'ido',
		'io',
		'kab',
		'li',
		'li-nl',
		'lmo',
		'me',
		'me-me',
		'rhg',
		'rup',
		'sah',
		'sc-it',
		'scn',
		'skr',
		'srd',
		'szl',
		'tah',
		'twd',
		'ty-tj',
		'tzm',
	);

	$sites = wp_cache_get( 'local-sites', 'locale-associations' );

	if ( false === $sites ) {
		global $wpdb;

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
		$sites = $wpdb->get_results( 'SELECT locale, subdomain FROM locales', OBJECT_K );
		if ( ! $sites ) {
			return;
		}

		require_once GLOTPRESS_LOCALES_PATH;

		foreach ( $sites as $site ) {
			$gp_locale = \GP_Locales::by_field( 'wp_locale', $site->locale );
			if ( ! $gp_locale ) {
				unset( $sites[ $site->locale ] );
				continue;
			}

			// Skip non-existing subdomains, e.g. 'de_CH_informal'.
			if ( false !== strpos( $site->subdomain, '_' ) ) {
				unset( $sites[ $site->locale ] );
				continue;
			}

			if ( isset( $gp_locale->slug ) && ! in_array( $gp_locale->slug, $unsupported_languages ) ) {
				$sites[ $site->locale ]->hreflang = $gp_locale->slug;
			} else {
				unset( $sites[ $site->locale ] );
			}
		}

		// Add en_US to the list of sites.
		$sites['en_US'] = (object) array(
			'locale'    => 'en_US',
			'hreflang'  => 'en',
			'subdomain' => '',
		);

		// Add x-default to the list of sites.
		$sites['x-default'] = (object) array(
			'locale'    => 'x-default',
			'hreflang'  => 'x-default',
			'subdomain' => '',
		);

		uasort( $sites, function( $a, $b ) {
			return strcasecmp( $a->hreflang, $b->hreflang );
		} );

		wp_cache_set( 'local-sites', $sites, 'locale-associations' );
	}

	if ( is_singular() ) {
		$path = parse_url( get_permalink(), PHP_URL_PATH );
	} else {
		// WordPress doesn't have a good way to get the canonical version of non-singular urls.
		$path = $_SERVER['REQUEST_URI']; // phpcs:ignore
	}

	foreach ( $sites as $site ) {
		$url = sprintf(
			'https://%swordpress.org%s',
			$site->subdomain ? "{$site->subdomain}." : '',
			$path
		);

		printf(
			'<link rel="alternate" href="%s" hreflang="%s" />' . "\n",
			esc_url( $url ),
			esc_attr( $site->hreflang )
		);
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\hreflang_link_attributes' );

/**
 * Custom template tags.
 */
require_once get_template_directory() . '/inc/template-tags.php';
