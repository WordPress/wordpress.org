<?php
/**
 * wpmobileapps functions and definitions
 *
 * @package wpmobileapps
 */

/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 720; /* pixels */
}

if ( ! function_exists( 'wpmobileapps_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function wpmobileapps_setup() {

	/*
	 * Make theme available for translation.
	 * Translations can be filed in the /languages/ directory.
	 * If you're building a theme based on wpmobileapps, use a find and replace
	 * to change 'wpmobileapps' to the name of your theme in all the template files
	 */
	load_theme_textdomain( 'wpmobileapps', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
	 * Enable support for Post Thumbnails on posts and pages.
	 *
	 * @link http://codex.wordpress.org/Function_Reference/add_theme_support#Post_Thumbnails
	 */
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in two locations.
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'wpmobileapps' ),
		'social'  => __( 'Social Menu', 'wpmobileapps' ),
	) );

	// Enable support for Post Formats.
	add_theme_support( 'post-formats', array( 'aside', 'image', 'video', 'quote', 'link' ) );

	// Enable support for HTML5 markup.
	add_theme_support( 'html5', array(
		'comment-list',
		'search-form',
		'comment-form',
		'gallery',
	) );
}
endif; // wpmobileapps_setup
add_action( 'after_setup_theme', 'wpmobileapps_setup' );

/**
 * Register widgetized area and update sidebar with default widgets.
 */
function wpmobileapps_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Footer Widgets 1', 'wpmobileapps' ),
		'id'            => 'sidebar-1',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1>',
	) );
	register_sidebar( array(
		'name'          => __( 'Footer Widgets 2', 'wpmobileapps' ),
		'id'            => 'sidebar-2',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1>',
	) );
}
add_action( 'widgets_init', 'wpmobileapps_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function wpmobileapps_scripts() {
	// Main stylesheet.
	wp_enqueue_style( 'wpmobileapps-style', get_stylesheet_uri() );

	// Animations.
//	wp_enqueue_style( 'wpmobileapps-animations-1', get_template_directory_uri() . '/animate.css' );
//	wp_enqueue_style( 'wpmobileapps-animations-2', get_template_directory_uri() . '/animations.css' );

	// Google Fonts.
	wp_enqueue_style( 'wpmobileapps-fonts', "//fonts.googleapis.com/css?family=Merriweather:300,700|Open+Sans:300,400,700" );

	// Genericons.
	wp_enqueue_style( 'wpmobileapps-genericons', get_template_directory_uri() . '/genericons.css' );

	// Skip Link.
	wp_enqueue_script( 'wpmobileapps-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20130115', true );

	// Theme specific scripts.
	wp_enqueue_script( 'wpmobileapps-waypoints', get_template_directory_uri() . '/js/waypoints.min.js', array( 'jquery' ), '20140528', true );
	wp_enqueue_script( 'wpmobileapps-scripts', get_template_directory_uri() . '/js/scripts.js', array( 'jquery' ), '20140528', true );
	wp_enqueue_script( 'wpmobileapps-home', get_template_directory_uri() . '/js/home.js', array( 'jquery' ), '20140528', true );
	wp_enqueue_script( 'wpmobileapps-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20140528', true );

	// Post highlighting for the blog, archive and search results.
	if ( ( is_home() || is_archive() || is_search() ) ) {
		wp_enqueue_script( 'wpmobileapps-post-highlighting', get_template_directory_uri() . '/js/post-highlighting.js', array( 'jquery' ), '20140528', true );
	}

	// Threaded comments.
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'wpmobileapps_scripts' );

/**
 * Shortcode that creates a download button for an app an a specific platform.
 */
function wpmobileapps_download_button( $atts , $content = null ) {
	if ( empty( $atts['link'] ) || empty( $atts['platform'] ) ) {
		return;
	}

	return sprintf(
		'<a href="%s" class="button button-white button-hero button-download %s">%s</a>',
		esc_url( $atts['link'] ),
		esc_attr( $atts['platform'] ),
		$content
	);
}
add_shortcode( 'wpmobileapps_download_button', 'wpmobileapps_download_button' );

/**
 * Post class filter.
 */
function wpmobileapps_post_class( $classes ) {
	// Add a class to the first post of the blog home. Make sure this also works when Infinite Scroll is enabled.
	if( is_home() && ! ( class_exists( 'The_Neverending_Home_Page' ) && The_Neverending_Home_Page::got_infinity() ) ) {
		global $wp_query;

		if( $wp_query->current_post == 0 ) {
			$classes[] = 'first-post';
		}
	}

	// Add a class to the page that has the Grid Page template applied.
	// is_page_template() can not be used here, since it will apply to the grid pages as well.
	global $post;
	if ( 'grid-page.php' == get_page_template_slug( $post->ID ) ) {
		$classes[] = 'grid-page';
	}
	return $classes;
}
add_filter( 'post_class', 'wpmobileapps_post_class' );

/**
 * Body class filter.
 */
function wpmobileapps_body_class( $classes ) {
	if ( is_home() || is_archive() || is_search() ) {
		$classes[] = 'post-listing';
	}

	return $classes;
}
add_filter( 'body_class', 'wpmobileapps_body_class' );

/**
 * Change the more link text for excerpts.
 */
function wpmobileapps_excerpt_more( $more ) {
	return '&hellip; <a class="continue-reading" href="'. get_permalink( get_the_ID() ) . '">' . __( 'Continue reading&nbsp;<span class="meta-nav">&rarr;</span>', 'wpmobileapps' ) . '</a>';
}
add_filter( 'excerpt_more', 'wpmobileapps_excerpt_more' );

/**
 * Change the excerpt length.
 */
function wpmobileapps_excerpt_length( $length ) {
	return 25;
}
add_filter( 'excerpt_length', 'wpmobileapps_excerpt_length' );

/**
 * Remove Jetpack Likes on the grid page template.
 */
function wpmobileapps_remove_likes() {
	if ( 'grid-page.php' != get_page_template_slug( get_the_ID() ) ) {
		return;
	}

	if ( class_exists( 'Jetpack_Likes' ) ) {
		remove_filter( 'post_flair', array( Jetpack_Likes::init(), 'post_likes' ), 30, 1 );
	}
}
add_action( 'loop_start', 'wpmobileapps_remove_likes' );

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

/**
 * Expose meta boxes
 */
function register_wpma_meta_boxes() {
	add_meta_box( 'wpma_custom_meta', __( 'Custom Meta', 'wpmobileapps' ), 'wpma_meta_boxes', 'page', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'register_wpma_meta_boxes' );

function wpma_meta_boxes( $post, $meta_box ) {
	$features_animation = get_post_meta( $post->ID, 'features_animation', true );
	$post_subtitle = get_post_meta( $post->ID, 'post_subtitle', true );

	wp_nonce_field( 'wpma-custom-meta', 'wpma_custom_meta_nonce' );
?>
<label for="wpma-post-subtitle-<?php echo (int) $post->ID; ?>" class="">Post Subtitle</label><br>
<input type="text" id="wpma-post-subtitle-<?php echo (int) $post->ID; ?>" class="widefat" name="post_subtitle" value="<?php echo esc_attr( $post_subtitle ); ?>" /><br><br>

<label for="wpma-features-animation-<?php echo (int) $post->ID; ?>" class="">Features Animation</label><br>
<textarea id="wpma-features-animation-<?php echo (int) $post->ID; ?>" class="widefat" name="features_animation" ><?php echo esc_html( $features_animation ); ?></textarea><br><br>
<?php
}

function save_wpma_meta_information( $post_id, $post ) {
	// check for nonce and verify this came from the our screen and with proper authorization
	if ( ! isset( $_POST['wpma_custom_meta_nonce'] ) )
    	return $post_id;

    if ( ! wp_verify_nonce( $_POST['wpma_custom_meta_nonce'], 'wpma-custom-meta' ) )
		return $post_id;

	// is the user allowed to edit the post or page
	if ( ! current_user_can( 'edit_post', $post_id ) )
		return $post_id;

	// Don't store custom data twice
	if ( $post->post_type == 'revision' )
		return $post_id;

	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return $post_id;

	// get data from $_POST
	if ( ! isset( $_POST['post_subtitle'] ) && ! isset( $_POST['features_animation'] ) )
		return $post_id;

	if ( isset( $_POST['post_subtitle'] ) )
		update_post_meta( $post_id, 'post_subtitle', $_POST['post_subtitle'] );

	if ( isset( $_POST['features_animation'] ) )
		update_post_meta( $post_id, 'features_animation', $_POST['features_animation'] );
}
add_action( 'save_post', 'save_wpma_meta_information', 1, 2 );

