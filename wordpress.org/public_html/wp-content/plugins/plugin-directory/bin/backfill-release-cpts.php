<?php
/**
 * One-off migration: backfill `plugin_release` CPT records from the legacy
 * `releases` postmeta (or `tags` metadata / SVN tags) for existing plugins.
 *
 * Usage:
 *   php backfill-release-cpts.php [--plugin=slug] [--url=...] [--abspath=...] [--force]
 *
 * @package WordPressdotorg\Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Releases;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$opts = getopt( '', array( 'plugin:', 'url:', 'abspath:', 'force' ) );

if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}

$force = isset( $opts['force'] );

// Bootstrap WordPress.
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

if ( ! empty( $opts['plugin'] ) ) {
	$slugs = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'plugin' AND post_name = %s",
			$opts['plugin']
		)
	);
} else {
	// All plugins.
	$slugs = $wpdb->get_col( "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'plugin'" );
}

if ( ! $slugs ) {
	fwrite( STDERR, "Error! The plugin(s) could not be located.\n" );
	die();
}

$total = count( $slugs );

foreach ( $slugs as $i => $slug ) {
	$releases = Releases::for_plugin( $slug );
	$result   = $releases ? $releases->maybe_backfill( $force ) : new \WP_Error( 'invalid_plugin', 'Invalid plugin' );

	if ( is_wp_error( $result ) ) {
		$message = 'error: ' . $result->get_error_message();
	} elseif ( false === $result ) {
		$message = 'skipped (already migrated)';
	} else {
		$message = count( $result ) . ' release(s) backfilled';
	}

	fwrite( STDOUT, sprintf( "%d/%d\t%s\t%s\n", $i + 1, $total, $slug, $message ) );

	// Reset in-memory caches between plugins to keep memory usage flat.
	wp_cache_flush_runtime();
}
