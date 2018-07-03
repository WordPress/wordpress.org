<?php
/**
 * WordPress.org functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPressdotorg\Theme\Main
 */

namespace WordPressdotorg\MainTheme;

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function setup() {
	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'rosetta_main' => esc_html__( 'Rosetta', 'wporg' ),
	) );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );

/**
 * Registers theme-specific widgets.
 */
function widgets() {
	include_once get_stylesheet_directory() . '/widgets/class-wporg-widget-download.php';

	register_widget( __NAMESPACE__ . '\WPORG_Widget_Download' );
	register_widget( 'WP_Widget_Links' );

	add_filter( 'widget_links_args', function( $args ) {
		$args['categorize'] = 0;
		$args['title_li']   = __( 'Resources', 'wporg' );

		return $args;
	} );

	add_filter( 'widget_categories_args', function( $args ) {
		$args['number']  = 10;
		$args['orderby'] = 'count';
		$args['order']   = 'DESC';

		return $args;
	} );

	add_filter( 'widget_archives_args', function( $args ) {
		$args['limit'] = 12;

		return $args;
	} );

	register_sidebar( [
		'id'            => 'sidebar-1',
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4>',
		'after_title'   => '</h4>',
	] );
}
add_action( 'widgets_init', __NAMESPACE__ . '\widgets' );

/**
 * Enqueue scripts and styles.
 */
function scripts() {
	wp_enqueue_style( 'wporg-style', get_theme_file_uri( '/css/style.css' ), [ 'dashicons', 'open-sans' ], '20180702a' );
	wp_style_add_data( 'wporg-style', 'rtl', 'replace' );

	if ( is_page( 'stats' ) ) {
		wp_enqueue_script( 'google-charts', 'https://www.gstatic.com/charts/loader.js', [], null, true );
		wp_enqueue_script( 'wporg-page-stats', get_theme_file_uri( '/js/page-stats.js' ), [ 'jquery', 'google-charts' ], 1, true );
		wp_localize_script( 'wporg-page-stats', 'wporgPageStats', [
			'trunk'         => number_format( WP_CORE_STABLE_BRANCH + 0.1, 1 ), /* trunk */
			'beta'          => number_format( WP_CORE_STABLE_BRANCH + 0.2, 1 ), /* trunk w/ beta-tester plugin */
			'wpVersions'    => __( 'WordPress Version', 'wporg' ),
			'phpVersions'   => __( 'PHP Versions', 'wporg' ),
			'mysqlVersions' => __( 'MySQL Version', 'wporg' ),
			'locales'       => __( 'Locales', 'wporg' ),
		] );
	}

	if ( is_page() && get_queried_object()->post_parent ) {
		wp_enqueue_script( 'wporg-navigation', get_theme_file_uri( '/js/navigation.js' ), [], '20151215', true );
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts' );

/**
 * Filters an enqueued script's fully-qualified URL.
 *
 * @param string $src    The source URL of the enqueued script.
 * @param string $handle The script's registered handle.
 * @return string
 */
function script_src( $src, $handle ) {
	$cdn_handles = [
		'wporg-page-stats',
		'wporg-navigation',
	];

	if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
		return $src;
	}

	// Use CDN url.
	if ( in_array( $handle, $cdn_handles, true ) ) {
		$src = str_replace( get_home_url(), 'https://s.w.org', $src );
	}

	return $src;
}
add_filter( 'script_loader_src', __NAMESPACE__ . '\script_src', 10, 2 );

/**
 * Extend the default WordPress body classes.
 *
 * Adds classes to make it easier to target specific pages.
 *
 * @param array $classes Body classes.
 * @return array
 */
function body_class( $classes ) {
	if ( is_page() ) {
		$page = get_queried_object();

		$classes[] = 'page-' . $page->post_name;

		if ( $page->post_parent ) {
			$parent = get_post( $page->post_parent );

			$classes[] = 'page-parent-' . $parent->post_name;
		}
	}

	return array_unique( $classes );
}
add_filter( 'body_class', __NAMESPACE__ . '\body_class' );

/**
 * Adds child-page-specific template name to page template hierarchy.
 *
 * @param array $templates A list of template candidates, in descending order of priority.
 * @return array List of filtered template candidates.
 */
function child_page_templates( $templates ) {
	$page = get_queried_object();

	if ( $page->post_parent ) {
		$parent = get_post( $page->post_parent );

		// We want it before page-{page_name}.php but after {Page Template}.php.
		$page_name_index = array_search( "page-{$page->post_name}.php", $templates, true );
		$top             = array_slice( $templates, 0, $page_name_index );
		$bottom          = array_slice( $templates, $page_name_index );

		$templates = array_merge( $top, [ "page-{$parent->post_name}-{$page->post_name}.php" ], $bottom );
	}

	return $templates;
}
add_filter( 'page_template_hierarchy', __NAMESPACE__ . '\child_page_templates' );

/**
 * Passes SEO-optimized title and description to embeds.
 */
function use_opengraph_data_for_embed_template() {
	global $post;

	if ( 'page' !== $post->post_type || ! $post->page_template || 'default' === $post->page_template ) {
		return;
	}

	$meta = custom_open_graph_tags();
	if ( $meta ) {
		add_filter( 'the_title', function( $title ) use ( $meta ) {
			return $meta['og:title'] ?? $title;
		} );
		add_filter( 'the_content', function( $content ) use ( $meta ) {
			return $meta['og:description'] ?? $content;
		} );
	}
}
add_action( 'embed_head', __NAMESPACE__ . '\use_opengraph_data_for_embed_template' );

/**
 * Customizes the parent page title when rendering as a site title on child pages.
 *
 * Example: 'About' on all child pages of the About page.
 *
 * @param string $title   Post Title.
 * @param int    $post_id Post ID.
 * @return string
 */
function parent_page_title( $title, $post_id ) {
	$title_post = get_post( $post_id );
	if ( is_page() && 'about' === $title_post->post_name && get_post()->post_name !== $title_post->post_name ) {
		$title = esc_html_x( 'About', 'Page title', 'wporg' );
	}

	return $title;
}
add_filter( 'the_title', __NAMESPACE__ . '\parent_page_title', 11, 2 );

/**
 * Custom template tags.
 */
require_once __DIR__ . '/inc/template-tags.php';

/**
 * Custom meta descriptions for page templates.
 */
require_once __DIR__ . '/inc/page-meta-descriptions.php';

/**
 * Include reCAPTCHA functions for privacy requests.
 */
require_once __DIR__ . '/inc/recaptcha.php';

/**
 * Include the Privacy request functions.
 */
require_once __DIR__ . '/inc/privacy-functions.php';
