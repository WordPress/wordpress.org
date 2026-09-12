<?php

$base_dir = dirname( dirname( dirname( __DIR__ ) ) );
require( $base_dir . '/translations/lib.php' );
require( $base_dir . '/init.php' );
require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
require( $base_dir . '/includes/object-cache.php' );
wp_cache_init();

$slug    = isset( $_REQUEST['slug'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['slug'] ) ) : '';
$version = isset( $_REQUEST['version'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['version'] ) ) : null;

foreach ( [ 'slug', 'version' ] as $field ) {
	if ( $$field && ! is_string( $$field ) ) {
		header( sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ?? '' ) ) . ' 400 Bad Request' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field name comes from the literal list iterated above, not from the request.
		die( "?{$field}= invalid." );
	}
}

$translations = find_all_translations_for_type_and_domain( 'plugin', $slug, $version );

call_headers( 'application/json' );

echo json_encode( array( 'translations' => $translations ) );

exit;

