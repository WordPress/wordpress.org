<?php
/**
 * WordPress.org functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPressdotorg\Theme\Main
 */

namespace WordPressdotorg\MainTheme;

function _esc_html_e( $text, $domain ) {
	echo esc_html( $text );
}
function ___( $text, $domain ) {
	return $text;
}
function esc_html___( $text, $domain ) {
	return esc_html( $text );
}

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

	/*
	 * Featured images are currently not used in the theme but this enables
	 * to use them via open graph tags when a page is shared.
	 */
	add_theme_support( 'post-thumbnails' );

	/*
	 * Enable WordPress.org skip-to links.
	 */
	add_action( 'wp_head', '\WordPressdotorg\skip_to_main' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );

/**
 * Registers theme-specific widgets.
 */
function widgets() {
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
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_enqueue_style(
		'wporg-style',
		get_theme_file_uri( '/css/style.css' ),
		array( 'dashicons', 'open-sans' ),
		filemtime( __DIR__ . '/css/style.css' )
	);

	wp_style_add_data( 'wporg-style', 'rtl', 'replace' );

	// Move jQuery to the footer.
	wp_scripts()->add_data( 'jquery', 'group', 1 );
	wp_scripts()->add_data( 'jquery-core', 'group', 1 );
	wp_scripts()->add_data( 'jquery-migrate', 'group', 1 );
	wp_enqueue_script( 'jquery' );

	if ( is_page( 'stats' ) ) {
		wp_enqueue_script( 'google-charts', 'https://www.gstatic.com/charts/loader.js', [], null, true );
		wp_enqueue_script( 'wporg-page-stats', get_theme_file_uri( '/js/page-stats.js' ), [ 'jquery', 'google-charts' ], filemtime( __DIR__ . '/js/page-stats.js' ), true );
		wp_localize_script( 'wporg-page-stats', 'wporgPageStats', [
			'trunk'       => number_format( WP_CORE_STABLE_BRANCH + 0.1, 1 ), /* trunk */
			'viewAsChart' => __( 'View as Chart', 'wporg' ),
			'viewAsTable' => __( 'View as Table', 'wporg' ),
		] );
	} elseif ( is_page( 'download' ) ) {
		wp_enqueue_style( 'jquery-modal-style', get_theme_file_uri( '/css/jquery.modal.min.css' ), [], '0.9.2' );
		if ( is_rtl() ) {
			wp_add_inline_style(
				'jquery-modal-style',
				'.modal { text-align: right; }' .
				'.modal a.close-modal { right: unset; left: -12.5px; }'
			);
		}
		wp_enqueue_script( 'jquery-modal', get_theme_file_uri( '/js/jquery.modal.min.js' ), [ 'jquery' ], '0.9.2', true );
		wp_enqueue_script( 'wporg-page-download', get_theme_file_uri( '/js/page-download.js' ), [ 'jquery', 'jquery-modal' ], '20201118', true );
	} elseif ( is_page( '40-percent-of-web' ) ) {
		wp_enqueue_style( 'page-40-percent', get_theme_file_uri( '/css/page-40-percent-style.css' ), [], '20210527a' );
	}

	if ( is_page() && get_queried_object()->post_parent ) {
		wp_enqueue_script( 'wporg-navigation', get_theme_file_uri( "/js/navigation$suffix.js" ), [], '20151215', true );
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
		'wporg-plugins-skip-link-focus-fix',
		'jquery-core',
		'jquery-migrate',
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
	if ( is_page() && get_queried_object() ) {
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

	if ( ! $post || 'page' !== $post->post_type || ! $post->page_template || ( 'default' === $post->page_template && ! is_front_page() ) ) {
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

	if ( is_page() && $title_post && 'about' === $title_post->post_name ) {
		$title = esc_html_x( 'About', 'Page title', 'wporg' );
	}

	if ( is_page() && $title_post && 'download' === $title_post->post_name ) {
		$title = esc_html_x( 'Download', 'Page title', 'wporg' );
	}

	return $title;
}
add_filter( 'the_title', __NAMESPACE__ . '\parent_page_title', 11, 2 );

/**
 * Some custom redirects for old pages no longer included in this theme.
 */
function old_page_redirects() {
	if ( ! is_404() ) {
		return;
	}

	// Old WordPress.org/about/* pages:
	if (
		'wordpress.org' == $_SERVER['HTTP_HOST'] &&
		preg_match( '!^/about/(books|fanart|screenshots)!i', $_SERVER['REQUEST_URI'] )
	) {
		wp_safe_redirect( '/about/', 301 );
		die();
	}

	// WordPress.org/about/gpl -> about/license
	if (
		'wordpress.org' == $_SERVER['HTTP_HOST'] &&
		preg_match( '!^/about/gpl/!i', $_SERVER['REQUEST_URI'] )
	) {
		wp_safe_redirect( '/about/license/', 301 );
		die();
	}

	// https://*/license.txt > about/license
	if ( '/license.txt' === $_SERVER['REQUEST_URI'] ) {
		wp_safe_redirect( '/about/license/', 301 );
		die();
	}
}
add_filter( 'template_redirect', __NAMESPACE__ . '\old_page_redirects' );

/**
 * Disables hreflang tags on instances of this theme, unless it's a page that has localised variants.
 *
 * This takes the reverse approach of the function name.
 * Instead of maybe removing it, it removes it unless a specific criteria is met.
 * 
 * The criteria is that...
 *  - It's a page
 *  - It's not the hosting page
 *  - It's not a rosetta page owned by anyone other than wordpressdotorg (These are the globally synced pages).
 */
function maybe_remove_hreflang_tags() {
	if (
		! is_page() ||
		is_page( 'hosting' ) ||
		// Exclude custom localised pages.
		// Only include posts authored by `wordPressdotorg` which are using a page template.
		(
			defined( 'IS_ROSETTA_NETWORK' ) && IS_ROSETTA_NETWORK &&
			get_user_by( 'slug', 'wordpressdotorg' )->ID != get_post()->post_author
		)
	) {
		remove_action( 'wp_head', 'WordPressdotorg\Theme\hreflang_link_attributes' );
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\maybe_remove_hreflang_tags', 1 );

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
