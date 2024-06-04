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


// Block Files
require_once( __DIR__ . '/src/blocks/archive-page/index.php' );
require_once( __DIR__ . '/src/blocks/category-navigation/index.php' );
require_once( __DIR__ . '/src/blocks/filter-bar/index.php' );
require_once( __DIR__ . '/src/blocks/front-page/index.php' );
require_once( __DIR__ . '/src/blocks/search-page/index.php' );
require_once( __DIR__ . '/src/blocks/single-plugin/index.php' );
require_once( __DIR__ . '/src/blocks/plugin-card/index.php' );

// Block Configs
require_once( __DIR__ . '/inc/block-config.php' );

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

	// Add support for WordPress generated <title> tags.
	add_theme_support( 'title-tag' );

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
	$GLOBALS['content_width'] = apply_filters( 'wporg_plugins_content_width', 750 );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\content_width', 0 );

/**
 * Enqueue scripts and styles.
 */
function scripts() {
	wp_enqueue_style( 'wporg-style', get_theme_file_uri( '/css/style.css' ), [ 'dashicons', 'open-sans' ], filemtime( __DIR__ . '/css/style.css' ) );
	wp_style_add_data( 'wporg-style', 'rtl', 'replace' );

	wp_enqueue_style( 'wporg-parent-2021-style', get_theme_root_uri() . '/wporg-parent-2021/build/style.css', [ 'wporg-global-fonts' ] );
	wp_enqueue_style( 'wporg-parent-2021-block-styles', get_theme_root_uri() . '/wporg-parent-2021/build/block-styles.css', [ 'wporg-global-fonts' ] );

	// Make jQuery a footer script.
	wp_scripts()->add_data( 'jquery', 'group', 1 );
	wp_scripts()->add_data( 'jquery-core', 'group', 1 );
	wp_scripts()->add_data( 'jquery-migrate', 'group', 1 );

	wp_enqueue_script( 'wporg-navigation', get_stylesheet_directory_uri() . '/js/navigation.js', array(), '20181209', true );
	wp_enqueue_script( 'wporg-skip-link-focus-fix', get_stylesheet_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20151215', true );

	if ( is_singular( 'plugin' ) ) {
		wp_enqueue_script( 'wporg-plugins-popover', get_stylesheet_directory_uri() . '/js/popover.js', array( 'jquery' ), '20171002', true );
		wp_enqueue_script( 'wporg-plugins-faq', get_stylesheet_directory_uri() . '/js/section-faq.js', array( 'jquery' ), filemtime( __DIR__ . '/js/section-faq.js' ), true );

		$post = get_post();
		if ( $post && current_user_can( 'plugin_admin_edit', $post ) ) {
			wp_enqueue_script( 'wporg-plugins-categorization', get_stylesheet_directory_uri() . '/js/section-categorization.js', array( 'jquery' ), filemtime( __DIR__ . '/js/section-categorization.js' ), true );
			wp_localize_script( 'wporg-plugins-categorization', 'categorizationOptions', [
				'restUrl'    => get_rest_url(),
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
				'pluginSlug' => $post->post_name,
			] );
		}
	}

	if ( get_query_var( 'plugin_advanced' ) ) {
		wp_enqueue_script( 'google-charts-loader', 'https://www.gstatic.com/charts/loader.js', array(), false, true );
		wp_enqueue_script( 'wporg-plugins-stats', get_stylesheet_directory_uri() . '/js/stats.js', array( 'jquery', 'google-charts-loader' ), '20220929', true );

		wp_localize_script( 'wporg-plugins-stats', 'pluginStats', array(
			'slug' => is_singular( 'plugin' ) ? get_queried_object()->post_name : '',
			'l10n' => array(
				'date'      => __( 'Date', 'wporg-plugins' ),
				'downloads' => __( 'Downloads', 'wporg-plugins' ),
				'noData'    => __( 'No data yet', 'wporg-plugins' ),
				'today'     => __( 'Today', 'wporg-plugins' ),
				'yesterday' => __( 'Yesterday', 'wporg-plugins' ),
				'last_week' => __( 'Last 7 Days', 'wporg-plugins' ),
				'all_time'  => __( 'All Time', 'wporg-plugins' ),
			),
		) );
	}

	// The plugin submission page: /developers/add/
	if ( is_page( 'add' ) ) {
		wp_enqueue_script( 'wporg-plugins-upload', get_stylesheet_directory_uri() . '/js/upload.js', array( 'wp-api', 'jquery' ), filemtime( __DIR__ . '/js/upload.js' ), true );
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
 * Filters the list of CSS body classes for the current post or page.
 *
 * @param array $classes An array of body classes.
 * @return array
 */
function custom_body_class( $classes ) {
	$post = get_post();	

	$classes[] = 'no-js';

	if ( $post && is_singular( 'plugin' ) ) {
		if ( has_term( 'commercial', 'plugin_business_model', $post ) ) {
			$classes[] = 'is-commercial-plugin';
		}

		if ( has_term( 'community', 'plugin_business_model', $post ) ) {
			$classes[] = 'is-community-plugin';
		}
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\custom_body_class' );

/**
 * Swaps out the no-js for the js body class if the browser supports Javascript.
 */
function nojs_body_tag() {
	echo "<script>document.body.className = document.body.className.replace('no-js','js');</script>\n";
}
add_action( 'wp_body_open', __NAMESPACE__ . '\nojs_body_tag' );

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
 * Adds meta tags for richer social media integrations.
 */
function social_meta_data() {
	$site_title = function_exists( '\WordPressdotorg\site_brand' ) ? \WordPressdotorg\site_brand() : 'WordPress.org';

	if ( is_front_page() ) {
		$og_fields = [
			'og:title'       => __( 'WordPress Plugins', 'wporg-plugins' ),
			'og:description' => __( 'Choose from thousands of free plugins to build, customize, and enhance your WordPress website.', 'wporg-plugins' ),
			'og:site_name'   => $site_title,
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
	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( $site_title ) );
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
 * Filter the archive title to use custom string for business model.
 *
 * @param string $title Archive title to be displayed.
 * @return string Updated title.
 */
function update_archive_title( $title ) {
	if ( is_tax( 'plugin_business_model', 'community' ) ) {
		return __( 'Community plugins', 'wporg-plugins' );
	} else if ( is_tax( 'plugin_business_model', 'commercial' ) ) {
		return __( 'Commercial plugins', 'wporg-plugins' );
	}

	return $title;
}
add_filter( 'get_the_archive_title', __NAMESPACE__ . '\update_archive_title' );

/**
 * Filter the archive description to use custom string for business model.
 *
 * @param string $description Archive description to be displayed.
 * @return string Updated description.
 */
function update_archive_description( $description ) {
	$contents = $description;

	// The description in the DB has <p> tags. Add them manually for consistency.
	if ( is_tax( 'plugin_business_model', 'community' ) ) {
		$contents = '<p>' . __( 'These plugins are developed and supported by a community.', 'wporg-plugins' ) . '</p>';
	} else if ( is_tax( 'plugin_business_model', 'commercial' ) ) {
		$contents = '<p>' . __( 'These plugins are free, but also have paid versions available.', 'wporg-plugins' ) . '</p>';
	}

	return sprintf(
		'<div class="section-intro">%s</div>',
		$contents
	);
}
add_filter( 'get_the_archive_description', __NAMESPACE__ . '\update_archive_description' );

/**
 * Custom template tags for this theme.
 */
require get_stylesheet_directory() . '/inc/template-tags.php';
