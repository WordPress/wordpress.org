<?php
/**
 * Plugin Name: Plugin Directory
 * Plugin URI: https://wordpress.org/plugins/
 * Description: Transforms a WordPress site in The Official Plugin Directory.
 * Version: 3.0
 * Author: the WordPress team
 * Author URI: https://wordpress.org/
 * Text Domain: wporg-plugins
 * License: GPLv2
 * License URI: https://opensource.org/licenses/gpl-2.0.php
 *
 * @package WordPressdotorg_Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory;

/**
 * Store the root plugin file for usage with functions which use the plugin basename.
 */
define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );

/**
 * Store the root plugin folder for usage with functions which need the relative path.
 */
define( __NAMESPACE__ . '\PLUGIN_DIR', __DIR__ );

// Register an Autoloader for all files
require __DIR__ . '/class-autoloader.php';
Autoloader\register_class_path( __NAMESPACE__, __DIR__ );

// Instantiate the Plugin Directory
Plugin_Directory::instance();
