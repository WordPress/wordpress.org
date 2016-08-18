<?php
/**
 * Plugin Directory functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

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

	// Don't include Adjacent Posts functionality
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'primary' => esc_html__( 'Primary', 'wporg-plugins' ),
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
	add_theme_support( 'custom-background', apply_filters( 'wporg_plugins_custom_background_args', array(
		'default-color' => 'ffffff',
		'default-image' => '',
	) ) );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function content_width() {
	$GLOBALS['content_width'] = apply_filters( 'wporg_plugins_content_width', 640 );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\content_width', 0 );

/**
 * Enqueue scripts and styles.
 */
function scripts() {
	$suffix = is_rtl() ? '-rtl' : '';
	wp_enqueue_style( 'wporg-plugins-style', get_template_directory_uri() . "/css/style{$suffix}.css", array(), time() );

	wp_enqueue_script( 'wporg-plugins-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20151215', true );
	wp_enqueue_script( 'wporg-plugins-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20151215', true );

	if ( is_singular( 'plugin' ) ) {
		wp_enqueue_script( 'wporg-plugins-accordion', get_template_directory_uri() . '/js/section-accordion.js', array(), '20160525', true );
	}

	wp_enqueue_script( 'wporg-plugins-locale-banner', get_template_directory_uri() . '/js/locale-banner.js', array(), '20160622', true );
	wp_localize_script( 'wporg-plugins-locale-banner', 'wporgLocaleBanner', array(
		'apiURL'        => rest_url( '/plugins/v1/locale-banner' ),
		'currentPlugin' => is_singular( 'plugin' ) ? get_queried_object()->post_name : '',
	) );

	if ( isset( $_REQUEST['react'] ) ) {
		wp_enqueue_script( 'wporg-plugins-client', get_template_directory_uri() . '/js/theme.js', array(), false, true );
		wp_localize_script( 'wporg-plugins-client', 'app_data', array(
			'api_url' => untrailingslashit( rest_url() ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'base'    => get_blog_details()->path,
		) );
	}

}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts' );

/**
 * Don't split plugin content in the front-end.
 */
function content() {
	remove_filter( 'the_content', array( Plugin_Directory::instance(), 'filter_post_content_to_correct_page' ), 1 );
}
add_action( 'template_redirect', __NAMESPACE__ . '\content' );

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param \WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
}
add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function customize_preview_js() {
	wp_enqueue_script( 'wporg_plugins_customizer', get_template_directory_uri() . '/js/customizer.js', array( 'customize-preview' ), '20151215', true );
}
add_action( 'customize_preview_init',  __NAMESPACE__ . '\customize_preview_js' );


/**
 * Adds hreflang link attributes to plugin pages.
 *
 * @link https://support.google.com/webmasters/answer/189077?hl=en Use hreflang for language and regional URLs.
 * @link https://sites.google.com/site/webmasterhelpforum/en/faq-internationalisation FAQ: Internationalisation.
 */
function hreflang_link_attributes() {
	wp_cache_add_global_groups( array( 'locale-associations' ) );

	if ( false === ( $sites = wp_cache_get( 'local-sites', 'locale-associations' ) ) ) {
		global $wpdb;

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

			// Note that Google only supports ISO 639-1 codes.
			if ( isset( $gp_locale->lang_code_iso_639_1 ) && isset( $gp_locale->country_code ) ) {
				$hreflang = $gp_locale->lang_code_iso_639_1 . '-' . $gp_locale->country_code;
			} elseif ( isset( $gp_locale->lang_code_iso_639_1 ) ) {
				$hreflang = $gp_locale->lang_code_iso_639_1;
			} elseif ( isset( $gp_locale->lang_code_iso_639_2 ) ) {
				$hreflang = $gp_locale->lang_code_iso_639_2;
			} elseif ( isset( $gp_locale->lang_code_iso_639_3 ) ) {
				$hreflang = $gp_locale->lang_code_iso_639_3;
			}

			if ( $hreflang ) {
				$sites[ $site->locale ]->hreflang = strtolower( $hreflang );
			} else {
				unset( $sites[ $site->locale ] );
			}
		}

		// Add en_US to the list of sites.
		$sites['en_US'] = (object) array(
			'locale'    => 'en_US',
			'hreflang'  => 'en',
			'subdomain' => ''
		);

		uasort( $sites, function( $a, $b ) {
			return strcasecmp( $a->hreflang, $b->hreflang );
		} );

		wp_cache_set( 'local-sites', $sites, 'locale-associations' );
	}

	foreach ( $sites as $site ) {
		$url = sprintf(
			'https://%swordpress.org%s',
			$site->subdomain ? "{$site->subdomain}." : '',
			$_SERVER[ 'REQUEST_URI' ]
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
 * Temporary FAQ parser until we have all readmes re-imported.
 *
 * @param $content
 * @param $section_slug
 *
 * @return string
 */
function temporary_faq_parser( $content, $section_slug ) {
	if ( 'faq' !== $section_slug ) {
		return $content;
	}

	if ( strpos( $content, '</dl>' ) ) {
		return $content;
	}

	$lines      = explode( "\n", $content );
	$definition = false;

	$content = "<dl>\n";
	while ( ( $line = array_shift( $lines ) ) !== null ) {
		$trimmed = trim( $line );
		if ( empty( $trimmed ) ) {
			continue;
		}

		if ( 0 === strpos( $trimmed, '<h4>' ) ) {
			if ( $definition ) {
				$content   .= "</dd>\n";
				$definition = false;
			}

			$content .= '<dt aria-expanded="false">' . strip_tags( $line ) . "</dt>\n";
			continue;
		}

		if ( ! $definition ) {
			$content   .= '<dd>' . $line;
			$definition = true;
			continue;
		}

		$content .= "\n" . $line;
	}

	$content .= "</dd>\n</dl>";

	if ( ! strpos( $content, '</dt>' ) ) {
		$content = wp_kses( $content, array(
			'a'          => array(
				'href'  => true,
				'title' => true,
				'rel'   => true,
			),
			'blockquote' => array(
				'cite' => true
			),
			'br'         => true,
			'p'          => true,
			'code'       => true,
			'pre'        => true,
			'em'         => true,
			'strong'     => true,
			'ul'         => true,
			'ol'         => true,
			'li'         => true,
			'h3'         => true,
			'h4'         => true,
		) );
	}

	return $content;
}
add_filter( 'the_content', __NAMESPACE__ . '\temporary_faq_parser', 10, 2 );

/**
 * Bold archive terms are made here.
 *
 * @param string $term The archive term to bold.
 * @return string
 */
function strong_archive_title( $term ) {
	return '<strong>' . $term . '</strong>';
}
add_filter( 'post_type_archive_title', __NAMESPACE__ . '\strong_archive_title' );
add_filter( 'single_term_title',       __NAMESPACE__ . '\strong_archive_title' );
add_filter( 'single_cat_title',        __NAMESPACE__ . '\strong_archive_title' );
add_filter( 'single_tag_title',        __NAMESPACE__ . '\strong_archive_title' );
add_filter( 'get_the_date',            __NAMESPACE__ . '\strong_archive_title' );

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';
