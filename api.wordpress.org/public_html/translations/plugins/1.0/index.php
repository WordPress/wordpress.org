<?php
/**
 * WordPress.org Translations API endpoint for plugins.
 *
 * This is a standalone, unauthenticated, stateless API endpoint: WordPress is not loaded,
 * so request data is never slashed, and there is no session or nonce infrastructure.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 *
 * @package WordPressdotorg\API\Translations
 */

$base_dir = dirname( dirname( dirname( __DIR__ ) ) );
require( $base_dir . '/translations/lib.php' );
require( $base_dir . '/init.php' );
require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
require( $base_dir . '/includes/object-cache.php' );
wp_cache_init();

// These become memcached cache keys, which reject spaces and control characters.
$slug    = isset( $_REQUEST['slug'] ) ? filter_var(
	$_REQUEST['slug'],
	FILTER_VALIDATE_REGEXP,
	array(
		'options' => array(
			'regexp' => '/^[a-z0-9._-]{1,100}\z/i',
		),
	)
) : '';
$version = isset( $_REQUEST['version'] ) ? filter_var(
	$_REQUEST['version'],
	FILTER_VALIDATE_REGEXP,
	array(
		'options' => array(
			'regexp' => '/^[a-z0-9._-]{1,100}\z/i',
		),
	)
) : null;

if ( isset( $_REQUEST['slug'] ) && ! is_string( $slug ) ) {
	http_response_code( 400 );
	die( '?slug= invalid.' );
}

if ( isset( $_REQUEST['version'] ) && ! is_string( $version ) ) {
	http_response_code( 400 );
	die( '?version= invalid.' );
}

$translations = find_all_translations_for_type_and_domain( 'plugin', $slug, $version );

call_headers( 'application/json' );

echo json_encode( array( 'translations' => $translations ) );

exit;
