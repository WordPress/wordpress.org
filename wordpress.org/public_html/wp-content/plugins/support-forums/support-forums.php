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
include( __DIR__ . '/inc/class-plugin.php' );
include( __DIR__ . '/inc/class-users.php' );
include( __DIR__ . '/inc/class-user-notes.php' );
include( __DIR__ . '/inc/class-moderators.php' );
include( __DIR__ . '/inc/class-hooks.php' );
include( __DIR__ . '/inc/class-report-topic.php' );
include( __DIR__ . '/inc/class-nsfw-handler.php' );
include( __DIR__ . '/inc/class-stats.php' );
include( __DIR__ . '/inc/class-emails.php' );
include( __DIR__ . '/inc/class-audit-log.php' );
include( __DIR__ . '/inc/class-blocks.php' );

// Compat-only includes.
include( __DIR__ . '/inc/class-dropin.php' );
include( __DIR__ . '/inc/class-support-compat.php' );
include( __DIR__ . '/inc/class-directory-compat.php' );
include( __DIR__ . '/inc/class-theme-directory-compat.php' );
include( __DIR__ . '/inc/class-plugin-directory-compat.php' );
include( __DIR__ . '/inc/class-ratings-compat.php' );
include( __DIR__ . '/inc/class-stickies-compat.php' );
include( __DIR__ . '/inc/class-performance-optimizations.php' );

// Instantiate the plugin on load.
Plugin::get_instance();
