<?php
/**
 * Plugin name: Rosetta
 * Description: Plugin to manage sites in the global.wordpress.org network.
 * Version:     1.0
 * Network:     true
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\Rosetta;

use WordPressdotorg\Autoload;

// Store the root plugin file for usage with functions which use the plugin basename.
define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );

if ( ! class_exists( '\WordPressdotorg\Autoload\Autoloader', false ) ) {
	include __DIR__ . '/vendor/wordpressdotorg/autoload/class-autoloader.php';
}

// Register an Autoloader for all files.
Autoload\register_class_path( __NAMESPACE__, __DIR__ . '/inc' );

// Instantiate the Plugin.
Plugin::get_instance();
