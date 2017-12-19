<?php
namespace WordPressdotorg\Plugin_Directory;

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$opts = getopt( '', array( 'post:', 'url:', 'abspath:', 'age:' ) );

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

function callback_sum( $carry, $row ) {
	$carry += $row[1];
	return $carry;
}

function display_top_table( $stats, $n = 20 ) {

	$top  = array_slice( $stats, 0, $n );
	$tail = array_slice( $stats, $n );

	foreach ( $top as $row ) {
		// $vals = array_values( (array) $row );
		echo $row[0] . "\t\t\t" . number_format( $row[1] ) . "\n";
	}

	echo "Top $n Total: " . number_format( array_reduce( $top, __NAMESPACE__ . '\callback_sum' ) ) . "\n";

	$tail_n = count( $tail );
	echo "Other $tail_n: " . number_format( array_reduce( $tail, __NAMESPACE__ . '\callback_sum' ) ) . "\n";

}

function tested_to_summary( $pfx_where = '1=1' ) {
	global $wpdb;

	$stats = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value AS k, COUNT( DISTINCT post_id ) AS c FROM $wpdb->posts LEFT JOIN $wpdb->postmeta ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID WHERE $pfx_where AND meta_key = %s GROUP BY meta_value ORDER BY c DESC", 'tested' ), ARRAY_N );

	return $stats;
}

$where = $wpdb->prepare( 'post_status = %s', 'publish' );

if ( ! empty( $opts['age'] ) && strtotime( $opts['age'] ) > 0 ) {
	$where .= $wpdb->prepare( ' AND post_modified >= %s', strftime( '%Y-%m-%d', strtotime( $opts['age'] ) ) );
}

// TODO: add some more reports, and a CLI argument for choosing them
display_top_table( tested_to_summary( $where ) );
