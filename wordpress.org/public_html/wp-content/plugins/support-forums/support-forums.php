<?php
/**
 * Plugin Name: Support Forums
 * Plugin URI: https://wordpress.org/support/
 * Description: Transform a WordPress site into the support forums.
 * Version: 1.0
 * Author: WordPress.org
 * Author URI: https://wordpress.org/
 * Text Domain: wporg-forums
 * License: GPLv2
 * License URI: http://opensource.org/licenses/gpl-2.0.php
 */

namespace WordPressdotorg\Forums;

// This plugin requires that bbPress be activated. Do nothing if activated without it.
if ( ! class_exists( 'bbPress' ) ) {
	return;
}

// General includes.
include( dirname( __FILE__ ) . '/inc/class-plugin.php' );
include( dirname( __FILE__ ) . '/inc/class-users.php' );
include( dirname( __FILE__ ) . '/inc/class-user-notes.php' );
include( dirname( __FILE__ ) . '/inc/class-moderators.php' );
include( dirname( __FILE__ ) . '/inc/class-hooks.php' );
include( dirname( __FILE__ ) . '/inc/class-report-topic.php' );
include( dirname( __FILE__ ) . '/inc/class-nsfw-handler.php' );
include( dirname( __FILE__ ) . '/inc/class-stats.php' );
include( dirname( __FILE__ ) . '/inc/class-emails.php' );

// Compat-only includes.
include( dirname( __FILE__ ) . '/inc/class-dropin.php' );
include( dirname( __FILE__ ) . '/inc/class-support-compat.php' );
include( dirname( __FILE__ ) . '/inc/class-directory-compat.php' );
include( dirname( __FILE__ ) . '/inc/class-theme-directory-compat.php' );
include( dirname( __FILE__ ) . '/inc/class-plugin-directory-compat.php' );
include( dirname( __FILE__ ) . '/inc/class-ratings-compat.php' );
include( dirname( __FILE__ ) . '/inc/class-stickies-compat.php' );
include( dirname( __FILE__ ) . '/inc/class-performance-optimizations.php' );

// Instantiate the plugin on load.
Plugin::get_instance();
