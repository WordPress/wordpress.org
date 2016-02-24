<?php
/**
 * Plugin Name: Plugin Directory
 * Plugin URI: http://wordpress.org/plugins/
 * Description: Transforms a WordPress site in The Official Plugin Directory.
 * Version: 0.1
 * Author: the WordPress team
 * Author URI: https://wordpress.org/
 * Text Domain: wporg-plugins
 * License: GPLv2
 * License URI: http://opensource.org/licenses/gpl-2.0.php
 *
 * @package WPorg_Plugin_Directory
 */

include __DIR__ . '/class-wporg-plugin-directory.php';
include __DIR__ . '/class-wporg-plugin-directory-template.php';
include __DIR__ . '/class-wporg-plugin-directory-tools.php';

include __DIR__ . '/shortcodes/screenshots.php';

if ( is_admin() ) {
	include __DIR__ . '/admin/class-wporg-plugin-directory-admin-metabox-committers.php';
}

$wporg_plugin_directory = new WPorg_Plugin_Directory();
register_activation_hook( __FILE__, array( $wporg_plugin_directory, 'activate' ) );
register_deactivation_hook( __FILE__, array( $wporg_plugin_directory, 'deactivate' ) );
