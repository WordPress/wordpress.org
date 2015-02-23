<?php

$base_dir = dirname( dirname( dirname( __DIR__ ) ) );
require( $base_dir . '/translations/lib.php' );
require( $base_dir . '/init.php' );
require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
require( $base_dir . '/includes/object-cache.php' );
wp_cache_init();

$version = isset( $_REQUEST['version'] ) ? str_replace( '-src', '', $_REQUEST['version'] ) : WP_CORE_LATEST_RELEASE;

$translations = find_all_translations_for_core( $version );

header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Expose-Headers: X-Translations-Count' );
header( 'X-Translations-Count:' . count( $translations ) );
if ( 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
	exit;
}

call_headers( 'application/json' );

// Remove the following crud after 4.2 is released
// https://core.trac.wordpress.org/ticket/31319
if ( version_compare( $version, '4.2', '<' ) ) {
	foreach ( $translations as $t ) {
		if ( 'haz' == $t->language ) {
			$t->iso = (object)array( 1 => 'haz', 2 => 'haz' );
		}
	}
}

echo json_encode( array( 'translations' => $translations ) );

exit;

