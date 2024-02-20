<?php

namespace WordPressdotorg\Pattern_Directory\Theme;

use const WordPressdotorg\Pattern_Directory\Pattern_Post_Type\POST_TYPE;
use const WordPressdotorg\Pattern_Directory\Pattern_Flag_Post_Type\POST_TYPE as FLAG_POST_TYPE;
use function WordPressdotorg\MU_Plugins\Global_Header_Footer\{ is_rosetta_site, get_rosetta_name };

add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets', 20 );
add_action( 'wp_head', __NAMESPACE__ . '\add_social_meta_tags' );
add_filter( 'document_title_parts', __NAMESPACE__ . '\set_document_title' );
add_action( 'body_class', __NAMESPACE__ . '\body_class', 10, 2 );
add_action( 'pre_get_posts', __NAMESPACE__ . '\pre_get_posts' );
add_filter( 'init', __NAMESPACE__ . '\add_rewrite' );

add_filter( 'archive_template', __NAMESPACE__ . '\use_index_php_as_template' );
add_filter( 'search_template', __NAMESPACE__ . '\use_index_php_as_template' );
add_action( 'template_redirect', __NAMESPACE__ . '\rewrite_urls' );

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function setup() {
	// Add gutenberg styling supports.
	add_theme_support( 'align-wide' );

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

	// Build up the alignment styles to match the layout set in theme.json.
	// See https://github.com/WordPress/gutenberg/blob/9d4b83cbbafcd6c6cbd20c86b572f458fc65ff16/lib/block-supports/layout.php#L38
	$block_gap = wp_get_global_styles( array( 'spacing', 'blockGap' ) );
	$layout = wp_get_global_settings( array( 'layout' ) );
	$style = wp_get_layout_style( '.entry-content', $layout, true, $block_gap );
	wp_add_inline_style( 'wporg-style', $style );

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
			sprintf(
				"var wporgLocale = JSON.parse( decodeURIComponent( '%s' ) );",
				rawurlencode( wp_json_encode( array(
					'id' => get_locale(),
					'displayName' => is_rosetta_site() ? get_rosetta_name() : '',
				) ) ),
			),
			'before'
		);

		wp_add_inline_script(
			'wporg-pattern-script',
			sprintf(
				"var wporgPatternsData = JSON.parse( decodeURIComponent( '%s' ) );",
				rawurlencode( wp_json_encode( array(
					'currentAuthorName' => esc_html( get_the_author_meta( 'display_name' ) ),
					'env' => esc_js( wp_get_environment_type() ),
					'thumbnailVersion' => 1, // cachebuster for the generated thumbnail image.
					'userId' => get_current_user_id(),
				) ) ),
			),
			'before'
		);

		wp_add_inline_script(
			'wporg-pattern-script',
			sprintf(
				"var wporgPatternsUrl = JSON.parse( decodeURIComponent( '%s' ) );",
				rawurlencode( wp_json_encode( array(
					'assets' => esc_url( get_stylesheet_directory_uri() ),
					'site' => esc_url( home_url() ),
					'login' => esc_url( wp_login_url() ),
					'register' => esc_url( wp_registration_url() ),
				) ) ),
			),
			'before'
		);
	}

	wp_enqueue_script( 'wporg-navigation', get_template_directory_uri() . "/js/navigation$suffix.js", array(), '20210331', true );
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
	if ( $wp_query->is_page() ) {
		$classes[] = 'page-' . $wp_query->get( 'pagename' );
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
		if ( in_array( $_pagename, array( 'my-patterns', 'favorites' ) ) ) {
			// Need to get the page ID because this is set before `pre_get_posts` fires.
			$page = get_page_by_path( $_pagename );
			$query->set( 'pagename', $_pagename );
			$query->set( 'page_id', (int) $page->ID );
		}
	} else if ( ! $query->get( 'pagename' ) && 'post' === $query->get( 'post_type', 'post' ) ) {
		$query->set( 'post_type', array( POST_TYPE ) );
		$query->set( 'post_status', array( 'publish' ) );

		// The `orderby_locale` meta_query will be transformed into a query orderby by Pattern_Post_Type\filter_orderby_locale().
		$query->set( 'meta_query', array(
			'orderby_locale' => array(
				'key'     => 'wpop_locale',
				'compare' => 'IN',
				// Order in value determines result order
				'value'   => array( get_locale(), 'en_US' ),
			),
		) );
	}
}

/**
 * Add rewrites needed for nested page navigation.
 * - `my-patterns/<status>` will render the My Patterns page.
 * - `favorites/categories/<cat>` will render the Favorites page.
 * - `author/<username>/categories/<cat>` will render the author archive.
 */
function add_rewrite() {
	add_rewrite_rule( '^my-patterns/[^/]+/?$', 'index.php?pagename=my-patterns', 'top' );
	add_rewrite_rule( '^favorites/.+/?$', 'index.php?pagename=favorites', 'top' );
	add_rewrite_endpoint( 'categories', EP_AUTHORS );
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
 * Set up redirects for the site.
 *
 * @return void
 */
function rewrite_urls() {
	// Redirect searches to `/search/term`.
	if ( is_search() && ! empty( $_GET['s'] ) ) {
		wp_redirect( home_url( '/search/' ) . urlencode( trim( get_query_var( 's' ) ) ) . '/' );
		exit();
	}
	// Redirect old slug `my-favorites` to `favorites`, see WordPress/pattern-directory#332.
	$path = str_replace( 'patterns/', '', trim( $_SERVER['REQUEST_URI'], '/' ) );
	if ( preg_match( '/^my-favorites(.*)/', $path, $matches ) ) {
		wp_redirect( home_url( '/favorites/' . $matches[1] . '/' ) );
		exit();
	}
}

/**
 * Add meta tags for richer social media integrations.
 */
function add_social_meta_tags() {
	$og_fields     = [];
	$default_image = get_stylesheet_directory_uri() . '/images/social-image.png';
	$site_title    = function_exists( '\WordPressdotorg\site_brand' ) ? \WordPressdotorg\site_brand() : 'WordPress.org';

	if ( is_front_page() || is_home() ) {
		$og_fields = [
			'og:title'       => __( 'Block Pattern Directory', 'wporg-patterns' ),
			'og:description' => __( 'Add a beautifully designed, ready to go layout to any WordPress site with a simple copy/paste.', 'wporg-patterns' ),
			'og:site_name'   => $site_title,
			'og:type'        => 'website',
			'og:url'         => home_url(),
			'og:image'       => esc_url( $default_image ),
		];
	} else if ( is_tax() ) {
		$og_fields = [
			'og:title'       => sprintf( __( 'Block Patterns: %s', 'wporg-patterns' ), esc_attr( single_term_title( '', false ) ) ),
			'og:description' => __( 'Add a beautifully designed, ready to go layout to any WordPress site with a simple copy/paste.', 'wporg-patterns' ),
			'og:site_name'   => $site_title,
			'og:type'        => 'website',
			'og:url'         => esc_url( get_term_link( get_queried_object_id() ) ),
			'og:image'       => esc_url( $default_image ),
		];
	} else if ( is_singular( POST_TYPE ) ) {
		$og_fields = [
			'og:title'       => the_title_attribute( array( 'echo' => false ) ),
			'og:description' => strip_tags( get_post_meta( get_the_ID(), 'wpop_description', true ) ),
			'og:site_name'   => $site_title,
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
function set_document_title( $title ) {
	global $wp_query;

	if ( is_front_page() ) {
		$title['title']   = __( 'Block Pattern Directory', 'wporg-patterns' );
		$title['tagline'] = __( 'WordPress.org', 'wporg-patterns' );
	} else {
		if ( is_singular( POST_TYPE ) ) {
			$title['title'] .= ' - ' . __( 'Block Pattern', 'wporg-patterns' );
		} elseif ( is_tax() ) {
			/* translators: Taxonomy term name */
			$title['title'] = sprintf( __( 'Block Patterns: %s', 'wporg-patterns' ), $title['title'] );
		} elseif ( is_author() ) {
			/* translators: Author name */
			$title['title'] = sprintf( __( 'Block Patterns by %s', 'wporg-patterns' ), $title['title'] );
		}

		// If results are paged and the max number of pages is known.
		if ( is_paged() && $wp_query->max_num_pages ) {
			// translators: 1: current page number, 2: total number of pages
			$title['page'] = sprintf(
				__( 'Page %1$s of %2$s', 'wporg-patterns' ),
				get_query_var( 'paged' ),
				$wp_query->max_num_pages
			);
		}

		$title['site'] = __( 'WordPress.org', 'wporg-patterns' );
	}

	return $title;
}
