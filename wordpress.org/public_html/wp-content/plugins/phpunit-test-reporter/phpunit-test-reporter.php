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
