<?php
/**
 * Photo Directory functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;

use WordPressdotorg\Photo_Directory;

/**
 * Returns the photo post type.
 *
 * @return string
 */
function get_photo_post_type() {
	return Photo_Directory\Registrations::get_post_type();
}

/**
 * Returns the number of published photos.
 *
 * @return int
 */
function get_photos_count() {
	return wp_count_posts( get_photo_post_type(), 'readable' )->publish;
}

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

}
add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );

/**
 * Handle the root-level redirect to trailing-slash'd uri which redirect_canonical() usually does.
 */
function enforce_trailing_slash() {
	if ( '/photos' === $_SERVER['REQUEST_URI'] ) {
		wp_safe_redirect( '/photos/' );
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
	$GLOBALS['content_width'] = apply_filters( 'wporg_photos_content_width', 640 );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\content_width', 0 );

/**
 * Enqueue scripts and styles.
 */
function scripts() {
	wp_enqueue_style( 'wporg-style', get_stylesheet_directory_uri() .  '/css/style.css', [ 'dashicons', 'open-sans' ], filemtime( __DIR__ . '/css/style.css' ) );
	wp_style_add_data( 'wporg-style', 'rtl', 'replace' );

	// Make jQuery a footer script.
	wp_scripts()->add_data( 'jquery', 'group', 1 );
	wp_scripts()->add_data( 'jquery-core', 'group', 1 );
	wp_scripts()->add_data( 'jquery-migrate', 'group', 1 );

	wp_enqueue_script( 'wporg-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20181209', true );
	wp_enqueue_script( 'wporg-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20151215', true );

	// No Jetpack scripts needed.
	add_filter( 'jetpack_implode_frontend_css', '__return_false' );
	wp_dequeue_script( 'devicepx' );
	wp_register_script( 'grofiles-cards', false );
	wp_enqueue_script( 'grofiles-cards' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts' );

// Disable mentions script in Photo Directory.
add_filter( 'jetpack_mentions_should_load_ui', '__return_false', 11 );

/**
 * Swaps out the no-js for the js body class if the browser supports Javascript.
 */
function nojs_body_tag() {
	echo "<script>document.body.className = document.body.className.replace('no-js','js');</script>\n";
}
add_action( 'wp_body_open', __NAMESPACE__ . '\nojs_body_tag' );

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
	];

	if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
		return $src;
	}

	// Use CDN url when running on WordPress.org production.
	if ( defined( 'IS_WPORG' ) && IS_WPORG ) {
		if ( in_array( $handle, $cdn_urls, true ) ) {
			$src = str_replace( get_home_url(), 'https://s.w.org', $src );
		}
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
		$title['title']   = __( 'WordPress Photos', 'wporg-photos' );
		$title['tagline'] = __( 'WordPress.org', 'wporg-photos' );
	} else {
		if ( is_singular( get_photo_post_type() ) ) {
			$title['title'] .= ' - ' . __( 'WordPress photo', 'wporg-photos' );
		} elseif ( is_tax() ) {
			$title['title'] = sprintf( __( 'Photos categorized as %s', 'wporg-photos' ), strtolower( $title['title'] ) );
		}

		// If results are paged and the max number of pages is known.
		if ( is_paged() && $wp_query->max_num_pages ) {
			// translators: 1: current page number, 2: total number of pages
			$title['page'] = sprintf(
				__( 'Page %1$s of %2$s', 'wporg-photos' ),
				get_query_var( 'paged' ),
				$wp_query->max_num_pages
			);
		}

		$title['site'] = __( 'WordPress.org', 'wporg-photos' );
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
	$site_name = function_exists( '\WordPressdotorg\site_brand' ) ? \WordPressdotorg\site_brand() : 'WordPress.org';

	if ( is_front_page() ) {
		$og_fields = [
			'og:title'       => __( 'WordPress Photo Directory', 'wporg-photos' ),
			'og:description' => __( 'Choose from a growing collection of free, CC0-licensed photos to customize and enhance your WordPress website.', 'wporg-photos' ),
			'og:site_name'   => $site_name,
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

	if ( ! is_singular( get_photo_post_type() ) ) {
		return;
	}

	$photo_id = get_post_thumbnail_id();
	$photo_src = wp_get_attachment_image_src( $photo_id, 'medium_large' );
	$photo_url = $photo_src[0] ?? '';

	printf( '<meta property="og:title" content="%s" />' . "\n", the_title_attribute( array( 'echo' => false ) ) );
	printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( strip_tags( get_the_excerpt() ) ) );
	printf( '<meta name="description" content="%s" />' . "\n", esc_attr( strip_tags( get_the_excerpt() ) ) );
	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( $site_name ) );
	printf( '<meta property="og:type" content="website" />' . "\n" );
	printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( get_permalink() ) );
	printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $photo_url ) );
	printf( '<meta property="og:image:type" content="%s" />' . "\n", esc_attr( get_post_mime_type( $photo_id ) ) );
	printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
	printf( '<meta name="twitter:site" content="@WordPress">' . "\n" );
	printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $photo_url ) );
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
} );

/**
 * Custom template tags for this theme.
 */
//require get_stylesheet_directory() . '/inc/template-tags.php';

/**
 * Modifies the main query during the `pre_get_posts` action.
 *
 * @param WP_Query $query Query object.
 */
function pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$post_type = get_photo_post_type();

	// Request photos for the front page main query.
	if ( is_home() || is_author() ) {
		$query->set( 'post_type', $post_type );
	}

	// Set the number of photos to appear per page in the photos archive.
	if ( is_post_type_archive( $post_type ) || ( is_home() && is_paged() ) ) {
		$query->set( 'posts_per_page', 30 );
	}
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\pre_get_posts' );

/**
 * Amends body class for taxonomy root pages.
 *
 * @param array $classes Body classes.
 * @return array
 */
function body_class_for_taxonomy_roots( $classes ) {
	$roots = [ 'c', 'color', 'orientation', 't' ];

	$post = get_post();

	if ( $post && in_array( $post->post_name, $roots ) ) {
		$classes[] = 'archive';
		$classes[] = 'taxonomy-archive-' . $post->post_name;
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\body_class_for_taxonomy_roots' );
