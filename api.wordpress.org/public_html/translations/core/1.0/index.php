<?php

$base_dir = dirname( dirname( dirname( __DIR__ ) ) );
require( $base_dir . '/translations/lib.php' );
require( $base_dir . '/init.php' );
require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
require( $base_dir . '/includes/object-cache.php' );
wp_cache_init();

$version = WP_CORE_LATEST_RELEASE;
if ( isset( $_REQUEST['version'] ) ) {
	$version = $_REQUEST['version'];
	if ( ! is_string( $version ) || ! is_numeric( $version[0] ) ) {
		header( $_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request' );
		die( '?version= must be a valid WordPress version' );
	}

	$version = str_replace( '-src', '', $version );
}

$translations = find_all_translations_for_core( $version );

header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Expose-Headers: X-Translations-Count' );
header( 'X-Translations-Count:' . count( $translations ) );
if ( 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
	exit;
}

call_headers( 'application/json' );

echo json_encode( array( 'translations' => $translations ) );

exit;

