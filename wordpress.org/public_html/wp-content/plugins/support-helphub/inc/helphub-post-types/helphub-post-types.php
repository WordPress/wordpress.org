<?php
/**
 * Plugin Name: Helphub Post Types
 * Plugin URI: http://www.wordpress.org
 * Description: This is what powers Post Types and Taxonomies.
 * Version: 1.3.0
 * Author: Jon Ang
 * Author URI: http://www.helphubcommunications.com/
 * Requires at least: 4.6.0
 * Tested up to: 4.0.0
 *
 * Text Domain: helphub
 * Domain Path: /languages/
 *
 * @package HelpHub_Post_Types
 * @category Core
 * @author Jon Ang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once( dirname( __FILE__ ) . '/classes/class-helphub-post-types.php' );

/**
 * Returns the main instance of HelpHub_Post_Types to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object HelpHub_Post_Types
 */
function helphub_post_types() {
	return HelpHub_Post_Types::instance();
} // End HelpHub_Post_Types()

add_action( 'plugins_loaded', 'helphub_post_types' );
