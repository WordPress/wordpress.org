<?php

namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\CLI\Block_Plugin_Checker;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$opts = getopt( '', array( 'post:', 'url:', 'abspath:', 'slug:', 'all' ) );

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

if ( !empty( $opts['slug'] ) ) {
	$args = array(
		'post_type' => 'plugin',
		'post_status' => 'publish',
		'posts_per_page' => 1,
		'name' => $opts['slug'],
		'tax_query' => array(
			array(
				'taxonomy' => 'plugin_section',
				'field'    => 'slug',
				'terms'    => 'block',
			),
		),
	);
} else {

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
}
$query = new \WP_Query( $args );

$count_plugins = $count_new_plugins = $count_checked = $count_blocks = $count_block_json = 0;
$good_plugins = $error_plugins = array();
while ( $query->have_posts() ) {
	++ $count_checked;
	$query->the_post();
	$plugin = get_post();

	echo "Checking $plugin->post_name\n";

	$url = 'https://plugins.svn.wordpress.org/' . $plugin->post_name . '/tags/' . $plugin->stable_tag;

	$checker = new Block_Plugin_Checker( $plugin->post_name );
	$results = $checker->run_check_plugin_repo( $url );

	foreach ( $results as $item ) {
		echo "$item->type\t$item->check_name\t$item->message\n";
		if ( $item->data ) {
			print_r( $item->data );
			echo "\n";
		}

		if ( 'error' === $item->type ) {
			$error_plugins[] = $plugin->post_name;
		}
	}

	$error_plugins = array_unique( $error_plugins );
	if ( !in_array( $plugin->post_name, $error_plugins ) )
		$good_plugins[] = $plugin->post_name;

}

echo "Good plugins:\n" . join( "\n", $good_plugins ) . "\n\n";
echo "Problem plugins:\n" . join( "\n", $error_plugins ) . "\n\n";

echo "Checked: " . number_format( $count_checked) . "\n";
