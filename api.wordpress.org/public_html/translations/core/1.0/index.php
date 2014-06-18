<?php

$base_dir = dirname( dirname( dirname( __DIR__ ) ) );
require( $base_dir . '/translations/lib.php' );
require( $base_dir . '/init.php' );
require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
require( $base_dir . '/includes/object-cache.php' );
wp_cache_init();

$version = isset( $_REQUEST['version'] ) ? $_REQUEST['version'] : WP_CORE_LATEST_RELEASE;

$translations = find_all_translations_for_core( $version );

call_headers( 'application/json' );

echo json_encode( array( 'languages' => $translations ) );
exit;

