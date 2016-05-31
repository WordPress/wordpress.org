<?php
/**
 * WCBS functions and definitions
 *
 * @package WCBS
 * @since WCBS 1.0
 */

/**
 * The following will enable functions, classes an a 
 * bunch of other WordCamp related stuff.
 */
require_once "lib/utils/functions.php";
wcb_maybe_define( 'WCB_DIR', dirname( __FILE__ ) );
wcb_maybe_define( 'WCB_URL', get_template_directory_uri() );
require_once "lib/class-wcb-manager.php";

// Start writing below this line.

/**
 * Set the content width based on the theme's design and stylesheet.
 *
 * @since WCBS 1.0
 */
if ( ! isset( $content_width ) )
	$content_width = 640; /* pixels */

if ( ! function_exists( 'wcbs_setup' ) ):
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which runs
 * before the init hook. The init hook is too late for some features, such as indicating
 * support post thumbnails.
 *
 * @since WCBS 1.0
 */
function wcbs_setup() {

	/**
	 * Custom template tags for this theme.
	 */
	require( get_template_directory() . '/inc/template-tags.php' );

	/**
	 * Custom functions that act independently of the theme templates
	 */
	//require( get_template_directory() . '/inc/tweaks.php' );

	/**
	 * Custom Theme Options
	 */
	//require( get_template_directory() . '/inc/theme-options/theme-options.php' );

	/**
	 * WordPress.com-specific functions and definitions
	 */
	//require( get_template_directory() . '/inc/wpcom.php' );

	/**
	 * Add default posts and comments RSS feed links to head
	 */
	add_theme_support( 'automatic-feed-links' );

	/**
	 * Enable support for Post Thumbnails
	 */
	add_theme_support( 'post-thumbnails' );

	/**
	 * This theme uses wp_nav_menu() in one location.
	 */
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'wordcamporg' ),
	) );

	/**
	 * Add support for the Aside Post Formats
	 */
	add_theme_support( 'post-formats', array( 'aside', ) );
}
endif; // wcbs_setup
add_action( 'after_setup_theme', 'wcbs_setup' );

/**
 * Register widgetized area and update sidebar with default widgets
 *
 * @since WCBS 1.0
 */
function wcbs_widgets_init() {

	// Generic main Sidebar Widget Area - Will show in all pages. Will load default content.
	register_sidebar( array(
		'name' => __( 'Primary Sidebar', 'wordcamporg' ),
		'id' => 'sidebar-1',
		'description'   => __( 'Main Widgets Sidebar. Shows up in all pages.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	// Generic main Sidebar Widget Area - Will show in all pages. Empty by default.
	register_sidebar( array(
		'name' => __( 'Secondary Sidebar', 'wordcamporg' ),
		'id' => 'sidebar-2',
		'description'   => __( 'Secondary Widgets Sidebar - shows up in all pages after the Primary Sidebar block.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	
	// After Header Widget Area - located after the #masthead header block. Will show in all pages except the homepage. Empty by default.
	register_sidebar( array(
		'name' => __( 'After Header', 'wordcamporg' ),
		'id' => 'after-header',
		'description'   => __( 'Will show a widgets area, after the #masthead header, in all pages except the homepage.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	// After Header Widget Area for the Homepage - located after the #masthead header block. Will show only on the homepage. Empty by default.
	register_sidebar( array(
		'name' => __( 'After Header (Homepage)', 'wordcamporg' ),
		'id' => 'after-header-homepage',
		'description'   => __( 'Will show a widgets area, after the #masthead header, only on the homepage.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	
	// Before Content Widget Area - located inside the #main block, before any other content. Will show in all pages except the homepage. Empty by default.
	register_sidebar( array(
		'name' => __( 'Before Content', 'wordcamporg' ),
		'id' => 'before-content',
		'description'   => __( 'Will show a widgets area, inside the #main block, before all the content, in all pages except the homepage.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	// Before Content Widget Area for the Homepage - located inside the #main block, before any other content. Will show only on the homepage. Empty by default.
	register_sidebar( array(
		'name' => __( 'Before Content (Homepage)', 'wordcamporg' ),
		'id' => 'before-content-homepage',
		'description'   => __( 'Will show a widgets area, inside the #main block, before all the content, only on the homepage.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	
	// Footer Widget Areas - Will show in all pages. Empty by default. (When activated, there will be a wrapper block around all the Footer widget areas)
	
	// Footer Widget Area 1
	register_sidebar( array(
		'name' => __( 'Footer Widget Area 1', 'wordcamporg' ),
		'id' => 'footer-1',
		'description'   => __( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	// Footer Widget Area 2
	register_sidebar( array(
		'name' => __( 'Footer Widget Area 2', 'wordcamporg' ),
		'id' => 'footer-2',
		'description'   => __( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	// Footer Widget Area 3
	register_sidebar( array(
		'name' => __( 'Footer Widget Area 3', 'wordcamporg' ),
		'id' => 'footer-3',
		'description'   => __( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	// Footer Widget Area 4
	register_sidebar( array(
		'name' => __( 'Footer Widget Area 4', 'wordcamporg' ),
		'id' => 'footer-4',
		'description'   => __( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
	// Footer Widget Area 5
	register_sidebar( array(
		'name' => __( 'Footer Widget Area 5', 'wordcamporg' ),
		'id' => 'footer-5',
		'description'   => __( 'Will Show a widgets area on the footer - can be combined with other Footer Widget Area blocks.', 'wordcamporg' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h1 class="widget-title">',
		'after_title' => '</h1>',
	) );
}
add_action( 'widgets_init', 'wcbs_widgets_init' );

/**
 * Enqueue scripts and styles
 */
function wcbs_scripts() {
	global $post;

	wp_enqueue_style( 'style', get_stylesheet_uri() );
	wp_enqueue_script( 'small-menu', get_template_directory_uri() . '/js/small-menu.js', array( 'jquery' ), '20120206', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	if ( is_singular() && wp_attachment_is_image( $post->ID ) ) {
		wp_enqueue_script( 'keyboard-image-navigation', get_template_directory_uri() . '/js/keyboard-image-navigation.js', array( 'jquery' ), '20120202' );
	}
}
add_action( 'wp_enqueue_scripts', 'wcbs_scripts' );

/**
 * Implement the Custom Header feature
 */
//require( get_template_directory() . '/inc/custom-header.php' );
