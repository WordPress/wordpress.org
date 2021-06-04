<?php
namespace WordPressdotorg\Plugin_Directory;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$opts = getopt( '', array( 'post:', 'url:', 'abspath:' ) );

if ( empty( $opts['url'] ) ) {
	$opts['url'] = 'https://wordpress.org/plugins/';
}
if ( empty( $opts['abspath'] ) && false !== strpos( __DIR__, 'wp-content' ) ) {
	$opts['abspath'] = substr( __DIR__, 0, strpos( __DIR__, 'wp-content' ) );
}

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

$plugin_ids = get_posts( array(
	'fields' => 'ids',
	'post_type' => 'plugin',
	'post_status' => 'publish',
	'posts_per_page' => -1,
	// For a random subset of blocks.
	// 'posts_per_page' => 50,
	// 'orderby' => 'rand',
	'meta_query' => array(
		array(
			'key' => 'all_blocks',
			'compare' => 'EXISTS',
		),
	),
) );

$total = 0;
foreach ( $plugin_ids as $plugin_id ) {
	$all_blocks = get_post_meta( $plugin_id, 'all_blocks', true );
	$total += count( $all_blocks );
}

echo count( $plugin_ids ) . ' plugins provide ' . intval( $total ) . " blocks.\n";

