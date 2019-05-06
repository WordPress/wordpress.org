<?php
/**
 * Plugin name: GlotPress: Translation Suggestions
 * Description: Provides suggestions from translation memory via translate.wordpress.com and from translations of other languages.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\GlotPress\TranslationSuggestions;

use WordPressdotorg\Autoload;

// Store the root plugin file for usage with functions which use the plugin basename.
const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR  = __DIR__;

if ( ! class_exists( '\WordPressdotorg\Autoload\Autoloader', false ) ) {
	include __DIR__ . '/vendor/wordpressdotorg/autoload/class-autoloader.php';
}

// Register an Autoloader for all files.
Autoload\register_class_path( __NAMESPACE__, __DIR__ . '/inc' );

// Instantiate the Plugin.
Plugin::get_instance();
