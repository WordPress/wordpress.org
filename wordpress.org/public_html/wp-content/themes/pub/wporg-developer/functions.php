<?php

namespace DevHub;

/**
 * Registrations (post type, taxonomies, etc).
 */
require __DIR__ . '/inc/registrations.php';

/**
 * Custom template tags for this theme.
 */
require __DIR__ . '/inc/template-tags.php';

/**
 * Custom functions that act independently of the theme templates.
 */
require __DIR__ . '/inc/extras.php';

/**
 * Customizer additions.
 */
require __DIR__ . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
require __DIR__ . '/inc/jetpack.php';

/**
 * Class for editing parsed content on the Function, Class, Hook, and Method screens.
 */
require_once( __DIR__ . '/inc/parsed-content.php' );

if ( ! function_exists( 'loop_pagination' ) ) {
	require __DIR__ . '/inc/loop-pagination.php';
}

if ( ! function_exists( 'breadcrumb_trail' ) ) {
	require __DIR__ . '/inc/breadcrumb-trail.php';
}

/**
 * User-submitted content (comments, notes, etc).
 */
require __DIR__ . '/inc/user-content.php';

/**
 * Voting for user-submitted content.
 */
require __DIR__ . '/inc/user-content-voting.php';

/**
 * Explanations for functions. hooks, classes, and methods.
 */
require( __DIR__ . '/inc/explanations.php' );

/**
 * Handbooks.
 */
require __DIR__ . '/inc/handbooks.php';

/**
 * Redirects.
 */
require __DIR__ . '/inc/redirects.php';

/**
 * Content formatting.
 */
require __DIR__ . '/inc/formatting.php';

/**
 * Autocomplete.
 */
require __DIR__ . '/inc/autocomplete.php';

/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 640; /* pixels */
}


add_action( 'init', __NAMESPACE__ . '\\init' );
add_action( 'widgets_init', __NAMESPACE__ . '\\widgets_init' );

function init() {

	register_nav_menus();

	add_action( 'pre_get_posts', __NAMESPACE__ . '\\pre_get_posts' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\theme_scripts_styles' );
	add_action( 'wp_head', __NAMESPACE__ . '\\header_js' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\rename_comments_meta_box', 10, 2 );

	add_filter( 'post_type_link', __NAMESPACE__ . '\\method_permalink', 10, 2 );
	add_filter( 'term_link', __NAMESPACE__ . '\\taxonomy_permalink', 10, 3 );
	add_filter( 'the_posts', __NAMESPACE__ . '\\rerun_empty_exact_search', 10, 2 );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );

	add_filter( 'breadcrumb_trail_items',  __NAMESPACE__ . '\\breadcrumb_trail_items', 10, 2 );

	add_filter( 'wp_parser_skip_duplicate_hooks', '__return_true' );

	add_filter( 'document_title_separator', __NAMESPACE__ . '\\theme_title_separator', 10, 2 );
}

/**
 * Customize the theme title separator.
 *
 * @return string
 */
function theme_title_separator(){
	return '|';
}

/**
 * Fix breadcrumb for hooks.
 *
 * A hook has a parent (the function containing it), which causes the Breadcrumb
 * Trail plugin to introduce trail items related to the parent that shouldn't
 * be shown.
 *
 * @param  array $items The breadcrumb trail items
 * @param  array $args  Original arg
 * @return array
 */
function breadcrumb_trail_items( $items, $args ) {
	$post_type = 'wp-parser-hook';

	// Bail early when not the single archive for hook
	if ( ! is_singular() || $post_type !== get_post_type() || ! isset( $items[4] ) ) {
		return $items;
	}

	$post_type_object = get_post_type_object( $post_type );

	// Replaces 'Functions' archive link with 'Hooks' archive link
	$items[2] = '<a href="' . get_post_type_archive_link( $post_type ) . '">' . $post_type_object->labels->name . '</a>';
	// Replace what the plugin thinks is the parent with the hook name
	$items[3] = $items[4];
	// Unset the last element since it shifted up in trail hierarchy
	unset( $items[4] );

	return $items;
}

/**
 * widgets_init function.
 *
 * @access public
 * @return void
 */
function widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Sidebar', 'wporg' ),
		'id'            => 'sidebar-1',
		'before_widget' => '<aside id="%1$s" class="box gray widget %2$s">',
		'after_widget'  => '</div></aside>',
		'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1><div class="widget-content">',
	) );

	register_sidebar( array(
		'name'          => __( 'Landing Page Footer - Left', 'wporg' ),
		'id'            => 'landing-footer-1',
		'description'   => __( 'Appears in footer of the primary landing page', 'wporg' ),
		'before_widget' => '<div id="%1$s" class="widget box %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	) );

	register_sidebar( array(
		'name'          => __( 'Landing Page Footer - Center', 'wporg' ),
		'id'            => 'landing-footer-2',
		'description'   => __( 'Appears in footer of the primary landing page', 'wporg' ),
		'before_widget' => '<div id="%1$s" class="widget box %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	) );
}

/**
 * @param \WP_Query $query
 */
function pre_get_posts( $query ) {

	if ( $query->is_main_query() && ( $query->is_post_type_archive() || $query->is_search() ) ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}

	if ( $query->is_main_query() && $query->is_tax() && $query->get( 'wp-parser-source-file' ) ) {
		$query->set( 'wp-parser-source-file', str_replace( array( '.php', '/' ), array( '-php', '_' ), $query->query['wp-parser-source-file'] ) );
	}

	// If user has '()' at end of a search string, assume they want a specific function/method.
	if ( $query->is_search() ) {
		$s = htmlentities( $query->get( 's' ) );
		if ( '()' === substr( $s, -2 ) ) {
			// Enable exact search
			$query->set( 'exact',     true );
			// Modify the search query to omit the parentheses
			$query->set( 's',         substr( $s, 0, -2 ) ); // remove '()'
			// Restrict search to function-like content
			$query->set( 'post_type', array( 'wp-parser-function', 'wp-parser-method' ) );
		}
	}
}

/**
 * Rerun an exact search with the same criteria except exactness if no posts
 * were found.
 *
 * @access public
 *
 * @param  array    $posts Array of posts after the main query
 * @param  WP_Query $query WP_Query object
 * @return array
 */
function rerun_empty_exact_search( $posts, $query ) {
	if ( is_search() && true === $query->get( 'exact' ) && ! $query->found_posts ) {
		$query->set( 'exact', false );
		$posts = $query->get_posts();
	}
	return $posts;
}

function register_nav_menus() {

	\register_nav_menus( array(
		'devhub-menu' => __( 'Developer Resources Menu', 'wporg' ),
	) );
}

function method_permalink( $link, $post ) {
	if ( $post->post_type !== 'wp-parser-method' )
		return $link;

	list( $class, $method ) = explode( '-', $post->post_name );
	$link = home_url( user_trailingslashit( "reference/classes/$class/$method" ) );
	return $link;
}

function taxonomy_permalink( $link, $term, $taxonomy ) {
	if ( $taxonomy === 'wp-parser-source-file' ) {
		$slug = $term->slug;
		if ( substr( $slug, -4 ) === '-php' ) {
			$slug = substr( $slug, 0, -4 ) . '.php';
			$slug = str_replace( '_', '/', $slug );
		}
		$link = home_url( user_trailingslashit( "reference/files/$slug" ) );
	} elseif ( $taxonomy === 'wp-parser-since' ) {
		$link = str_replace( $term->slug, str_replace( '-', '.', $term->slug ), $link );
	}
	return $link;
}

/**
 * Outputs JavaScript intended to appear in the head of the page.
 */
function header_js() {
	// Output CSS to hide markup with the class 'hide-if-js'. Ensures the markup is visible if JS is not present.
	echo "<script type=\"text/javascript\">jQuery( '<style>.hide-if-js { display: none; }</style>' ).appendTo( 'head' );</script>\n";
}

function theme_scripts_styles() {
	wp_enqueue_style( 'dashicons' );
	wp_enqueue_style( 'open-sans', '//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,400,300,600' );
	wp_enqueue_style( 'wporg-developer-style', get_stylesheet_uri(), array(), '2' );
	wp_enqueue_style( 'wp-dev-sass-compiled', get_template_directory_uri() . '/stylesheets/main.css', array( 'wporg-developer-style' ), '20160224' );
	wp_enqueue_script( 'wporg-developer-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20120206', true );
	wp_enqueue_script( 'wporg-developer-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20130115', true );
	wp_enqueue_script( 'wporg-developer-search', get_template_directory_uri() . '/js/search.js', array(), '20150430', true );
}

/**
 * Rename the 'Comments' meta box to 'User Contributed Notes' for reference-editing screens.
 *
 * @param string  $post_type Post type.
 * @param WP_Post $post      WP_Post object for the current post.
 */
function rename_comments_meta_box( $post_type, $post ) {
	if ( is_parsed_post_type( $post_type ) ) {
		remove_meta_box( 'commentsdiv', $post_type, 'normal' );
		add_meta_box( 'commentsdiv', __( 'User Contributed Notes', 'wporg' ), 'post_comment_meta_box', $post_type, 'normal', 'high' );
	}
}
