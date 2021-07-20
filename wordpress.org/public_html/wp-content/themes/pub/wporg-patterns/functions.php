<?php

namespace WordPressdotorg\Pattern_Directory\Theme;

use const WordPressdotorg\Pattern_Directory\Pattern_Post_Type\POST_TYPE;
use const WordPressdotorg\Pattern_Directory\Pattern_Flag_Post_Type\POST_TYPE as FLAG_POST_TYPE;

add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets', 20 );
add_action( 'wp_head', __NAMESPACE__ . '\generate_block_editor_styles_html' );
add_action( 'wp_head', __NAMESPACE__ . '\add_social_meta_tags' );
add_action( 'body_class', __NAMESPACE__ . '\body_class', 10, 2 );
add_action( 'pre_get_posts', __NAMESPACE__ . '\pre_get_posts' );
add_filter( 'init', __NAMESPACE__ . '\add_rewrite' );

add_filter( 'archive_template', __NAMESPACE__ . '\use_index_php_as_template' );
add_action( 'template_redirect', __NAMESPACE__ . '\rewrite_search_url' );

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function setup() {
	add_theme_support( 'post-thumbnails' );

	// Add gutenberg styling supports.
	add_theme_support( 'align-wide' );
	add_theme_support( 'custom-spacing' );
	add_theme_support( 'custom-line-height' );
	add_theme_support( 'experimental-link-color' );

	// The parent wporg theme is designed for use on wordpress.org/* and assumes locale-domains are available.
	// Remove hreflang support.
	remove_action( 'wp_head', 'WordPressdotorg\Theme\hreflang_link_attributes' );
}

/**
 * Enqueue styles & scripts.
 *
 * The wporg theme registers these with static versions, so we need to override with dynamic versions for
 * cache-busting. The version is set to the last modified time during development.
 */
function enqueue_assets() {
	$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	$suffix       = $script_debug ? '' : '.min';

	// Unregister the parent style so that the version & deps defined here are used.
	wp_deregister_style( 'wporg-style' );
	wp_enqueue_style(
		'wporg-style',
		get_theme_file_uri( '/css/style.css' ),
		array( 'dashicons', 'open-sans' ),
		filemtime( __DIR__ . '/css/style.css' )
	);
	wp_style_add_data( 'wporg-style', 'rtl', 'replace' );

	$script_asset_path = dirname( __FILE__ ) . '/build/index.asset.php';
	if ( file_exists( $script_asset_path ) ) {
		$script_asset = require( $script_asset_path );
		wp_enqueue_script(
			'wporg-pattern-script',
			get_theme_file_uri( '/build/index.js' ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		wp_set_script_translations( 'wporg-pattern-script', 'wporg-patterns' );

		wp_add_inline_script(
			'wporg-pattern-script',
			sprintf( "var wporgLocale = '%s';", wp_json_encode( get_locale() ) ),
			'before'
		);

		wp_add_inline_script(
			'wporg-pattern-script',
			sprintf(
				"var wporgPatternsData = JSON.parse( decodeURIComponent( '%s' ) )",
				rawurlencode( wp_json_encode( array(
					'userId' => get_current_user_id(),
				) ) ),
			),
			'before'
		);

		wp_add_inline_script(
			'wporg-pattern-script',
			sprintf(
				"var wporgPatternsUrl = JSON.parse( decodeURIComponent( '%s' ) )",
				rawurlencode( wp_json_encode( array(
					'assets' => esc_url( get_stylesheet_directory_uri() ),
					'site' => esc_url( home_url() ),
					'login' => esc_url( wp_login_url() ),
				) ) ),
			),
			'before'
		);
	}

	wp_enqueue_script( 'wporg-navigation', get_template_directory_uri() . "/js/navigation$suffix.js", array(), '20210331', true );
}

/**
 * Generate styles used in the block pattern preview iframe.
 * See https://github.com/WordPress/gutenberg/blob/6ad2a433769a4514fc52083e97aa47a0bc9edf07/lib/client-assets.php#L710
 */
function generate_block_editor_styles_html() {
	$handles = array( 'wp-block-library' );

	$block_registry = \WP_Block_Type_Registry::get_instance();

	foreach ( $block_registry->get_all_registered() as $block_type ) {
		if ( ! empty( $block_type->style ) ) {
			$handles[] = $block_type->style;
		}

		if ( ! empty( $block_type->editor_style ) ) {
			$handles[] = $block_type->editor_style;
		}
	}

	$handles = array_unique( $handles );
	$done    = wp_styles()->done;

	ob_start();

	wp_styles()->done = array();
	wp_styles()->do_items( $handles );
	wp_styles()->done = $done;

	wp_add_inline_script(
		'wporg-pattern-script',
		sprintf(
			'window.__editorStyles = JSON.parse( decodeURIComponent( \'%s\' ) );',
			rawurlencode( wp_json_encode( array( 'html' => ob_get_clean() ) ) )
		),
		'before'
	);
}

/**
 * Add a class to body for the My Patterns page.
 *
 * @param string[] $classes An array of body class names.
 * @param string[] $class   An array of additional class names added to the body.
 */
function body_class( $classes, $class ) {
	global $wp_query;
	if ( 'my-patterns' === $wp_query->get( 'pagename' ) ) {
		$classes[] = 'my-patterns';
	}
	return $classes;
}

/**
 * Handle queries.
 * - My Patterns and My Favories have "subpages" which should still show the root page.
 * - Default & archive views should show patterns, not posts.
 *
 * @param \WP_Query $query The WordPress Query object.
 */
function pre_get_posts( $query ) {
	if ( is_admin() ) {
		return;
	}
	if ( ! $query->is_main_query() ) {
		return;
	}

	$pagename = $query->get( 'pagename' );
	if ( $pagename ) {
		list( $_pagename ) = explode( '/', $pagename );
		if ( in_array( $_pagename, array( 'my-patterns', 'my-favorites' ) ) ) {
			// Need to get the page ID because this is set before `pre_get_posts` fires.
			$page = get_page_by_path( $_pagename );
			$query->set( 'pagename', $_pagename );
			$query->set( 'page_id', (int) $page->ID );
		}
	} else if ( ! $query->get( 'pagename' ) && 'post' === $query->get( 'post_type', 'post' ) ) {
		$query->set( 'post_type', array( POST_TYPE ) );
		$query->set( 'post_status', array( 'publish' ) );

		$query->set( 'meta_key', 'wpop_locale' );
		$query->set( 'meta_value', get_locale() );
	}
}

/**
 * Add the "My Patterns" status rewrites.
 * This will redirect `my-patterns/draft`, `my-patterns/pending` etc to use the My Patterns page.
 */
function add_rewrite() {
	add_rewrite_rule( '^my-patterns/[^/]+/?$', 'index.php?pagename=my-patterns', 'top' );
	add_rewrite_rule( '^my-favorites/.+/?$', 'index.php?pagename=my-favorites', 'top' );
}


/**
 * Use the index.php template for various WordPress views that would otherwise be handled by the parent theme.
 */
function use_index_php_as_template() {
	return __DIR__ . '/index.php';
}

/**
 * Checks whether the user has a pending flag for a specific pattern.
 *
 * @return bool
 */
function user_has_flagged_pattern() {
	$args = array(
		'author' => get_current_user_id(),
		'post_parent' => get_the_ID(),
		'post_type' => FLAG_POST_TYPE,
		'post_status' => 'pending',
	);

	$items = new \WP_Query( $args );

	return $items->have_posts();
}

/**
 * Get the full, filtered content of a post, ignoring more and noteaser tags and pagination.
 *
 * See https://github.com/WordPress/wordcamp.org/blob/442ea26d8e6a1b39f97114e933842b1ec4f8eef9/public_html/wp-content/mu-plugins/blocks/includes/content.php#L21
 *
 * @param int|WP_Post $post Post ID or post object.
 * @return string The full, filtered post content.
 */
function get_all_the_content( $post ) {
	$post = get_post( $post );

	$content = wp_kses_post( $post->post_content );

	/** This filter is documented in wp-includes/post-template.php */
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );

	return $content;
}

/**
 * Rewrites the search url from s={keyword} to /search/{keyword}.
 *
 * @return void
 */
function rewrite_search_url() {
	if ( is_search() && ! empty( $_GET['s'] ) ) {
		wp_redirect( home_url( '/search/' ) . urlencode( trim( get_query_var( 's' ) ) ) . '/' );
		exit();
	}
}

/**
 * Add meta tags for richer social media integrations.
 */
function add_social_meta_tags() {
	global $wporg_global_header_options;
	$default_image = get_stylesheet_directory_uri() . '/images/social-image.png';
	$og_fields = [];

	if ( is_front_page() || is_home() ) {
		$og_fields = [
			'og:title'       => __( 'Block Pattern Directory', 'wporg-patterns' ),
			'og:description' => __( 'Add a beautifully designed, ready to go layout to any WordPress site with a simple copy/paste.', 'wporg-patterns' ),
			'og:site_name'   => $wporg_global_header_options['rosetta_title'] ?? 'WordPress.org',
			'og:type'        => 'website',
			'og:url'         => home_url(),
			'og:image'       => esc_url( $default_image ),
		];
	} else if ( is_tax() ) {
		$og_fields = [
			'og:title'       => sprintf( __( 'Block Patterns: %s', 'wporg-patterns' ), esc_attr( single_term_title( '', false ) ) ),
			'og:description' => __( 'Add a beautifully designed, ready to go layout to any WordPress site with a simple copy/paste.', 'wporg-patterns' ),
			'og:site_name'   => esc_attr( $wporg_global_header_options['rosetta_title'] ?? 'WordPress.org' ),
			'og:type'        => 'website',
			'og:url'         => esc_url( get_term_link( get_queried_object_id() ) ),
			'og:image'       => esc_url( $default_image ),
		];
	} else if ( is_singular( POST_TYPE ) ) {
		$og_fields = [
			'og:title'       => the_title_attribute( array( 'echo' => false ) ),
			'og:description' => esc_attr( strip_tags( get_post_meta( get_the_ID(), 'wpop_description', true ) ) ),
			'og:site_name'   => esc_attr( $wporg_global_header_options['rosetta_title'] ?? 'WordPress.org' ),
			'og:type'        => 'website',
			'og:url'         => esc_url( get_permalink() ),
			'og:image'       => esc_url( $default_image ),
		];
		printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
		printf( '<meta name="twitter:site" content="@WordPress">' . "\n" );
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $default_image ) );
	}

	foreach ( $og_fields as $property => $content ) {
		printf(
			'<meta property="%1$s" content="%2$s" />' . "\n",
			esc_attr( $property ),
			esc_attr( $content )
		);
	}

	if ( isset( $og_fields['og:description'] ) ) {
		printf(
			'<meta name="description" content="%1$s" />' . "\n",
			esc_attr( $og_fields['og:description'] )
		);
	}
}
