<?php

/**
 * WP.org Themes' functions and definitions.
 *
 * @package wporg-themes
 */

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function wporg_themes_setup() {
	global $themes_allowedtags;

	load_theme_textdomain( 'wporg-themes' );

	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
	) );

	add_theme_support( 'title-tag' );

	// No need for canonical lookups
	remove_action( 'template_redirect', 'redirect_canonical' );
	remove_action( 'template_redirect', 'wp_old_slug_redirect' );

	add_action( 'template_redirect', 'wporg_themes_canonical_redirects' );

	add_theme_support( 'wp4-styles' );
}
add_action( 'after_setup_theme', 'wporg_themes_setup' );

/**
 * Handle redirects which redirect_canonical() usually would (or should) do.
 */
function wporg_themes_canonical_redirects() {

	// always include the trailing slash for the Site URL
	if ( '/themes' === $_SERVER['REQUEST_URI'] ) {
		wp_safe_redirect( '/themes/', 301 );
		die();
	}

	// We don't need any urls such as /themes/index.php/twentyten/ working
	if ( false !== stripos( $_SERVER['REQUEST_URI'], '/index.php' ) ) {
		$url = str_ireplace( '/index.php', '/', $_SERVER['REQUEST_URI'] );
		wp_safe_redirect( $url, 301 );
		die();
	}

	// We don't support pagination on the directory at present.
	if ( get_query_var( 'paged' ) || get_query_var( 'page' ) ) {
		$url = remove_query_arg( [ 'paged', 'page' ] );
		$url = preg_replace( '!(page/\d+|/\d+/?$)!i', '', $url );

		// Remove any double slashes
		$url = preg_replace( '!/{2,}!', '/', $url );

		wp_safe_redirect( $url ); // Not 301, as paginated requests will one day be supported hopefully.
		die();
	}

	// Searches should be redirected to canonical location.
	if ( isset( $_GET['s'] ) ) {
		wp_safe_redirect( home_url( '/search/' . urlencode( get_query_var( 's' ) ) . '/' ), 301 );
		die();
	}

	// Redirect direct queries for a theme by ID to it's post.
	if (
		isset( $_GET['p'] ) &&
		( $post = get_post( $_GET['p'] ) )
	) {
		wp_safe_redirect( get_permalink( $post ), 301 );
		die();
	}

	// Handle 404 pages where it's a singular theme followed by junk, for example, /themes/twentyten/junk/input/
	if ( is_404() ) {
		$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( preg_match( '!^/themes/([^/]+)/.+!i', $path, $m ) ) {
			$posts = get_posts( [
				'name'        => $m[1],
				'post_type'   => 'repopackage',
				'post_status' => 'publish'
			] );

			if ( $posts ) {
				wp_safe_redirect( get_permalink( $posts[0] ), 301 );
				die();
			}
		}
	}

	// Uppercase characters in URLs tend to lead to broken JS pages.
	// Redirect all paths to the lower-case variant, excluding searches..
	$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	if (
		$path &&
		$path !== strtolower( $path ) &&
		( trailingslashit( $path ) !== '/themes/search/' . get_query_var( 's' ) . '/' )
	) {
		$url = preg_replace(
			'|^' . preg_quote( $path, '|' ) . '|',
			trailingslashit( strtolower( $path ) ),
			$_SERVER['REQUEST_URI']
		);
		wp_safe_redirect( $url, 301 );
		die();
	}

	// Redirect /browse/featured/ to the front-page temporarily, as it's showing in Google results.
	if ( '/themes/browse/featured' === substr( $path, 0, 23 ) ) {
		wp_safe_redirect( home_url( '/' ), 302 );
		die();
	}

	// Ensure all requests are trailingslash'd.
	if ( $path && '/' !== substr( $path, -1 ) && '.xml' !== substr( $path, -4 ) ) {
		$url = str_replace( $path, $path . '/', $_SERVER['REQUEST_URI'] );
		wp_safe_redirect( $url, 301 );
		die();
	}
}

/**
 * Enqueue scripts and styles.
 */
function wporg_themes_scripts() {
	$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	$suffix       = $script_debug ? '' : '.min';

	// Concatenates core scripts when possible.
	if ( ! $script_debug ) {
		$GLOBALS['concatenate_scripts'] = true;
	}

	wp_enqueue_style( 'wporg-themes', get_theme_file_uri( '/css/style.css' ), [ 'dashicons', 'open-sans' ], filemtime( __DIR__ . '/css/style.css' ) );
	wp_style_add_data( 'wporg-themes', 'rtl', 'replace' );

	if ( ! is_singular( 'page' ) ) {
		wp_enqueue_script( 'google-charts-loader', 'https://www.gstatic.com/charts/loader.js', array(), null, true );
		wp_enqueue_script( 'wporg-theme', get_stylesheet_directory_uri() . "/js/theme{$suffix}.js", array( 'wp-backbone' ), filemtime( __DIR__ . "/js/theme{$suffix}.js" ), true );

		// Use the Rosetta-specific site name. Ie. "WordPress.org $LOCALE"
		$title_suffix = isset( $GLOBALS['wporg_global_header_options']['rosetta_title'] ) ? $GLOBALS['wporg_global_header_options']['rosetta_title'] : 'WordPress.org';

		$api_endpoints = [
			'query'    => 'https://api.wordpress.org/themes/info/1.2/',
			'favorite' => 'https://api.wordpress.org/themes/theme-directory/1.0/',
		];
		if ( 'local' === wp_get_environment_type() ) {
			$api_endpoints['query'] = rest_url( 'themes/1.2/query' );
		}

		wp_localize_script( 'wporg-theme', '_wpThemeSettings', array(
			'themes'   => false,
			'query'    => wporg_themes_get_themes_for_query(),
			'settings' => array(
				'title'        => array(
					'default'  => "%s &#124; ${title_suffix}",
					'home'     => __( 'WordPress Themes', 'wporg-themes' ) . " &#124; ${title_suffix}",
					'theme'    => '%s - ' . __( 'WordPress theme', 'wporg-themes' ) . " &#124; ${title_suffix}",
					/* translators: %s: theme author name */
					'author'   => sprintf(
							__( 'Themes by %s', 'wporg-themes' ),
							// The Javascript doesn't handle the author route, so we can just hard-code the author name in here for now.
							is_author() ? ( get_queried_object()->display_name ?: get_queried_object()->user_nicename ) : '%s'
					) . " &#124; ${title_suffix}",
					/* translators: %s: Category/Browse section */
					'tax'      => __( 'WordPress Themes: %s Free', 'wporg-themes' ) . " &#124; ${title_suffix}",
					/* translators: %s: Search term */
					'search'   => __( 'Search Results for &#8220;%s&#8221;', 'wporg-themes' ) . " &#124; ${title_suffix}",
					'notfound' => __( 'Page not found', 'wporg-themes' ) . " &#124; ${title_suffix}",
				),
				'isMobile'     => wp_is_mobile(),
				'postsPerPage' => get_option( 'posts_per_page' ),
				'path'         => trailingslashit( parse_url( home_url(), PHP_URL_PATH ) ),
				'locale'       => get_locale(),
				'favorites'    => array(
					'api'    => $api_endpoints['favorite'],
					'themes' => wporg_themes_get_user_favorites(),
					'nonce'  => is_user_logged_in() ? wp_create_nonce( 'modify-theme-favorite' ) : false,
				),
				'currentUser' => is_user_logged_in() ?
					array(
						'login'    => wp_get_current_user()->user_login,
						'slug'     => wp_get_current_user()->user_nicename,
						'is_admin' => current_user_can( 'edit_posts' ),
					) :
					false,
				'browseDefault'=> WPORG_THEMES_DEFAULT_BROWSE,
				'apiEndpoint'  => $api_endpoints['query'],
			),
			'l10n' => array(
				'locale'            => str_replace( '_', '-', get_locale() ),
				'search'            => __( 'Search Themes', 'wporg-themes' ),
				'searchPlaceholder' => __( 'Search themes...', 'wporg-themes' ), // placeholder (no ellipsis)
				'error'             => __( 'An unexpected error occurred.', 'wporg-themes' ),

				// Downloads Graph
				'date'      => __( 'Date', 'wporg-themes' ),
				'downloads' => __( 'Downloads', 'wporg-themes' ),

				// Tags
				'tags' => wporg_themes_get_tag_translations(),

				/* translators: %s: Name of the pattern */
				'pattern_caption_template' => __( '%s pattern', 'wporg-themes' ),

				'style_variations_title' => __( 'Style variations', 'wporg-themes' ),

				/* translators: %s: Title of the style variation */
				'style_variation_caption_template' => __( '%s style variation', 'wporg-themes' ),

				// Active Installs
				'active_installs_less_than_10' => __( 'Less than 10', 'wporg-themes' ),
				'active_installs_1_million' => __( '1+ million', 'wporg-themes' ),
			),
			'rest' => array(
				'restUrl'   => get_rest_url(),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'themeSlug' => get_post()->post_name ?? '',
			),
		) );
	}

	// No emoji support needed.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	// No Jetpack styles needed.
	add_filter( 'jetpack_implode_frontend_css', '__return_false' );

	/*
	 * No Grofiles needed.
	 *
	 * Enqueued so that it's overridden in the global footer.
	 */
	wp_register_script( 'grofiles-cards', false );
	wp_enqueue_script( 'grofiles-cards' );
}
add_action( 'wp_enqueue_scripts', 'wporg_themes_scripts' );

// Disable mentions script in Theme Directory.
add_filter( 'jetpack_mentions_should_load_ui', '__return_false', 11 );

/**
 * Extend the default WordPress body classes.
 *
 * Adds body classes to
 * 1. denote singular themes.
 * 2. Identify IE8.
 * 3. denote if no themes were found.
 *
 * @param array $classes A list of existing body class values.
 * @return array The filtered body class list.
 */
function wporg_themes_body_class( $classes ) {

	if ( ! is_page() && get_query_var( 'name' ) && ! is_404() ) {
		$classes[] = 'modal-open';
	}

	if ( $GLOBALS['is_IE'] && false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 8' ) ) {
		$classes[] = 'ie8';
	}

	if ( empty( $GLOBALS['themes']['themes'] ) && ! is_singular( 'page' ) ) {
		$classes[] = 'no-results';
	}

	return $classes;
}
add_filter( 'body_class', 'wporg_themes_body_class' );

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
function wporg_themes_document_title( $title ) {
	if ( is_front_page() ) {
		$title['title']   = __( 'WordPress Themes', 'wporg-themes' );
		$title['tagline'] = __( 'WordPress.org', 'wporg-themes' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		/* translators: Category or tag name */
		$title['title'] = sprintf(
			__( 'WordPress Themes: %s Free', 'wporg-themes' ),
			single_term_title( '', false )
		);
	} elseif ( is_author() ) {
		$title['title'] = sprintf(
			__( 'Themes by %s', 'wporg-themes' ),
			get_queried_object()->display_name ?: get_queried_object()->user_nicename
		);
	}

	if ( ! is_front_page() ) {
		if ( is_singular( 'repopackage' ) ) {
			$title['title'] .= ' - ' . __( 'WordPress theme', 'wporg-themes' );
		}
		$title['site'] = __( 'WordPress.org', 'wporg-themes' );
	}

	return $title;
}
add_filter( 'document_title_parts', 'wporg_themes_document_title' );

/**
 * Set the separator for the document title.
 *
 * @return string Document title separator.
 */
add_filter( 'document_title_separator', function() {
	return '&#124;';
} );

/**
 * Adds meta description for front page.
 *
 * @param array $tags Array that consists of meta name and meta content pairs.
 */
function wporg_themes_meta_tags( $tags ) {
	if ( is_front_page() ) {
		$tags['description'] = __( 'Find the perfect theme for your WordPress website. Choose from thousands of stunning designs with a wide variety of features and customization options.', 'wporg-themes' );
	} elseif ( is_author() ) {
		$tags['description'] = sprintf(
			__( 'See all WordPress themes developed by %s.', 'wporg-themes' ),
			get_queried_object()->display_name ?: get_queried_object()->user_nicename
		);
	}

	return $tags;
}
add_filter( 'jetpack_seo_meta_tags', 'wporg_themes_meta_tags' );

/**
 * Overrides feeds to use a custom RSS2 feed which contains the current requests themes.
 */
function wporg_themes_custom_feed() {
	if ( ! is_feed() ) {
		return;
	}
	if ( 'repopackage' != get_query_var( 'post_type' ) ) {
		return;
	}

	include __DIR__ . '/rss.php';
	die();
}
add_filter( 'template_redirect', 'wporg_themes_custom_feed', 9999 );

/**
 * Include view templates in the footer.
 */
function wporg_themes_view_templates() {
	if ( ! is_singular( 'page' ) ) {
		get_template_part( 'view-templates/theme' );
		get_template_part( 'view-templates/theme-preview' );
		get_template_part( 'view-templates/theme-single' );
	}
}
add_action( 'wp_footer', 'wporg_themes_view_templates' );

/**
 * This is a copy of get_theme_feature_list(), but with the wporg-themes text domain
 *
 * @param string $include Optional. Type of list: 'active', 'deprecated' or 'all'. Default 'active'.
 * @return array List of features.
 */
function wporg_themes_get_feature_list( $include = 'active' ) {
	$features = array();

	if ( 'active' === $include || 'all' === $include ) {
		$features = array(
			__( 'Layout', 'wporg-themes' )   => array(
				'grid-layout'   => __( 'Grid Layout', 'wporg-themes' ),
				'one-column'    => __( 'One Column', 'wporg-themes' ),
				'two-columns'   => __( 'Two Columns', 'wporg-themes' ),
				'three-columns' => __( 'Three Columns', 'wporg-themes' ),
				'four-columns'  => __( 'Four Columns', 'wporg-themes' ),
				'left-sidebar'  => __( 'Left Sidebar', 'wporg-themes' ),
				'right-sidebar' => __( 'Right Sidebar', 'wporg-themes' ),
				'wide-blocks'   => __( 'Wide Blocks', 'wporg-themes' ),
			),
			__( 'Features', 'wporg-themes' ) => array(
				'accessibility-ready'   => __( 'Accessibility Ready', 'wporg-themes' ),
				'block-patterns'        => __( 'Block Editor Patterns', 'wporg-themes' ),
				'block-styles'          => __( 'Block Editor Styles', 'wporg-themes' ),
				'buddypress'            => __( 'BuddyPress', 'wporg-themes' ),
				'custom-background'     => __( 'Custom Background', 'wporg-themes' ),
				'custom-colors'         => __( 'Custom Colors', 'wporg-themes' ),
				'custom-header'         => __( 'Custom Header', 'wporg-themes' ),
				'custom-logo'           => __( 'Custom Logo', 'wporg-themes' ),
				'custom-menu'           => __( 'Custom Menu', 'wporg-themes' ),
				'editor-style'          => __( 'Editor Style', 'wporg-themes' ),
				'featured-image-header' => __( 'Featured Image Header', 'wporg-themes' ),
				'featured-images'       => __( 'Featured Images', 'wporg-themes' ),
				'flexible-header'       => __( 'Flexible Header', 'wporg-themes' ),
				'footer-widgets'        => __( 'Footer Widgets', 'wporg-themes' ),
				'front-page-post-form'  => __( 'Front Page Posting', 'wporg-themes' ),
				'full-site-editing'     => __( 'Full Site Editing', 'wporg-themes' ),
				'full-width-template'   => __( 'Full Width Template', 'wporg-themes' ),
				'microformats'          => __( 'Microformats', 'wporg-themes' ),
				'post-formats'          => __( 'Post Formats', 'wporg-themes' ),
				'rtl-language-support'  => __( 'RTL Language Support', 'wporg-themes' ),
				'sticky-post'           => __( 'Sticky Post', 'wporg-themes' ),
				'style-variations'      => __( 'Style Variations', 'wporg-themes' ),
				'template-editing'      => __( 'Template Editing', 'wporg-themes' ),
				'theme-options'         => __( 'Theme Options', 'wporg-themes' ),
				'threaded-comments'     => __( 'Threaded Comments', 'wporg-themes' ),
				'translation-ready'     => __( 'Translation Ready', 'wporg-themes' ),
			),
			__( 'Subject', 'wporg-themes' )  => array(
				'blog'           => __( 'Blog', 'wporg-themes' ),
				'e-commerce'     => __( 'E-Commerce', 'wporg-themes' ),
				'education'      => __( 'Education', 'wporg-themes' ),
				'entertainment'  => __( 'Entertainment', 'wporg-themes' ),
				'food-and-drink' => __( 'Food & Drink', 'wporg-themes' ),
				'holiday'        => __( 'Holiday', 'wporg-themes' ),
				'news'           => __( 'News', 'wporg-themes' ),
				'photography'    => __( 'Photography', 'wporg-themes' ),
				'portfolio'      => __( 'Portfolio', 'wporg-themes' ),
			),
		);
	}

	if ( 'deprecated' === $include || 'all' === $include ) {
		$features[ __( 'Colors', 'wporg-themes' ) ] = array(
			'black'  => __( 'Black', 'wporg-themes' ),
			'blue'   => __( 'Blue', 'wporg-themes' ),
			'brown'  => __( 'Brown', 'wporg-themes' ),
			'gray'   => __( 'Gray', 'wporg-themes' ),
			'green'  => __( 'Green', 'wporg-themes' ),
			'orange' => __( 'Orange', 'wporg-themes' ),
			'pink'   => __( 'Pink', 'wporg-themes' ),
			'purple' => __( 'Purple', 'wporg-themes' ),
			'red'    => __( 'Red', 'wporg-themes' ),
			'silver' => __( 'Silver', 'wporg-themes' ),
			'tan'    => __( 'Tan', 'wporg-themes' ),
			'white'  => __( 'White', 'wporg-themes' ),
			'yellow' => __( 'Yellow', 'wporg-themes' ),
			'dark'   => __( 'Dark', 'wporg-themes' ),
			'light'  => __( 'Light', 'wporg-themes' ),
		);

		if ( 'deprecated' === $include ) {
			// Initialize arrays.
			$features[ __( 'Layout', 'wporg-themes' ) ]   = array();
			$features[ __( 'Features', 'wporg-themes' ) ] = array();
			$features[ __( 'Subject', 'wporg-themes' ) ]  = array();
		}

		$features[ __( 'Layout', 'wporg-themes' ) ] = array_merge( $features[ __( 'Layout', 'wporg-themes' ) ], array(
			'fixed-layout'      => __( 'Fixed Layout', 'wporg-themes' ),
			'fluid-layout'      => __( 'Fluid Layout', 'wporg-themes' ),
			'responsive-layout' => __( 'Responsive Layout', 'wporg-themes' ),
		) );

		$features[ __( 'Features', 'wporg-themes' ) ] = array_merge( $features[ __( 'Features', 'wporg-themes' ) ], array(
			'blavatar' => __( 'Blavatar', 'wporg-themes' ),
		) );

		$features[ __( 'Subject', 'wporg-themes' ) ] = array_merge( $features[ __( 'Subject', 'wporg-themes' ) ], array(
			'photoblogging' => __( 'Photoblogging', 'wporg-themes' ),
			'seasonal'      => __( 'Seasonal', 'wporg-themes' ),
		) );
	}

	return $features;
}

/**
 * Returns an array of [ tag_slug => translated_tag_name] tags for translation within JS
 *
 * @return array List of features.
 */
function wporg_themes_get_tag_translations() {
	$translations = array();
	foreach ( wporg_themes_get_feature_list( 'all' ) as $group => $tags ) {
		$translations = array_merge( $translations, $tags );
	}
	return $translations;
}

/**
 * Prints markup information in the head of a page.
 *
 * @link http://schema.org/SoftwareApplication
 * @link https://developers.google.com/search/docs/data-types/software-apps
 */
function wporg_themes_json_ld_schema() {
	$schema = false;

	// Schema for the front page.
	if ( is_front_page() ) {
		$schema = [
			"@context" => "http://schema.org",
			"@type"    => "WebSite",
			"name"     => __( 'WordPress Themes', 'wporg-themes' ),
			"url"      => home_url( '/' ),
			"potentialAction" => [
				[
					"@type"       => "SearchAction",
					"target"      => home_url( '/search/{search_term_string}' ),
					"query-input" => "required name=search_term_string"
				]
			]
		];

	// Schema for theme pages.
	} elseif ( is_singular( 'repopackage' ) && 'publish' === get_post_status( get_queried_object_id() ) ) {
		$schema = wporg_themes_json_jd_schema( get_queried_object() );
	}

	// Print the schema.
	if ( $schema ) {
		echo PHP_EOL, '<script type="application/ld+json">', PHP_EOL;
		// Output URLs without escaping the slashes, and print it human readable.
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		echo PHP_EOL, '</script>', PHP_EOL;
	}
}
add_action( 'wp_head', 'wporg_themes_json_ld_schema' );

/**
 * Fetches JSON LD schema for a specific theme.
 *
 * @static
 *
 * @param \WP_Post $post Plugin to output JSON LD Schema for.
 * @return array Schema object.
 */
function wporg_themes_json_jd_schema( $post ) {

	$theme = wporg_themes_theme_information( $post->post_name );

	$schema = [];

	// Add the theme 'SoftwareApplication' node.
	$software_application = [
		"@context"            => "http://schema.org",
		"@type"               => [
			"SoftwareApplication",
			"Product"
		],
		"applicationCategory" => "OtherApplication",
		"operatingSystem"     => "WordPress",
		"name"                => $theme->name,
		"url"                 => get_permalink( $post ),
		"description"         => $theme->description,
		"softwareVersion"     => $theme->version,
		"fileFormat"          => "application/zip",
		"downloadUrl"         => $theme->download_link,
		"dateModified"        => get_post_modified_time( 'c', false, $post ),
		"aggregateRating"     => [
			"@type"       => "AggregateRating",
			"worstRating" => 1,
			"bestRating"  => 5,
			"ratingValue" => round( $theme->rating / 20 / 0.5 )*0.5,
			"ratingCount" => (int) $theme->num_ratings,
			"reviewCount" => (int) $theme->num_ratings,
		],
		"interactionStatistic" => [
			"@type"                => "InteractionCounter",
			"interactionType"      => "http://schema.org/DownloadAction",
			"userInteractionCount" => $theme->downloaded,
		],
		"image" => $theme->screenshot_url,
		"offers" => [
			"@type"         => "Offer",
			"url"           => get_permalink( $post ),
			"price"         => "0.00",
			"priceCurrency" => "USD",
			"seller"        => [
				"@type" => "Organization",
				"name"  => "WordPress.org",
				"url"   => "https://wordpress.org"
			]
		]
	];

	// Remove the aggregateRating node if there's no reviews.
	if ( ! $software_application['aggregateRating']['ratingCount'] ) {
		unset( $software_application['aggregateRating'] );
	}

	$schema[] = $software_application;

	return $schema;
}

/**
 * Use the index.php template for various WordPress views that would otherwise be handled by the parent theme.
 */
function use_index_php_as_template() {
	return __DIR__ . '/index.php';
}
add_filter( 'single_template',  'use_index_php_as_template' );
add_filter( 'archive_template', 'use_index_php_as_template' );
