<?php
/**
 * Plugin name: GlotPress: Playground
 * Description: Provides general customizations for translate.wordpress.org.
 * Version:     0.1
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\GlotPress\Playground;

// Store the root plugin file for usage with functions which use the plugin basename.
const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR  = __DIR__;

include __DIR__ . '/inc/routes/class-route.php';
include __DIR__ . '/inc/class-plugin.php';

// Instantiate the Plugin.
Plugin::get_instance();
