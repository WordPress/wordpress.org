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

	add_theme_support( 'automatic-feed-links' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'wporg-themes' ),
	) );

	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
	) );
}
add_action( 'after_setup_theme', 'wporg_themes_setup' );

/**
 * Enqueue scripts and styles.
 */
function wporg_themes_scripts() {

	wp_enqueue_style( 'ratings', '//wordpress.org/extend/themes-plugins/bb-ratings/bb-ratings.css', array(), '4' );
	wp_enqueue_style( 'themes-style', self_admin_url( 'css/themes.css' ) );
	wp_enqueue_style( 'wporg-themes-style', get_stylesheet_uri() );

	wp_enqueue_script( 'google-jsapi', '//www.google.com/jsapi', array( 'jquery' ), null );

	if ( ! is_singular( 'page' ) ) {
		wp_enqueue_script( 'theme', self_admin_url( 'js/theme.js' ), array( 'wp-backbone' ), false, true );
		wp_enqueue_script( 'wporg-theme', get_template_directory_uri() . '/js/theme.js', array( 'theme' ), filemtime( __DIR__ . '/js/theme.js' ), true );

		wp_localize_script( 'theme', '_wpThemeSettings', array(
			'themes'   => false,
			'settings' => array(
				'title'      => __( 'WordPress &#8250; %s &laquo; Free WordPress Themes', 'wporg-themes' ),
				'isMobile'   => wp_is_mobile(),
				'isInstall'  => true,
				'canInstall' => false,
				'installURI' => null,
				'adminUrl'   => trailingslashit( parse_url( home_url(), PHP_URL_PATH ) ),
			),
			'l10n' => array(
				'addNew'            => __( 'Add New Theme' ),
				'search'            => __( 'Search Themes' ),
				'searchPlaceholder' => __( 'Search themes...' ), // placeholder (no ellipsis)
				'upload'            => __( 'Upload Theme' ),
				'back'              => __( 'Back' ),
				'error'             => __( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="https://wordpress.org/support/">support forums</a>.' ),

				// Downloads Graph
				'date'      => __( 'Date' ),
				'downloads' => __( 'Downloads' ),
			),
			'installedThemes' => array(),
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'wporg_themes_scripts' );

/**
 * Create a nicely formatted and more specific title element text for output
 * in head of document, based on current view.
 *
 * @global int $paged WordPress archive pagination page count.
 * @global int $page  WordPress paginated post page count.
 *
 * @param string $title Default title text for current view.
 * @param string $sep Optional separator.
 * @return string The filtered title.
 */
function wporg_themes_wp_title( $title, $sep ) {
	global $paged, $page;

	if ( is_feed() ) {
		return $title;
	}

	// Add the site name.
	$title .= get_bloginfo( 'name', 'display' );

	// Add the site description for the home/front page.
	$site_description = get_bloginfo( 'description', 'display' );
	if ( $site_description && ( is_home() || is_front_page() ) ) {
		$title = "$title $sep $site_description";
	}

	// Add a page number if necessary.
	if ( ( $paged >= 2 || $page >= 2 ) && ! is_404() ) {
		$title = "$title $sep " . sprintf( __( 'Page %s', 'wporg-themes' ), max( $paged, $page ) );
	}

	return $title;
}
add_filter( 'wp_title', 'wporg_themes_wp_title', 10, 2 );

/**
 * @param  object $args
 * @param  string $action
 *
 * @return array
 */
function wporg_themes_api_args( $args, $action ) {
	if ( in_array( $action, array( 'query_themes', 'theme_information' ) ) ) {
		$args->per_page = 30;
		$args->fields['parent']  = true;
		$args->fields['ratings'] = true;
		$args->fields['tags']    = true;
	}

	return $args;
}
add_filter( 'themes_api_args', 'wporg_themes_api_args', 10, 2 );

/**
 * Removes Core's built-in query-themes handler, so we can safely add ours later on.
 */
function wporg_themes_remove_ajax_action() {
	remove_action( 'wp_ajax_query-themes', 'wp_ajax_query_themes', 1 );
}
add_action( 'wp_ajax_query-themes', 'wporg_themes_remove_ajax_action', -1 );

/**
 * A recreation of Core's implementation without capability check, since there is nothing to install.
 */
function wporg_themes_query_themes() {
	$request = wp_unslash( $_REQUEST['request'] );
	$request['fields'] = wp_parse_args( $request['fields'], array(
		'description'  => true,
		'sections'     => false,
		'tested'       => true,
		'requires'     => true,
		'rating'       => true,
		'ratings'      => true,
		'downloaded'   => true,
		'downloadlink' => true,
		'last_updated' => true,
		'homepage'     => true,
		'tags'         => true,
		'num_ratings'  => true,
		'parent'       => true,
	) );
	$args = wp_parse_args( $request, array(
		'per_page' => 20,
	) );

	include_once API_WPORGPATH . 'themes/info/1.0/class-themes-api.php';
	$api = new Themes_API( 'query_themes', $args );
	$api = $api->response;

	if ( is_wp_error( $api ) ) {
		wp_send_json_error();
	}

	foreach ( $api->themes as $key => $theme ) {
		$api->themes[ $key ] = wporg_themes_ajax_prepare_theme( $theme );
	}

	wp_send_json_success( $api );
}
add_action( 'wp_ajax_query-themes',        'wporg_themes_query_themes' );
add_action( 'wp_ajax_nopriv_query-themes', 'wporg_themes_query_themes' );

function wporg_themes_theme_info() {
	$request = wp_unslash( $_REQUEST['request'] );
	$request['fields'] = wp_parse_args( $request['fields'], array(
		'description'  => true,
		'sections'     => false,
		'tested'       => true,
		'requires'     => true,
		'rating'       => true,
		'ratings'      => true,
		'downloaded'   => true,
		'downloadlink' => true,
		'last_updated' => true,
		'homepage'     => true,
		'tags'         => true,
		'num_ratings'  => true,
		'parent'       => true,
	) );

	include_once API_WPORGPATH . 'themes/info/1.0/class-themes-api.php';
	$api = new Themes_API( 'theme_information', $request );
	$api = $api->response;

	if ( empty( $api ) ) {
		wp_send_json_error();
	}

	$api = wporg_themes_ajax_prepare_theme( $api );

	wp_send_json_success( $api );
}
add_action( 'wp_ajax_theme-info',        'wporg_themes_theme_info' );
add_action( 'wp_ajax_nopriv_theme-info', 'wporg_themes_theme_info' );

function wporg_themes_ajax_prepare_theme( $theme ) {
	global $themes_allowedtags;
	if ( empty( $themes_allowedtags ) ) {
		$themes_allowedtags = array(
			'a'       => array( 'href' => array(), 'title' => array(), 'target' => array() ),
			'abbr'    => array( 'title' => array() ),
			'acronym' => array( 'title' => array() ),
			'code'    => array(),
			'pre'     => array(),
			'em'      => array(),
			'strong'  => array(),
			'div'     => array(),
			'p'       => array(),
			'ul'      => array(),
			'ol'      => array(),
			'li'      => array(),
			'h1'      => array(),
			'h2'      => array(),
			'h3'      => array(),
			'h4'      => array(),
			'h5'      => array(),
			'h6'      => array(),
			'img'     => array( 'src' => array(), 'class' => array(), 'alt' => array() ),
		);
	}

	$author        = get_user_by( 'login', $theme->author );
	$theme->author = new StdClass;
	foreach ( array( 'user_nicename', 'display_name' ) as $property ) {
		$theme->author->$property = get_the_author_meta( $property, $author->ID );
	}

	$theme->name        = wp_kses( $theme->name, $themes_allowedtags );
	$theme->version     = wp_kses( $theme->version, $themes_allowedtags );
	$theme->description = wp_kses( $theme->description, $themes_allowedtags );
	$theme->downloaded  = number_format_i18n( $theme->downloaded );
	$theme->num_ratings = number_format_i18n( $theme->num_ratings );
	$theme->preview_url = set_url_scheme( $theme->preview_url );

	if ( preg_match( '/screenshot.(jpg|jpeg|png|gif)/', $theme->screenshot_url, $match ) ) {
		$theme->screenshot_url = sprintf( 'https://i0.wp.com/themes.svn.wordpress.org/%1$s/%2$s/%3$s',
			$theme->slug,
			$theme->version,
			$match[0]
		);
	}

	return $theme;
}

/**
 * Include view templates in the footer.
 */
function wporg_themes_view_templates() {
	get_template_part( 'view-templates/theme' );
	get_template_part( 'view-templates/theme-preview' );
	get_template_part( 'view-templates/theme-single' );
}
add_action( 'wp_footer', 'wporg_themes_view_templates' );
