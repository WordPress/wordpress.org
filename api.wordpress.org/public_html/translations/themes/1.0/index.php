<?php

$base_dir = dirname( dirname( dirname( __DIR__ ) ) );
require( $base_dir . '/translations/lib.php' );
require( $base_dir . '/init.php' );
require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
require( $base_dir . '/includes/object-cache.php' );
wp_cache_init();

$slug = isset( $_REQUEST['slug'] ) ? $_REQUEST['slug'] : '';
$version = isset( $_REQUEST['version'] ) ? $_REQUEST['version'] : null;

$translations = find_all_translations_for_type_and_domain( 'theme', $slug, $version );

call_headers( 'application/json' );

echo json_encode( array( 'translations' => $translations ) );

exit;

