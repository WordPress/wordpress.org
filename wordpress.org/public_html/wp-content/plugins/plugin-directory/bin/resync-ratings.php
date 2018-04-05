<?php
namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}
$opts = getopt( '', array( 'plugin:', 'url:', 'abspath:' ) );

if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
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

if ( !empty( $opts['plugin'] ) ) {
	$sql = $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'plugin' and post_status = 'publish' AND post_name = %s", $opts['plugin'] );
} else {
	// All plugins.
	$sql = "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'plugin' and post_status = 'publish'";
}

$slugs = $wpdb->get_col( $sql );

foreach ( $slugs as $i => $slug ) {

	$post = Plugin_Directory::get_plugin_post( $slug );
	if ( ! $post ) {
        	continue;
	}

	echo $i . '/' . count( $slugs ) . "\t" . $post->post_name . "\n";

	update_post_meta(
        	$post->ID,
        	'rating',
        	\WPORG_Ratings::get_avg_rating( 'plugin', $post->post_name )
	);

	update_post_meta(
        	$post->ID,
        	'ratings',
        	\WPORG_Ratings::get_rating_counts( 'plugin', $post->post_name )
	);

	clear_memory_caches();
}

function clear_memory_caches() {
	global $wpdb, $wp_object_cache;

	$wpdb->queries = [];

	if ( is_object( $wp_object_cache ) ) {
		$wp_object_cache->cache          = [];
		$wp_object_cache->group_ops      = [];
		$wp_object_cache->memcache_debug = [];
		$wp_object_cache->stats          = [ 'get' => 0, 'delete' => 0, 'add' => 0 ];
	}
}