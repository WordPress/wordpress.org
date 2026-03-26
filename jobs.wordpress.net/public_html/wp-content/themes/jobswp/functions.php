<?php
/**
 * jobswp functions and definitions
 *
 * @package jobswp
 */

/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) )
	$content_width = 640; /* pixels */

if ( ! function_exists( 'jobswp_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which runs
 * before the init hook. The init hook is too late for some features, such as indicating
 * support post thumbnails.
 */
function jobswp_setup() {

	/**
	 * Make theme available for translation
	 * Translations can be filed in the /languages/ directory
	 * If you're building a theme based on jobswp, use a find and replace
	 * to change 'jobswp' to the name of your theme in all the template files
	 */
	load_theme_textdomain( 'jobswp', get_template_directory() . '/languages' );

	/**
	 * Add default posts and comments RSS feed links to head
	 */
	add_theme_support( 'automatic-feed-links' );

	/**
	 * Enable support for Post Thumbnails on posts and pages
	 *
	 * @link https://developer.wordpress.org/reference/functions/add_theme_support/#post-thumbnails
	 */
	//add_theme_support( 'post-thumbnails' );

	/**
	 * This theme uses wp_nav_menu() in one location.
	 */
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'jobswp' ),
	) );

	/**
	 * Enable support for Post Formats
	 */
//	add_theme_support( 'post-formats', array( 'aside', 'image', 'video', 'quote', 'link' ) );

	/**
	 * Setup the WordPress core custom background feature.
	 */
	add_theme_support( 'custom-background', apply_filters( 'jobswp_custom_background_args', array(
		'default-color' => 'ffffff',
		'default-image' => '',
	) ) );
}
endif; // jobswp_setup
add_action( 'after_setup_theme', 'jobswp_setup' );

/**
 * Register widgetized area and update sidebar with default widgets
 */
function jobswp_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Sidebar', 'jobswp' ),
		'id'            => 'sidebar-1',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1>',
	) );
}
add_action( 'widgets_init', 'jobswp_widgets_init' );

/**
 * Enqueue scripts and styles
 */
function jobswp_scripts() {
	wp_enqueue_style( 'jobswp-style', get_stylesheet_uri(), array(), '20260326' );

	wp_enqueue_script( 'jobswp-main', get_template_directory_uri() . '/js/main.js', array(), '20260326', true );
	wp_enqueue_script( 'jobswp-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20130115', true );
}
add_action( 'wp_enqueue_scripts', 'jobswp_scripts' );

/**
 * Sets 404 response for author archive requests.
 */
function jobswp_author_archives_404() {
	if ( is_author() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}
}
add_action( 'wp', 'jobswp_author_archives_404' );

/**
 * Outputs `noindex,follow` robots tag for appropriate pages.
 *
 * Currently output for:
 * - empty job category archives
 * - search results
 */
function jobswp_noindex( $robots ) {
	global $wp_query;

	if (
		is_search()
	||
		( is_tax( 'job_category' ) && 0 === $wp_query->found_posts )
	) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}

	return $robots;
}
add_filter( 'wp_robots', 'jobswp_noindex' );

/**
 * Add a body class incorporating page slug.
 *
 * @param string[] $classes An array of body class names.
 * @return string[]
 */
function jobswp_add_page_slug_to_body_class( $classes ) {
	if ( is_page() ) {
		$page = get_post();
		if ( $page ) {
			$classes[] = 'page-' . $page->post_name;
		}
	}

	return $classes;
}
add_filter( 'body_class', 'jobswp_add_page_slug_to_body_class' );

/**
 * Implement the Custom Header feature.
 */
//require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Custom functions that act independently of the theme templates.
 */
require get_template_directory() . '/inc/extras.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
require get_template_directory() . '/inc/jetpack.php';
