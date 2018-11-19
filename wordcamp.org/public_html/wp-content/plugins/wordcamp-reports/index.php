<?php
/**
 * Plugin Name:     WordCamp Reports
 * Plugin URI:      https://wordcamp.org
 * Description:     Automated reports for WordCamp.org.
 * Author:          WordCamp.org
 * Author URI:      https://wordcamp.org
 * Version:         1
 *
 * @package         WordCamp\Reports
 */

namespace WordCamp\Reports;
defined( 'WPINC' ) || die();

use WordCamp\Reports\Report;

define( __NAMESPACE__ . '\PLUGIN_DIR', \plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_URL', \plugins_url( '/', __FILE__ ) );

/**
 * Get the path for the includes directory.
 *
 * @return string Path with trailing slash.
 */
function get_classes_dir_path() {
	return trailingslashit( PLUGIN_DIR ) . 'classes/';
}

/**
 * Get the path for the includes directory.
 *
 * @return string Path with trailing slash.
 */
function get_includes_dir_path() {
	return trailingslashit( PLUGIN_DIR ) . 'includes/';
}

/**
 * Get the path for the views directory.
 *
 * @return string Path with trailing slash.
 */
function get_views_dir_path() {
	return trailingslashit( PLUGIN_DIR ) . 'views/';
}

/**
 * Get the path for the assets directory.
 *
 * @return string Path with trailing slash.
 */
function get_assets_dir_path() {
	return trailingslashit( PLUGIN_DIR ) . 'assets/';
}

/**
 * Get the URL for the assets directory.
 *
 * @return string URL with trailing slash.
 */
function get_assets_url() {
	return trailingslashit( PLUGIN_URL ) . 'assets/';
}

/**
 * Autoload all the files in the includes directory.
 *
 * @return void
 */
function load_includes() {
	foreach ( glob( get_includes_dir_path() . '*.php' ) as $filename ) {
		if ( is_readable( $filename ) ) {
			include_once ( $filename );
		}
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\load_includes' );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class The fully-qualified class name.
 *
 * @return void
 */
spl_autoload_register( function( $class ) {
	// Project-specific namespace prefix.
	$prefix = 'WordCamp\\Reports\\';

	// Base directory for the namespace prefix.
	$base_dir = get_classes_dir_path();

	// Does the class use the namespace prefix?
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		// No, move to the next registered autoloader.
		return;
	}

	// Get the relative class name.
	$relative_class = substr( $class, $len );

	// Convert the relative class name to a relative path.
	$relative_path_parts = explode( '\\', $relative_class );
	$filename = 'class-' . array_pop( $relative_path_parts );
	$relative_path = implode( '/', $relative_path_parts ) . "/$filename.php";
	$relative_path = strtolower( $relative_path );
	$relative_path = str_replace( '_', '-', $relative_path );

	$file = $base_dir . $relative_path;

	// If the file exists, require it.
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * A list of available report classes.
 *
 * @todo Maybe parse the classes/report directory and generate this dynamically?
 *
 * @return array
 */
function get_report_classes() {
	return array(
		__NAMESPACE__ . '\Report\Ticket_Revenue',
		__NAMESPACE__ . '\Report\Sponsor_Invoices',
		__NAMESPACE__ . '\Report\Payment_Activity',
		__NAMESPACE__ . '\Report\Sponsorship_Grants',
		__NAMESPACE__ . '\Report\WordCamp_Status',
		__NAMESPACE__ . '\Report\WordCamp_Details',
		__NAMESPACE__ . '\Report\Meetup_Groups',
		__NAMESPACE__ . '\Report\Meetup_Events',
		__NAMESPACE__ . '\Report\WordCamp_Payment_Methods',
		__NAMESPACE__ . '\Report\Meetup_Status',
		__NAMESPACE__ . '\Report\Meetup_Details',
		__NAMESPACE__ . '\Report\WordCamp_Counts',
	);
}


function get_report_groups( $classes = array() ) {
	$groups = [
		'finance'  => [
			'label'   => 'Finances',
			'classes' => [],
		],
		'wordcamp'  => [
			'label'   => 'WordCamps',
			'classes' => [],
		],
		'meetup'  => [
			'label'   => 'Meetups',
			'classes' => [],
		],
		'misc'  => [
			'label'   => 'Miscellaneous',
			'classes' => [],
		],
	];

	if ( empty( $classes ) ) {
		$classes = get_report_classes();
	}

	foreach ( $classes as $class ) {
		if ( property_exists( $class, 'group' ) && array_key_exists( $class::$group, $groups ) ) {
			$groups[ $class::$group ]['classes'][] = $class;
		} else {
			$groups['misc']['classes'][] = $class;
		}
	}

	return $groups;
}

/**
 * Register the Reports page in the WP Admin.
 *
 * @hook action admin_menu
 *
 * @return void
 */
function add_reports_page() {
	\add_submenu_page(
		'index.php',
		__( 'Reports', 'wordcamporg' ),
		__( 'Reports', 'wordcamporg' ),
		'manage_network',
		'wordcamp-reports',
		__NAMESPACE__ . '\render_page'
	);
}

add_action( 'admin_menu', __NAMESPACE__ . '\add_reports_page' );

/**
 * Render the main Reports page or use an appropriate class method to
 * render a particular child report page.
 *
 * @return void
 */
function render_page() {
	$report       = filter_input( INPUT_GET, 'report', FILTER_SANITIZE_STRING );
	$report_class = get_report_class_by_slug( $report );

	$reports_with_admin = array_filter( get_report_classes(), function( $class ) {
		if ( ! method_exists( $class, 'render_admin_page' ) ) {
			return false;
		}

		return true;
	} );

	if ( $report_class && in_array( $report_class, $reports_with_admin, true ) ) {
		$report_class::render_admin_page();
	} else {
		$report_groups = get_report_groups( $reports_with_admin );

		include get_views_dir_path() . 'admin.php';
	}
}

/**
 * Enqueue JS and CSS assets for a particular report's admin interface, if it has any.
 *
 * @param string $hook_suffix The ID of the current admin page.
 */
function enqueue_admin_assets( $hook_suffix ) {
	if ( 'dashboard_page_wordcamp-reports' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'admin-common',
		get_assets_url() . 'css/admin-common.css',
		array(),
		filemtime( get_assets_dir_path() . 'css/admin-common.css' )
	);

	$report       = filter_input( INPUT_GET, 'report', FILTER_SANITIZE_STRING );
	$report_class = get_report_class_by_slug( $report );

	if ( ! is_null( $report_class ) && method_exists( $report_class, 'enqueue_admin_assets' ) ) {
		$report_class::enqueue_admin_assets();
	}
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_assets' );

/**
 * Determine the class used for a report based on a given string ID.
 *
 * @param string $report_slug String identifying a particular report class.
 *
 * @return Report\Base|null
 */
function get_report_class_by_slug( $report_slug ) {
	$report_classes = get_report_classes();

	$report_slugs = array_map( function( $class ) {
		return $class::$slug;
	}, $report_classes );

	$reports = array_combine( $report_slugs, $report_classes );

	if ( isset( $reports[ $report_slug ] ) ) {
		return $reports[ $report_slug ];
	}

	return null;
}

/**
 * Get the URL for a Reports-related page.
 *
 * @param string $report_slug The slug string for a particular report.
 *
 * @return string
 */
function get_page_url( $report_slug = '' ) {
	$url = add_query_arg( array( 'page' => 'wordcamp-reports' ), admin_url( 'index.php' ) );

	if ( $report_slug ) {
		$url = add_query_arg( array( 'report' => sanitize_key( $report_slug ) ), $url );
	}

	return $url;
}

/**
 * Register shortcodes for reports that have a public interface.
 *
 * @return void
 */
function register_shortcodes() {
	$report_classes = get_report_classes();

	foreach ( $report_classes as $class ) {
		if ( property_exists( $class, 'shortcode_tag' ) && method_exists( $class, 'handle_shortcode' ) ) {
			add_shortcode( $class::$shortcode_tag, array( $class, 'handle_shortcode' ) );
		}
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\register_shortcodes' );

/**
 * Register endpoints for reports that have a REST API interface.
 *
 * @return void
 */
function register_rest_endpoints() {
	$namespace = 'wordcamp-reports/v1';

	$report_classes = get_report_classes();

	foreach ( $report_classes as $class ) {
		if ( property_exists( $class, 'rest_base' ) && method_exists( $class, 'rest_callback' ) ) {
			register_rest_route( $namespace, '/' . $class::$rest_base, array(
				'methods'  => array( 'GET' ),
				'callback' => array( $class, 'rest_callback' ),
			) );
		}
	}
}

add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_endpoints' );

/**
 * Add action hooks for methods that emit data files.
 *
 * @return void
 */
function register_file_exports() {
	$report_classes = get_report_classes();

	foreach ( $report_classes as $class ) {
		if ( method_exists( $class, 'export_to_file' ) ) {
			add_action( 'admin_init', array( $class, 'export_to_file' ) );
		}
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\register_file_exports' );

/**
 * A list of IDs for sites that should not be included in report results.
 *
 * @return array
 */
function get_excluded_site_ids() {
	return array(
		206, // 2016.testing
		928, // 2017.testing
	);
}
