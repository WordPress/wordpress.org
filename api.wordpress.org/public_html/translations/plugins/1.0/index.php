<?php

$base_dir = dirname( dirname( dirname( __DIR__ ) ) );
require( $base_dir . '/translations/lib.php' );
require( $base_dir . '/init.php' );
require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
require( $base_dir . '/includes/object-cache.php' );
wp_cache_init();

// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Standalone endpoint; WordPress is not loaded here, so its sanitizers are unavailable.
$slug    = isset( $_REQUEST['slug'] )    ? $_REQUEST['slug']    : '';
$version = isset( $_REQUEST['version'] ) ? $_REQUEST['version'] : null;
// phpcs:enable WordPress.Security.ValidatedSanitizedInput

foreach ( [ 'slug', 'version' ] as $field ) {
	if ( $$field && ! is_string( $$field ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Standalone endpoint; WordPress is not loaded here, so its sanitizers are unavailable.
		header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 400 Bad Request' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field name comes from the literal list iterated above, not from the request.
		die( "?{$field}= invalid." );
	}
}

$translations = find_all_translations_for_type_and_domain( 'plugin', $slug, $version );

call_headers( 'application/json' );

echo json_encode( array( 'translations' => $translations ) );

exit;

