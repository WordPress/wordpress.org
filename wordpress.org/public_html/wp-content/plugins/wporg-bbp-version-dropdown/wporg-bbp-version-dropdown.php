<?php
/**
 * Plugin Name: bbPress: Version Dropdown
 * Description: Display a WordPress version dropdown on new/edit topic forms.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\Forums\Version_Dropdown;

// Store the root plugin file for usage with functions which use the plugin basename
define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );

// Includes
include( dirname( __FILE__ ) . '/inc/class-plugin.php' );

// Instantiate the Plugin
Plugin::get_instance();

// Easy access for templates
function get_topic_version( $topic_id ) {
	return Plugin::get_topic_version( array( 'id' => $topic_id ) );
}
