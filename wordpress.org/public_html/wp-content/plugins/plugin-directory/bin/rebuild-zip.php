<?php
namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use Exception;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

ob_start();

$opts = getopt( '', array( 'url:', 'abspath:', 'plugin:', 'versions:', 'async' ) );

// Guess the default parameters:
if ( empty( $opts ) && $argc == 2 ) {
	$opts['plugin'] = $argv[1];
	$argv[1]        = '--plugin ' . $argv[1];
}
if ( empty( $opts ) && $argc == 3 ) {
	$opts['plugin'] = $argv[1];
	$argv[1]        = '--plugin ' . $argv[1];

	$opts['versions'] = $argv[2];
	$argv[2]          = '--versions ' . $argv[2];
}
if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}
if ( empty( $opts['versions'] ) ) {
	$opts['versions'] = '';
}

foreach ( array( 'url', 'abspath', 'plugin' ) as $opt ) {
	if ( empty( $opts[ $opt ] ) ) {
		fwrite( STDERR, "Missing Parameter: $opt\n" );
		fwrite( STDERR, "Usage: php {$argv[0]} --plugin hello-dolly --versions 1.0,trunk --abspath /home/example/public_html --url https://wordpress.org/plugins/\n" );
		fwrite( STDERR, "--url and --abspath will be guessed if possible.\n" );
		fwrite( STDERR, "--versions if skipped will rebuild all tags/trunk.\n" );
		exit( 1 );
	}
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
	exit( 1 );
}

$plugin_slug = $opts['plugin'];
$versions    = array_filter( array_unique( array_map( 'trim', (array) explode( ',', $opts['versions'] ) ) ), 'strlen' );
$start_time  = microtime( 1 );

if ( empty( $versions ) ) {
	// Rebuild them all!
	$svn_tags = SVN::ls( "http://plugins.svn.wordpress.org/{$plugin_slug}/tags/" );
	if ( false === $svn_tags ) {
		fwrite( STDERR, "{$plugin_slug}: Warning: Failed to retrieve SVN tag listing, proceeding with trunk rebuilding only.\n" );
		$svn_tags = array();
	}

	$versions = array_map( function( $dir ) {
		return trim( $dir, '/' );
	}, $svn_tags );
	$versions[] = 'trunk';
}

if ( ! $versions ) {
	fwrite( STDERR, "{$plugin_slug}: Error! No versions specified.\n" );
	exit( 1 );
}

echo "Rebuilding ZIPs for $plugin_slug... ";
try {
	$zip_builder = new ZIP\Builder();

	$plugin_post = Plugin_Directory::get_plugin_post( $plugin_slug );
	if ( ! $plugin_post ) {
		throw new Exception( 'Could not locate plugin post' );
	}
	$stable_tag = get_post_meta( $plugin_post->ID, 'stable_tag', true ) ?? 'trunk';

	// (re)Build & Commit 5 Zips at a time to avoid limitations.
	foreach ( array_chunk( $versions, 5 ) as $versions_to_build ) {
		$zip_builder->build(
			$plugin_slug,
			$versions_to_build,
			"{$plugin_slug}: Rebuild triggered by " . php_uname( 'n' ),
			$stable_tag
		);
	}

	echo 'OK. Took ' . round( microtime( 1 ) - $start_time, 2 ) . "s\n";
} catch ( Exception $e ) {
	fwrite( STDERR, "{$plugin_slug}: Zip Rebuild failed: " . $e->getMessage() . "\n" );
	echo 'Failed. Took ' . round( microtime( 1 ) - $start_time, 2 ) . "s\n";
	exit( 1 );
}
