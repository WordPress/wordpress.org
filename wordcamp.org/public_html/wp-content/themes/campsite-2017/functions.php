<?php
/**
 * CampSite 2017 functions and definitions
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

add_action( 'after_setup_theme',  __NAMESPACE__ . '\setup_theme'             );
add_action( 'after_setup_theme',  __NAMESPACE__ . '\content_width',        0 );
add_action( 'widgets_init',       __NAMESPACE__ . '\widgets_init'            );
add_action( 'wp_head',            __NAMESPACE__ . '\javascript_detection', 0 );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts'         );

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function setup_theme() {
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'customize-selective-refresh-widgets' );

	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' )
	);

	add_theme_support(
		'custom-background',
		apply_filters( __NAMESPACE__ . '\custom_background_args', array(
			'default-color' => 'ffffff',
			'default-image' => '',
		) )
	);

	register_nav_menus( array(
		'primary'   => esc_html__( 'Primary',   'wordcamporg' ),
		'secondary' => esc_html__( 'Secondary', 'wordcamporg' ),
	) );
}

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function content_width() {
	$GLOBALS['content_width'] = apply_filters( __NAMESPACE__ . '\content_width', 640 );
}

/**
 * Register widget area.
 */
function widgets_init() {
	$miscellaneous_areas = array(
		array(
			'name'        => esc_html__( 'Primary Sidebar', 'wordcamporg' ),
			'id'          => 'sidebar-1',
			'description' => esc_html__( 'Main Widgets Sidebar. Shows up in all pages.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Secondary Sidebar', 'wordcamporg' ),
			'id'          => 'sidebar-2',
			'description' => esc_html__( 'Secondary Widgets Sidebar - shows up in all pages after the Primary Sidebar block.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (All pages except homepage)', 'wordcamporg' ),
			'id'          => 'before-content-1',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, in all pages except the homepage.', 'wordcamporg' ),
		),
	);

	$before_content_home_areas = array(
		array(
			'name'        => esc_html__( 'Before Content (Homepage) Area 1', 'wordcamporg' ),
			'id'          => 'before-content-homepage-1',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the homepage.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (Homepage) Area 2', 'wordcamporg' ),
			'id'          => 'before-content-homepage-2',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the homepage.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (Homepage) Area 3', 'wordcamporg' ),
			'id'          => 'before-content-homepage-3',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the homepage.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (Homepage) Area 4', 'wordcamporg' ),
			'id'          => 'before-content-homepage-4',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the homepage.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (Homepage) Area 5', 'wordcamporg' ),
			'id'          => 'before-content-homepage-5',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the homepage.', 'wordcamporg' ),
		),
	);

	$before_content_day_of_areas = array(
		array(
			'name'        => esc_html__( 'Before Content (Day Of) Area 1', 'wordcamporg' ),
			'id'          => 'before-content-day-of-1',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the day-of.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (Day Of) Area 2', 'wordcamporg' ),
			'id'          => 'before-content-day-of-2',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the day-of.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (Day Of) Area 3', 'wordcamporg' ),
			'id'          => 'before-content-day-of-3',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the day-of.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (Day Of) Area 4', 'wordcamporg' ),
			'id'          => 'before-content-day-of-4',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the day-of.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Before Content (Day Of) Area 5', 'wordcamporg' ),
			'id'          => 'before-content-day-of-5',
			'description' => esc_html__( 'Will show a widgets area, inside the #content block, before all the content, only on the day-of.', 'wordcamporg' ),
		),
	);

	$header_areas = array(
		array(
			'name'        => esc_html__( 'Header Widget Area 1', 'wordcamporg' ),
			'id'          => 'header-1',
			'description' => esc_html__( 'Will Show a widgets area on the header - can be combined with other Header Widget Area blocks.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Header Widget Area 2', 'wordcamporg' ),
			'id'          => 'header-2',
			'description' => esc_html__( 'Will Show a widgets area on the header - can be combined with other Header Widget Area blocks.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Header Widget Area 3', 'wordcamporg' ),
			'id'          => 'header-3',
			'description' => esc_html__( 'Will Show a widgets area on the header - can be combined with other Header Widget Area blocks.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Header Widget Area 4', 'wordcamporg' ),
			'id'          => 'header-4',
			'description' => esc_html__( 'Will Show a widgets area on the header - can be combined with other Header Widget Area blocks.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Header Widget Area 5', 'wordcamporg' ),
			'id'          => 'header-5',
			'description' => esc_html__( 'Will Show a widgets area on the header - can be combined with other Header Widget Area blocks.', 'wordcamporg' ),
		),
	);

	$footer_areas = array(
		array(
			'name'        => esc_html__( 'Footer Widget Area 1', 'wordcamporg' ),
			'id'          => 'footer-1',
			'description' => esc_html__( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Footer Widget Area 2', 'wordcamporg' ),
			'id'          => 'footer-2',
			'description' => esc_html__( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Footer Widget Area 3', 'wordcamporg' ),
			'id'          => 'footer-3',
			'description' => esc_html__( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Footer Widget Area 4', 'wordcamporg' ),
			'id'          => 'footer-4',
			'description' => esc_html__( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		),

		array(
			'name'        => esc_html__( 'Footer Widget Area 5', 'wordcamporg' ),
			'id'          => 'footer-5',
			'description' => esc_html__( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		),
	);

	$widget_areas = array_merge( $miscellaneous_areas, $before_content_home_areas, $before_content_day_of_areas, $before_content_home_areas, $header_areas, $footer_areas );

	foreach ( $widget_areas as $widget_area ) {
		$args = array_merge( array(
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		), $widget_area );

		register_sidebar( $args );
	}
}

/**
 * Handles JavaScript detection.
 *
 * Adds a `js` class to the root `<html>` element when JavaScript is detected.
 */
function javascript_detection() {
	?>

	<script>
		(function( html ) {
			html.className = html.className.replace( /\bno-js\b/, 'js' );
		})(document.documentElement);
	</script>

	<?php
}

/**
 * Enqueue scripts and styles.
 */
function enqueue_scripts() {
	$campsite_2017_l10n = array(
		'quote' => get_svg( array( 'icon' => 'quote-right' ) ),
	);

	wp_enqueue_style( 'campsite-2017-style', get_stylesheet_uri() );

	wp_enqueue_script(
		'campsite-2017-skip-link-focus-fix',
		get_theme_file_uri( '/js/skip-link-focus-fix.js' ),
		array(),
		1,
		true
	);

	wp_enqueue_script(
		'campsite-2017-global',
		get_theme_file_uri( '/js/global.js' ),
		array( 'jquery' ),
		1,
		true
	);

	if ( has_nav_menu( 'primary' ) || has_nav_menu( 'secondary' ) ) {
		wp_enqueue_script(
			'campsite-2017-navigation',
			get_theme_file_uri( '/js/navigation.js' ),
			array(),
			1,
			true
		);

		$campsite_2017n_l10n['expand']   = __( 'Expand child menu',   'wordcamporg' );
		$campsite_2017n_l10n['collapse'] = __( 'Collapse child menu', 'wordcamporg' );
		$campsite_2017n_l10n['icon']     = get_svg( array( 'icon' => 'angle-down', 'fallback' => true ) );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	wp_localize_script(
		'campsite-2017-skip-link-focus-fix',
		'campsiteScreenReaderText',
		$campsite_2017_l10n
	);
}

require_once( get_theme_file_path( '/inc/custom-header.php'  ) );
require_once( get_theme_file_path( '/inc/template-tags.php'  ) );
require_once( get_theme_file_path( '/inc/extras.php'         ) );
require_once( get_theme_file_path( '/inc/customizer.php'     ) );
require_once( get_theme_file_path( '/inc/jetpack.php'        ) );
require_once( get_theme_file_path( '/inc/icon-functions.php' ) );
