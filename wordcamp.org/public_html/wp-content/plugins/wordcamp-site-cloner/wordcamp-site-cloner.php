<?php

/*
Plugin Name: WordCamp Site Cloner
Description: Allows organizers to clone another WordCamp's theme and custom CSS as a starting point for their site.
Version:     0.2
Author:      WordCamp.org
Author URI:  http://wordcamp.org
License:     GPLv2 or later
*/

namespace WordCamp\Site_Cloner;
defined( 'WPINC' ) or die();

const PRIME_SITES_CRON_ACTION      = 'wcsc_prime_sites';
const WORDCAMP_SITES_TRANSIENT_KEY = 'wcsc_sites';

/**
 * Initialization
 */
function initialize() {
	// We rely on the Custom CSS module being available
	if ( ! class_exists( '\Jetpack' ) ) {
		return;
	}

	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\register_scripts'               );
	add_action( 'admin_menu',            __NAMESPACE__ . '\add_submenu_page'               );
	add_action( 'customize_register',    __NAMESPACE__ . '\register_customizer_components' );
	add_action( 'rest_api_init',         __NAMESPACE__ . '\register_api_endpoints'         );
	add_action( PRIME_SITES_CRON_ACTION, __NAMESPACE__ . '\prime_wordcamp_sites'           );

	if ( ! wp_next_scheduled( PRIME_SITES_CRON_ACTION ) ) {
		wp_schedule_event( time(), 'daily', PRIME_SITES_CRON_ACTION );
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\initialize' ); // After Jetpack has loaded

/**
 * Register scripts and styles
 */
function register_scripts() {
	wp_register_style(
		'wordcamp-site-cloner',
		plugin_dir_url( __FILE__ ) . 'wordcamp-site-cloner.css',
		array(),
		2
	);

	wp_register_script(
		'wordcamp-site-cloner',
		plugin_dir_url( __FILE__ ) . 'wordcamp-site-cloner.js',
		array( 'jquery', 'customize-controls', 'wp-backbone' ),
		2,
		true
	);

	wp_localize_script(
		'wordcamp-site-cloner',
		'_wcscSettings',
		array(
			'apiUrl'        => get_rest_url( null, '/wordcamp-site-cloner/v1/sites/' ),
			'customizerUrl' => admin_url( 'customize.php' ),
			'themes'        => get_available_themes(),
		)
	);
}

/**
 * Get all of the available themes
 *
 * @return array
 */
function get_available_themes() {
	/** @var \WP_Theme $theme */
	$available_themes = array();
	$raw_themes       = wp_get_themes( array( 'allowed' => true ) );

	foreach ( $raw_themes as $theme ) {
		$theme_name         = $theme->display( 'Name' );
		$available_themes[] = array(
			'slug' => $theme->get_stylesheet(),
			'name' => $theme_name ?: $theme->get_stylesheet()
		);
	}

	return $available_themes;
}

/**
 * Add a submenu page
 *
 * This helps organizers to realize that this tool exists, because they otherwise wouldn't see it unless
 * they opened the Customizer.
 */
function add_submenu_page() {
	\add_submenu_page(
		'themes.php',
		__( 'Clone Another WordCamp', 'wordcamporg' ),
		__( 'Clone Another WordCamp', 'wordcamporg' ),
		'switch_themes',
		'customize.php?autofocus[section]=wcsc_sites'
	);
}

/**
 * Register our Customizer settings, panels, sections, and controls
 *
 * @param \WP_Customize_Manager $wp_customize
 */
function register_customizer_components( $wp_customize ) {
	require_once( __DIR__ . '/includes/source-site-id-setting.php' );
	require_once( __DIR__ . '/includes/site-control.php' );

	$wp_customize->add_setting( new Source_Site_ID_Setting(
		$wp_customize,
		'wcsc_source_site_id',
		array( 'capability' => 'switch_themes' )
	) );

	$wp_customize->add_section(
		'wcsc_sites',
		array(
			'title'      => __( 'Clone Another WordCamp', 'wordcamporg' ),
			'capability' => 'switch_themes'
		)
	);

	$wp_customize->add_control( new Site_Control(
		$wp_customize,
		'wcsc_site_search',
		array(
			'type'     => 'wcscSearch',
			'label'    => __( 'Search', 'wordcamporg' ),
			'settings' => 'wcsc_source_site_id',
			'section'  => 'wcsc_sites'
		)
	) );
}

/**
 * Register the REST API endpoint for the Customizer to use to retriever the site list
 */
function register_api_endpoints() {
	if ( ! current_user_can( 'switch_themes' ) ) {
		return;

		// todo - use `permission_callback` instead
	}

	register_rest_route(
		'wordcamp-site-cloner/v1',
		'/sites',
		array(
			'methods'  => 'GET',
			'callback' => __NAMESPACE__ . '\sites_endpoint',
		)
	);
}

/**
 * Handle the response for the Sites endpoint
 *
 * This always pulls cached data, because Central needs to be the site generating it. See get_wordcamp_sites().
 *
 * @return array
 */
function sites_endpoint() {
	$sites        = array();
	$cached_sites = get_site_transient( WORDCAMP_SITES_TRANSIENT_KEY );

	if ( $cached_sites ) {
		unset( $cached_sites[ get_current_blog_id() ] );

	    $sites = array_values( $cached_sites );
	}

	return $sites;
}

/**
 * Prime the cache of cloneable WordCamp sites
 *
 * This is called via WP Cron.
 *
 * @todo - Reintroduce batching from `1112.3.diff` to get more than 500 sites. Will need to fix transient bug
 * mentioned in `get_wordcamp_sites()` first.
 */
function prime_wordcamp_sites() {
	// This only needs to run on a single site, then the whole network can use the cached result
	if ( ! is_main_site() ) {
		return;
	}

	// Keep the cache longer than needed, just to be sure that it doesn't expire before the cron job runs again
	set_site_transient( WORDCAMP_SITES_TRANSIENT_KEY, get_wordcamp_sites(), DAY_IN_SECONDS * 2 );
}

/**
 * Get WordCamp sites that are suitable for cloning
 *
 * @return array
 */
function get_wordcamp_sites() {
	/*
	 * The post statuses that \WordCamp_Loader::get_public_post_statuses() returns are only created on Central,
	 * because the plugin isn't active on any other sites.
	 */
	if ( ! is_main_site() ) {
		return array();
	}

	if ( ! \Jetpack::is_module_active( 'custom-css' ) ) {
		\Jetpack::activate_module( 'custom-css', false, false );
	}

	switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org

	$wordcamp_query = new \WP_Query( array(
		/*
		 * todo - There's a bug where a `posts_per_page` value greater than ~250-300 will result in
		 * `set_site_transient()` calling `add_site_option()` rather than `update_site_option()`,
		 * and then `get_site_transient()` fails, so `sites_endpoint()` returns an empty array.
		 */
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => \WordCamp_Loader::get_public_post_statuses(),
		'posts_per_page' => 250,
		'meta_key'       => 'Start Date (YYYY-mm-dd)',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',

		'meta_query' => array(
			array(
				// New sites won't have finished designs, so ignore them
				'key'     => 'Start Date (YYYY-mm-dd)',
				'value'   => strtotime( 'now - 1 month' ),
				'compare' => '<'
			)
		),
	) );

	$sites = get_filtered_wordcamp_sites( $wordcamp_query->get_posts() );

	uasort( $sites, __NAMESPACE__ . '\sort_sites_by_year' );

	restore_current_blog();

	return $sites;
}

/**
 * Filter out sites that aren't relevant to the Cloner
 *
 * @param array $wordcamps
 *
 * @return array
 */
function get_filtered_wordcamp_sites( $wordcamps ) {
	$sites = array();

	foreach ( $wordcamps as $wordcamp ) {
		$site_id    = get_wordcamp_site_id( $wordcamp );
		$site_url   = get_post_meta( $wordcamp->ID, 'URL',                     true );
		$start_date = get_post_meta( $wordcamp->ID, 'Start Date (YYYY-mm-dd)', true );

		if ( ! $site_id || ! $site_url || ! $start_date ) {
			continue;
		}

		switch_to_blog( $site_id );

		/*
		 * Sites with Coming Soon enabled probably don't have a finished design yet, so there's no point in
		 * cloning it.
		 */
		if ( ! coming_soon_plugin_enabled() ) {
			$preprocessor = \Jetpack_Custom_CSS::get_preprocessor();
			$preprocessor = isset( $preprocessor[ 'name' ] ) ? $preprocessor[ 'name' ] : 'none';

			$sites[ $site_id ] = array(
				'site_id'          => $site_id,
				'name'             => get_wordcamp_name(),
				'theme_slug'       => get_stylesheet(),
				'screenshot_url'   => get_screenshot_url( $site_url ),
				'year'             => date( 'Y', $start_date ),
				'css_preprocessor' => $preprocessor,
			);
		}

		restore_current_blog();
	}

	return $sites;
}

/**
 * Determine if the Coming Soon plugin is enabled for the current site
 *
 * @return bool
 */
function coming_soon_plugin_enabled() {
	global $WCCSP_Settings;
	$enabled = false;

	if ( ! is_callable( 'WCCSP_Settings::get_settings' ) ) {
		return $enabled;
	}

	// We may need to instantiate the class if this is the first time calling this function
	if ( ! is_a( $WCCSP_Settings, 'WCCSP_Settings' ) ) {
		$WCCSP_Settings = new \WCCSP_Settings();
	}

	$settings = $WCCSP_Settings->get_settings();

	if ( isset( $settings[ 'enabled' ] ) && 'on' === $settings[ 'enabled' ] ) {
		$enabled = true;
	}

	return $enabled;
}

/**
 * Get the mShot URL for the given site URL
 *
 * Allow it to be filtered so that production URLs can be changed to match development URLs in local environments.
 *
 * @param string $site_url
 *
 * @return string
 */
function get_screenshot_url( $site_url ) {
	$screenshot_url = add_query_arg( 'w', 275, 'https://www.wordpress.com/mshots/v1/' . rawurlencode( $site_url ) );

	return apply_filters( 'wcsc_site_screenshot_url', $screenshot_url );
}

/**
 * Sort arrays by the year
 *
 * @param array $site_a
 * @param array $site_b
 *
 * @return int
 */
function sort_sites_by_year( $site_a, $site_b ) {
	if ( $site_a[ 'year' ] === $site_b[ 'year' ] ) {
		return 0;
	}

	return ( $site_a[ 'year' ] < $site_b[ 'year' ] ? 1 : -1 );
}
