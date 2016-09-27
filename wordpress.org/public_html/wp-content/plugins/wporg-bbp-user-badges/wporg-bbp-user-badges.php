<?php
/**
 * Plugin Name: bbPress: User Badges
 * Description: Display a badge in user replies as appropriate (such as for a plugin/theme author/contributor, moderator).
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 * Text Domain: wporg-forums
 */

namespace WordPressdotorg\Forums\User_Badges;

defined( 'ABSPATH' ) or die();

// Store the root plugin file for usage with functions which use the plugin basename
define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );

// Includes
include( dirname( __FILE__ ) . '/inc/class-plugin.php' );

// Instantiate the Plugin
Plugin::get_instance();
