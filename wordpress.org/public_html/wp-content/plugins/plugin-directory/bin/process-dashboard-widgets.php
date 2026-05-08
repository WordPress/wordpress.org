<?php

namespace WordPressdotorg\Plugin_Directory;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$opts = getopt( '', array( 'url:', 'abspath:', 'csv:', 'list:' ) );

if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}
if ( empty( $opts['csv'] ) && empty( $opts['list'] ) ) {
	// Default candidate list: the Veloria search for `wp_add_dashboard_widget`.
	$opts['csv'] = 'https://veloria.dev/search/51a57411-22de-4f83-87ca-11b282f975ad/export';
}

// Bootstrap WordPress
$_SERVER['HTTP_HOST']   = parse_url( $opts['url'], PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = parse_url( $opts['url'], PHP_URL_PATH );

require rtrim( $opts['abspath'], '/' ) . '/wp-load.php';

if ( ! class_exists( '\WordPressdotorg\Plugin_Directory\Plugin_Directory' ) ) {
	fwrite( STDERR, "Error! This site doesn't have the Plugin Directory plugin enabled.\n" );
	if ( defined( 'WPORG_PLUGIN_DIRECTORY_BLOGID' ) ) {
		fwrite( STDERR, "Run the following command instead:\n" );
		fwrite( STDERR, "\tphp " . implode( ' ', $argv ) . ' --url ' . get_site_url( WPORG_PLUGIN_DIRECTORY_BLOGID, '/' ) . "\n" );
	}
	die();
}

// Build the candidate slug list.
$slugs = array();
if ( ! empty( $opts['list'] ) ) {
	$slugs = array_filter( array_map( 'trim', file( $opts['list'] ) ) );
} else {
	// Veloria CSV: Extension,Slug,Version,Active Installs,File,Line Number,Line
	$handle = fopen( $opts['csv'], 'r' );
	if ( ! $handle ) {
		fwrite( STDERR, "Failed to open CSV: {$opts['csv']}\n" );
		exit( 1 );
	}
	fgetcsv( $handle ); // header
	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		if ( ! empty( $row[1] ) ) {
			$slugs[ $row[1] ] = true;
		}
	}
	fclose( $handle );
	$slugs = array_keys( $slugs );
}

echo count( $slugs ) . " candidate plugins to scan.\n";

$count_total = $count_tagged = $count_skipped = 0;
foreach ( $slugs as $slug ) {
	$plugin = Plugin_Directory::get_plugin_post( $slug );
	if ( ! $plugin || 'publish' !== $plugin->post_status ) {
		echo "  {$slug}: not published, skipping\n";
		$count_skipped++;
		continue;
	}

	echo "{$slug}... ";
	shell_exec( 'php ' . escapeshellarg( __DIR__ . '/import-plugin.php' ) . ' ' . escapeshellarg( $slug ) );

	$widget_names = get_post_meta( $plugin->ID, 'dashboard_widget_name' );
	$has_section  = has_term( 'dashboard-widgets', 'plugin_section', $plugin->ID );

	if ( $has_section ) {
		echo "tagged (" . count( $widget_names ) . " widgets: " . implode( ', ', $widget_names ) . ")\n";
		$count_tagged++;
	} else {
		echo "no widgets detected\n";
	}
	$count_total++;
}

echo "\n";
echo number_format( $count_total ) . " plugins scanned\n";
echo number_format( $count_tagged ) . " tagged with dashboard-widgets\n";
echo number_format( $count_skipped ) . " skipped (not published)\n";
