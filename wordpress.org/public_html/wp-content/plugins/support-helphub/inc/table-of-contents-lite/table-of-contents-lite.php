<?php
/**
 * Plugin Name: Table Of Contents Lite
 * Version: 1.0
 * Plugin URI: https://carl.alber2.com
 * Description: Lightweight Table of Content Plugin. Automatically detects h1, h2, h3 tags in your post content and convert it to your table of contents.
 * Author: Carl Alberto
 * Author URI: https://carl.alber2.com
 * Requires at least: 4.0
 * Tested up to: 4.5
 *
 * Text Domain: table-of-contents-lite
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Carl Alberto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'TABLE_OF_CONTENTS_URL', plugin_dir_url( __FILE__ ) );

// Load plugin class files.
require_once( 'includes/class-table-of-contents-lite.php' );

/**
 * Returns the main instance of Table_Of_Contents_Lite to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Table_Of_Contents_Lite
 */
function table_of_contents_lite() {
	$instance = Table_Of_Contents_Lite::instance( __FILE__, '1.0.0' );
	return $instance;
}

table_of_contents_lite();
