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
	 * @link http://codex.wordpress.org/Function_Reference/add_theme_support#Post_Thumbnails
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
 * Registers the CSS stylesheet files.
 */
function jobswp_register_styles() {
	wp_register_style(
		'open-sans',
		'//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,300,400,600&subset=latin-ext,latin',
		false,
		'20130605'
	);

	wp_register_style(
		'dashicons',
		get_template_directory_uri() . '/css/dashicons.css',
		false,
		filemtime( get_template_directory() . '/css/dashicons.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'jobswp_register_styles', 1 );

/**
 * Enqueue scripts and styles
 */
function jobswp_scripts() {
	wp_enqueue_style( '996-normalize', get_template_directory_uri() . '/css/996/normalize.css' );
	wp_enqueue_style( '996-base',      get_template_directory_uri() . '/css/996/base.css' );
	wp_enqueue_style( '996-grid',      get_template_directory_uri() . '/css/996/grid.css' );
	wp_enqueue_style( '996-style',     get_template_directory_uri() . '/css/996/style.css' );
	wp_enqueue_style( 'dashicons' );
	wp_enqueue_style( 'open-sans' );
	wp_enqueue_style( 'jobswp-style', get_stylesheet_uri() );

	wp_enqueue_script( 'jobswp-navigation', get_template_directory_uri() . '/js/navigation.js', array( 'jquery'), '20131107', true );
	wp_enqueue_script( 'jobswp-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20130115', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	if ( is_singular() && wp_attachment_is_image() ) {
		wp_enqueue_script( 'jobswp-keyboard-image-navigation', get_template_directory_uri() . '/js/keyboard-image-navigation.js', array( 'jquery' ), '20120202' );
	}

	/* Modernizr disbabled because it causes Safari to whitescreen */
//	wp_enqueue_script( 'modernizr',    get_template_directory_uri() . '/996/modernizr-2.6.2.min.js' );
//	wp_enqueue_script( 'modernizr',    get_template_directory_uri() . '/996/modernizr-2.6.2.js' );
}
add_action( 'wp_enqueue_scripts', 'jobswp_scripts' );

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
