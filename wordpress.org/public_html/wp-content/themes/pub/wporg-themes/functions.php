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
//	load_theme_textdomain( 'wporg-themes', get_template_directory() . '/languages' );
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

	wp_enqueue_style( 'global-style', '//s.w.org/style/wp4.css', array(), '14' );
	wp_enqueue_style( 'themes-style', self_admin_url( 'css/themes.css' ) );
	wp_enqueue_style( 'wporg-themes-style', get_stylesheet_uri() );


	wp_enqueue_script( 'google-jsapi', '//www.google.com/jsapi', array(), null );

	wp_enqueue_script( 'theme', self_admin_url( 'js/theme.js' ), array( 'wp-backbone' ), false, 1 );
	wp_enqueue_script( 'wporg-theme', get_template_directory_uri() . '/js/theme.js', array( 'theme' ), false, 1 );

	wp_localize_script( 'theme', '_wpThemeSettings', array(
		'themes'   => false,
		'settings' => array(
			'isInstall'  => true,
			'canInstall' => false,
			'installURI' => null,
			'adminUrl'   => '',
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
add_action( 'wp_enqueue_scripts', 'wporg_themes_scripts' );

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
	global $themes_allowedtags, $theme_field_defaults;

	$args = wp_parse_args( wp_unslash( $_REQUEST['request'] ), array(
		'per_page' => 20,
		'fields'   => $theme_field_defaults,
	) );

	$old_filter = isset( $args['browse'] ) ? $args['browse'] : 'search';

	/** This filter is documented in wp-admin/includes/class-wp-theme-install-list-table.php */
	$args = apply_filters( 'install_themes_table_api_args_' . $old_filter, $args );

	$api = themes_api( 'query_themes', $args );

	if ( is_wp_error( $api ) ) {
		wp_send_json_error();
	}

	foreach ( $api->themes as &$theme ) {
		$theme->name        = wp_kses( $theme->name,        $themes_allowedtags );
		$theme->author      = wp_kses( $theme->author,      $themes_allowedtags );
		$theme->version     = wp_kses( $theme->version,     $themes_allowedtags );
		$theme->description = wp_kses( $theme->description, $themes_allowedtags );
		$theme->num_ratings = number_format_i18n( $theme->num_ratings );
		$theme->preview_url = set_url_scheme( $theme->preview_url );
	}

	wp_send_json_success( $api );
}
add_action( 'wp_ajax_query-themes',        'wporg_themes_query_themes' );
add_action( 'wp_ajax_nopriv_query-themes', 'wporg_themes_query_themes' );

/**
 * Include view templates in the footer.
 */
function wporg_themes_view_templates() {
	get_template_part( 'view-templates/theme' );
	get_template_part( 'view-templates/theme-preview' );
	get_template_part( 'view-templates/theme-single' );
}
add_action( 'wp_footer', 'wporg_themes_view_templates' );
