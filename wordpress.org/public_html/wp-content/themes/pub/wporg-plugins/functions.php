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
use WordPressdotorg\Plugin_Directory\Template;

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

	add_theme_support( 'wp4-styles' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );

/**
 * Handle the root-level redirect to trailing-slash'd uri which redirect_canonical() usually does.
 */
function enforce_trailing_slash() {
	if ( '/plugins' === $_SERVER['REQUEST_URI'] ) {
		wp_safe_redirect( '/plugins/' );
		die();
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\enforce_trailing_slash' );

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
	wp_enqueue_style( 'wporg-style', get_theme_file_uri( '/css/style.css' ), [ 'dashicons', 'open-sans' ], '20200818' );
	wp_style_add_data( 'wporg-style', 'rtl', 'replace' );

	// Make jQuery a footer script.
	wp_scripts()->add_data( 'jquery', 'group', 1 );
	wp_scripts()->add_data( 'jquery-core', 'group', 1 );
	wp_scripts()->add_data( 'jquery-migrate', 'group', 1 );

	wp_enqueue_script( 'wporg-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20181209', true );
	wp_enqueue_script( 'wporg-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20151215', true );

	if ( is_singular( 'plugin' ) ) {
		wp_enqueue_script( 'wporg-plugins-popover', get_stylesheet_directory_uri() . '/js/popover.js', array( 'jquery' ), '20171002', true );
		wp_enqueue_script( 'wporg-plugins-faq', get_stylesheet_directory_uri() . '/js/section-faq.js', array( 'jquery' ), '20200113', true );
	}

	if ( ! is_404() ) {
		wp_enqueue_script( 'wporg-plugins-locale-banner', get_stylesheet_directory_uri() . '/js/locale-banner.js', array( 'jquery' ), '20200401', true );
		wp_localize_script( 'wporg-plugins-locale-banner', 'wporgLocaleBanner', array(
			'apiURL'        => rest_url( '/plugins/v1/locale-banner' ),
			'currentPlugin' => is_singular( 'plugin' ) ? get_queried_object()->post_name : '',
		) );
	}

	if ( get_query_var( 'plugin_advanced' ) ) {
		wp_enqueue_script( 'google-charts-loader', 'https://www.gstatic.com/charts/loader.js', array(), false, true );
		wp_enqueue_script( 'wporg-plugins-stats', get_stylesheet_directory_uri() . '/js/stats.js', array( 'jquery', 'google-charts-loader' ), '20180713', true );

		wp_localize_script( 'wporg-plugins-stats', 'pluginStats', array(
			'slug' => is_singular( 'plugin' ) ? get_queried_object()->post_name : '',
			'l10n' => array(
				'date'      => __( 'Date', 'wporg-plugins' ),
				'downloads' => __( 'Downloads', 'wporg-plugins' ),
				'growth'    => __( 'Growth', 'wporg-plugins' ),
				'noData'    => __( 'No data yet', 'wporg-plugins' ),
				'today'     => __( 'Today', 'wporg-plugins' ),
				'yesterday' => __( 'Yesterday', 'wporg-plugins' ),
				'last_week' => __( 'Last 7 Days', 'wporg-plugins' ),
				'all_time'  => __( 'All Time', 'wporg-plugins' ),
			),
		) );
	}

	// React is currently only used on detail pages.
	if ( is_single() ) {
		$assets_path = dirname( __FILE__ ) . '/js/build/theme.asset.php';
		if ( file_exists( $assets_path ) ) {
			$script_info = require( $assets_path );
			wp_enqueue_script(
				'wporg-plugins-client',
				get_stylesheet_directory_uri() . '/js/build/theme.js',
				$script_info['dependencies'],
				$script_info['version'],
				true
			);
			wp_localize_script(
				'wporg-plugins-client',
				'localeData',
				array(
					'' => array(
						'Plural-Forms' => _x( 'nplurals=2; plural=n != 1;', 'plural forms', 'wporg-plugins' ),
						'Language'     => _x( 'en', 'language (fr, fr_CA)', 'wporg-plugins' ),
						'localeSlug'   => _x( 'en', 'locale slug', 'wporg-plugins' ),
					),
					'screenshots' => __( 'Screenshots', 'wporg-plugins' ),
				)
			);
		}
	}

	// No Jetpack scripts needed.
	add_filter( 'jetpack_implode_frontend_css', '__return_false' );
	wp_dequeue_script( 'devicepx' );
	wp_register_script( 'grofiles-cards', false );
	wp_enqueue_script( 'grofiles-cards' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts' );

// Disable mentions script in Plugin Directory.
add_filter( 'jetpack_mentions_should_load_ui', '__return_false', 11 );

/**
 * Filters an enqueued script & style's fully-qualified URL.
 *
 * @param string $src    The source URL of the enqueued script/style.
 * @param string $handle The style's registered handle.
 * @return string
 */
function loader_src( $src, $handle ) {
	$cdn_urls = [
		'dashicons',
		'wp-embed',
		'jquery-core',
		'jquery-migrate',
		'wporg-style',
		'wporg-navigation',
		'wporg-skip-link-focus-fix',
		'wporg-plugins-popover',
		'wporg-plugins-locale-banner',
		'wporg-plugins-stats',
		'wporg-plugins-client',
		'wporg-plugins-faq',
	];

	if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
		return $src;
	}

	// Use CDN url.
	if ( in_array( $handle, $cdn_urls, true ) ) {
		$src = str_replace( get_home_url(), 'https://s.w.org', $src );
	}

	// Remove version argument.
	if ( in_array( $handle, [ 'open-sans' ], true ) ) {
		$src = remove_query_arg( 'ver', $src );
	}

	return $src;
}
add_filter( 'style_loader_src', __NAMESPACE__ . '\loader_src', 10, 2 );
add_filter( 'script_loader_src', __NAMESPACE__ . '\loader_src', 10, 2 );

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
 * Filters the list of CSS body classes for the current post or page.
 *
 * @param array $classes An array of body classes.
 * @return array
 */
function custom_body_class( $classes ) {
	$classes[] = 'no-js';
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\custom_body_class' );

/**
 * Append an optimized site name.
 *
 * @param array $title {
 *     The document title parts.
 *
 *     @type string $title   Title of the viewed page.
 *     @type string $page    Optional. Page number if paginated.
 *     @type string $tagline Optional. Site description when on home page.
 *     @type string $site    Optional. Site title when not on home page.
 * }
 * @return array Filtered title parts.
 */
function document_title( $title ) {
	global $wp_query;

	if ( is_front_page() ) {
		$title['title']   = __( 'WordPress Plugins', 'wporg-plugins' );
		$title['tagline'] = __( 'WordPress.org', 'wporg-plugins' );
	} else {
		if ( is_singular( 'plugin' ) ) {
			if ( get_query_var( 'plugin_advanced' ) ) {
				$title['title'] .= ' ' . __( '(advanced view)', 'wporg-plugins' ) . ' - ' . __( 'WordPress plugin', 'wporg-plugins' );
			} else {
				$title['title'] .= ' - ' . __( 'WordPress plugin', 'wporg-plugins' );
			}
		} elseif ( is_tax() ) {
			$title['title'] = sprintf( __( 'Plugins categorized as %s', 'wporg-plugins' ), strtolower( $title['title'] ) );
		}

		// If results are paged and the max number of pages is known.
		if ( is_paged() && $wp_query->max_num_pages ) {
			// translators: 1: current page number, 2: total number of pages
			$title['page'] = sprintf(
				__( 'Page %1$s of %2$s', 'wporg-plugins' ),
				get_query_var( 'paged' ),
				$wp_query->max_num_pages
			);
		}

		$title['site'] = __( 'WordPress.org', 'wporg-plugins' );
	}

	return $title;
}
add_filter( 'document_title_parts', __NAMESPACE__ . '\document_title' );

/**
 * Set the separator for the document title.
 *
 * @return string Document title separator.
 */
function document_title_separator() {
	return ( is_feed() ) ? '&#8212;' : '&#124;';
}
add_filter( 'document_title_separator', __NAMESPACE__ . '\document_title_separator' );

/**
 * Shorten excerpt length on index pages, so plugins cards are all the same height.
 *
 * @param string $excerpt The excerpt.
 * @return string
 */
function excerpt_length( $excerpt ) {
	if ( is_home() || is_archive() ) {
		/*
		 * translators: If your word count is based on single characters (e.g. East Asian characters),
		 * enter 'characters_excluding_spaces' or 'characters_including_spaces'. Otherwise, enter 'words'.
		 * Do not translate into your own language.
		 */
		if ( strpos( _x( 'words', 'Word count type. Do not translate!', 'wporg-plugins' ), 'characters' ) === 0 ) {
			// Use the default limit of 55 characters for East Asian locales.
			$excerpt = wp_trim_words( $excerpt );
		} else {
			// Limit the excerpt to 15 words for other locales.
			$excerpt = wp_trim_words( $excerpt, 15 );
		}
	}

	return $excerpt;
}
add_filter( 'get_the_excerpt', __NAMESPACE__ . '\excerpt_length' );

/**
 * Adds meta tags for richer social media integrations.
 */
function social_meta_data() {
	global $wporg_global_header_options;
	if ( is_front_page() ) {
		$og_fields = [
			'og:title'       => __( 'WordPress Plugins', 'wporg-plugins' ),
			'og:description' => __( 'Choose from thousands of free plugins to build, customize, and enhance your WordPress website.', 'wporg-plugins' ),
			'og:site_name'   => $wporg_global_header_options['rosetta_title'] ?? 'WordPress.org',
			'og:type'        => 'website',
			'og:url'         => home_url(),
		];
		foreach ( $og_fields as $property => $content ) {
			printf(
				'<meta property="%1$s" content="%2$s" />' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
		printf(
			'<meta name="description" content="%1$s" />' . "\n",
			esc_attr( $og_fields['og:description'] )
		);
		return;
	}

	if ( ! is_singular( 'plugin' ) ) {
		return;
	}

	$icon   = Template::get_plugin_icon();
	$banner = Template::get_plugin_banner();

	$banner['banner']    = $banner['banner'] ?? false;
	$banner['banner_2x'] = $banner['banner_2x'] ?? false;

	printf( '<meta property="og:title" content="%s" />' . "\n", the_title_attribute( array( 'echo' => false ) ) );
	printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( strip_tags( get_the_excerpt() ) ) );
	printf( '<meta name="description" content="%s" />' . "\n", esc_attr( strip_tags( get_the_excerpt() ) ) );
	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( $wporg_global_header_options['rosetta_title'] ?? 'WordPress.org' ) );
	printf( '<meta property="og:type" content="website" />' . "\n" );
	printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( get_permalink() ) );
	printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
	printf( '<meta name="twitter:site" content="@WordPress">' . "\n" );

	if ( $banner['banner_2x'] ) {
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $banner['banner_2x'] ) );
	}
	if ( $banner['banner'] ) {
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $banner['banner'] ) );
	}
	if ( ! $icon['generated'] && ( $icon['icon_2x'] || $icon['icon'] ) ) {
		printf( '<meta name="thumbnail" content="%s" />' . "\n", esc_url( $icon['icon_2x'] ?: $icon['icon'] ) );
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\social_meta_data' );

/**
 * Bold archive terms are made here.
 *
 * @param string $term The archive term to bold.
 * @return string
 */
function strong_archive_title( $term ) {
	return '<strong>' . $term . '</strong>';
}
add_action( 'wp_head', function() {
	add_filter( 'post_type_archive_title', __NAMESPACE__ . '\strong_archive_title' );
	add_filter( 'single_term_title', __NAMESPACE__ . '\strong_archive_title' );
	add_filter( 'single_cat_title', __NAMESPACE__ . '\strong_archive_title' );
	add_filter( 'single_tag_title', __NAMESPACE__ . '\strong_archive_title' );
	add_filter( 'get_the_date', __NAMESPACE__ . '\strong_archive_title' );
} );

/**
 * Custom template tags for this theme.
 */
require get_stylesheet_directory() . '/inc/template-tags.php';
