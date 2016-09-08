<?php
/**
 * Plugin Name: bbPress: Term Subscription
 * Description: Allow users to subscribe to topic tags. Provides foundation for generic taxonomy term subscriptions.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 *
 * Based on Subscribe to Tags by Otto.
 */

namespace WordPressdotorg\Forums\Term_Subscription;

// Store the root plugin file for usage with functions which use the plugin basename
define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );

// Includes
include( dirname( __FILE__ ) . '/inc/class-plugin.php' );

// Instantiate the Plugin
new Plugin();

// Easy access for templates
function get_subscription_link( $term_id ) {
	return Plugin::get_subscription_link( array(
		'term_id'  => $term_id,
		'taxonomy' => 'topic-tag',
	) );
}
