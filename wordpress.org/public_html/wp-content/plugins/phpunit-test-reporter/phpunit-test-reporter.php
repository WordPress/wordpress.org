<?php
/**
 * Plugin Name:     PHPUnit Test Reporter
 * Plugin URI:      https://make.wordpress.org/hosting/test-results/
 * Description:     Captures and displays test results from the PHPUnit Test Runner
 * Author:          WordPress Hosting Community
 * Author URI:      https://make.wordpress.org/hosting/
 * Text Domain:     ptr
 * Domain Path:     /languages
 * Version:         0.1.3
 * License:         GPL v3
 *
 * @package         PTR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once dirname( __FILE__ ) . '/src/class-content-model.php';
require_once dirname( __FILE__ ) . '/src/class-restapi.php';
require_once dirname( __FILE__ ) . '/src/class-display.php';

add_action( 'init', array( 'PTR\Content_Model', 'action_init_register_post_type' ) );
add_action( 'init', array( 'PTR\Content_Model', 'action_init_register_role' ) );
add_action( 'init', array( 'PTR\Display', 'action_init_register_shortcode' ) );
add_action( 'get_post_metadata', array( 'PTR\Display', 'filter_get_post_metadata' ), 10, 4 );
add_action( 'body_class', array( 'PTR\Display', 'filter_body_class' ) );
add_action( 'post_class', array( 'PTR\Display', 'filter_post_class' ) );
add_action( 'the_content', array( 'PTR\Display', 'filter_the_content' ) );
add_action( 'rest_api_init', array( 'PTR\RestAPI', 'register_routes' ) );

add_action( 'load-edit.php', 'ptr_load_edit_php' );

/**
 * Override the post type list table.
 *
 * The Results post type Quick Edit 'Page Parent' dropdown is tens of thousands of items long,
 * and causes PHP OOM errors.
 * This replaces it with a variant that doesn't support inline editing.. through a very non-conventional method.
 */
function ptr_load_edit_php() {
	if ( ! isset( $_GET['post_type'] ) || 'result' != $_GET['post_type'] ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
	require_once __DIR__ . '/src/class-posts-list-table.php';

	add_action( 'parse_request', 'ptr_override_results_list_table' );
}

/**
 * Override the edit.php?post_type=results WP_Post_List_Table.
 *
 * This is the most ridiculous hack I've hacked, but this totally works.
 */
function ptr_override_results_list_table() {
	global $wp_list_table;

	if (
		isset( $wp_list_table ) &&
		'WP_Posts_List_Table' == get_class( $wp_list_table )
	) {
		remove_action( 'parse_request', __FUNCTION__ );

		$wp_list_table = new PTR\Posts_List_Table();
		// We were within WP_Posts_List_Table::prepare_items() when we overrode it, so we have to query again.
		$wp_list_table->prepare_items();
	}
}

/**
 * Get a rendered template part
 *
 * @param string $template
 * @param array $vars
 * @return string
 */
function ptr_get_template_part( $template, $vars = array() ) {
	$full_path = dirname( __FILE__ ) . '/parts/' . $template . '.php';

	if ( ! file_exists( $full_path ) ) {
		return '';
	}

	ob_start();
	// @codingStandardsIgnoreStart
	if ( ! empty( $vars ) ) {
		extract( $vars );
	}
	// @codingStandardsIgnoreEnd
	include $full_path;
	return ob_get_clean();
}
