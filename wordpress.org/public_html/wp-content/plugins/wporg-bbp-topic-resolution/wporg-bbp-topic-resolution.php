<?php
/**
 * Plugin Name: bbPress: Topic Resolution
 * Description: Display a topic resolution on new/edit topic forms.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 *
 * Based on Support Forum 3.0.6 by Aditya Naik and Sam Bauers.
 *
 *
 */



namespace WordPressdotorg\Forums\Topic_Resolution;

// Store the root plugin file for usage with functions which use the plugin basename
define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );

// Includes
include( dirname( __FILE__ ) . '/inc/class-plugin.php' );

// Instantiate the Plugin
Plugin::get_instance();

// Easy access for templates
function get_topic_resolution( $topic_id ) {
	return Plugin::get_topic_resolution( array( 'id' => $topic_id ) );
}
