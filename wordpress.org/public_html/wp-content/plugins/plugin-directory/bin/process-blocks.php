<?php

namespace WordPressdotorg\Plugin_Directory;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$opts = getopt( '', array( 'url:', 'abspath:' ) );

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

$args = array(
	'post_type' => 'plugin',
	'post_status' => 'publish',
	'nopaging' => true,
	'tax_query' => array(
		array(
			'taxonomy' => 'plugin_section',
			'field'    => 'slug',
			'terms'    => 'block',
		),
	),
);
$query = new \WP_Query( $args );

$count_plugins = $count_with_blocks = $count_with_files = 0;
$plugins_missing_assets = $plugins_missing_blocks = array();
while ( $query->have_posts() ) {
	$query->the_post();
	$plugin = get_post();

	echo $plugin->post_name;
	echo "\n";

	shell_exec( 'php import-plugin.php ' . escapeshellarg( $plugin->post_name ) );

	$all_blocks = get_post_meta( $plugin->ID, 'all_blocks', true );
	$block_files = get_post_meta( $plugin->ID, 'block_files', true );

	if ( ! empty( $all_blocks ) ) {
		echo "Blocks: ";
		print_r( $all_blocks );
	} else {
		echo "Blocks: none\n";
	}

	if ( ! empty( $block_files ) ) {
		echo "Assets: ";
		print_r( $block_files );
	} else {
		echo "Assets: none\n";
	}

	echo "\n\n";

	if ( is_array( $block_files ) && count( $block_files ) > 0 ) {
		++ $count_with_files;
	} else {
		$plugins_missing_assets[] = $plugin->post_name;
	}
	if ( is_array( $all_blocks ) && count( $all_blocks ) > 0 ) {
		++ $count_with_blocks;
	} else {
		$plugins_missing_blocks[] = $plugin->post_name;
	}
	++ $count_plugins;
}

echo number_format( $count_plugins ) . " plugins\n";
echo number_format( $count_with_blocks ) . " have blocks\n";
echo number_format( $count_with_files ) . " have asset files\n";
echo "\n";
echo "Plugins missing blocks:\n" . join( "\n", $plugins_missing_blocks ) . "\n\n";
echo "Plugins missing assets:\n" . join( "\n", $plugins_missing_assets ) . "\n\n";
