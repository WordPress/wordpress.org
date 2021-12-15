<?php
namespace WordPressdotorg\Trac\Watcher;
/**
 * Plugin Name: WordPress.org Trac & SVN Watcher.
 * Description: This plugin imports Trac activity & SVN activity into WordPress.org databases. Trac actions for Profiles, and SVN activity for reporting, profiles, and make.w.org/* reporting.
 * Version: 1.0
 * Author: Dion Hulse
 */

define( 'PLUGIN', __FILE__ );

include_once __DIR__ . '/trac.php';
include_once __DIR__ . '/svn.php';
include_once __DIR__ . '/props.php';

// Must be earlier than admin_init for admin_menu
add_action( 'init', function() {
	if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
		include_once __DIR__ . '/admin/ui.php';
	}
} );

add_filter( 'cron_schedules', function( $schedules ) { 
	$schedules['10_minutes'] = [
		'interval' => 600,
		'display'  => 'Every Ten Minutes'
	];
	return $schedules;
} );

add_action( 'admin_init', function() {
	// Queue the jobs on blog_id 1 only (wordpress.org/)
	if ( 1 !== get_current_blog_id() ) {
		return;
	}

	if ( ! wp_next_scheduled( 'import_revisions_from_svn' ) ) {
		wp_schedule_event( time(), '10_minutes', 'import_revisions_from_svn' );
	}

	if ( ! wp_next_scheduled( 'import_trac_feeds' ) ) {
		wp_schedule_event( time(), '10_minutes', 'import_trac_feeds' );
	}
} );

/**
 * Database tables for if this is used outside of wordpress.org
 */
register_activation_hook( __FILE__, __NAMESPACE__ . '\create_tables' );
function create_tables() {
	$trac_table = "CREATE TABLE IF NOT EXISTS `%s` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`description` longtext NOT NULL,
		`summary` longtext NOT NULL,
		`category` varchar(50) NOT NULL,
		`username` varchar(50) NOT NULL,
		`link` varchar(255) NOT NULL,
		`pubdate` datetime NOT NULL,
		`md5_id` varchar(50) DEFAULT NULL,
		`title` varchar(255) NOT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `md5_id` (`md5_id`),
		KEY `category` (`category`),
		KEY `username` (`username`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;"; // UTF8, but treated as latin1, due to historical profiles reasons.

	$revisions_table = "CREATE TABLE IF NOT EXISTS `%s` (
		`id` int(11) unsigned NOT NULL,
		`author` varchar(255) NOT NULL DEFAULT '',
		`date` datetime NOT NULL,
		`summary` tinytext NOT NULL,
		`message` text DEFAULT NULL,
		`branch` varchar(255) NOT NULL DEFAULT '',
		`version` varchar(32) NOT NULL DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `author` (`author`),
		KEY `branch` (`branch`),
		KEY `version` (`version`(3))
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

	$props_table = "CREATE TABLE IF NOT EXISTS `%s` (
		`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`revision` int(11) NOT NULL,
		`user_id` bigint(20) DEFAULT NULL,
		`prop_name` varchar(128) NOT NULL DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `user_id` (`user_id`),
		KEY `revision` (`revision`)
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

	foreach ( SVN\get_svns() as $prefix => $info ) {
		if ( ! empty( $info['trac_table'] ) ) {
			$wpdb->query( sprintf( $trac_table, $info['trac_table'] ) );
		}
		if ( ! empty( $info['rev_table'] ) ) {
			$wpdb->query( sprintf( $revisions_table, $info['rev_table'] ) );
		}
		if ( ! empty( $info['props_table'] ) ) {
			$wpdb->query( sprintf( $props_table, $info['props_table'] ) );
		}
	}
}
