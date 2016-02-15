<?php
/**
 * Plugin Name: Plugin Directory
 * Plugin URI: http://wordpress.org/plugins/
 * Description: Transforms a WordPress site in The Official Plugin Directory.
 * Version: 0.1
 * Author: the WordPress team
 * Author URI: https://wordpress.org/
 * Text Domain: wporg-plugins
 * License: GPLv2
 * License URI: http://opensource.org/licenses/gpl-2.0.php
 *
 * @package WPorg_Plugin_Directory
 */

include_once( 'class-wporg-plugin-directory.php' );
include_once( 'class-wporg-plugin-directory-template.php' );
include_once( 'class-wporg-plugin-directory-tools.php' );

$wporg_plugin_directory = new WPorg_Plugin_Directory();
register_activation_hook( __FILE__, array( $wporg_plugin_directory, 'activate' ) );
register_deactivation_hook( __FILE__, array( $wporg_plugin_directory, 'deactivate' ) );
