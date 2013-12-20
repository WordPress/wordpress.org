<?php

$api_root = dirname( dirname( __DIR__ ) );

// Grab some helpers; also WP_CORE_LATEST_RELEASE and WP_CORE_LATEST_BRANCH.
require "$api_root/init.php";
// Need HyperDB for DB calls.
require "$api_root/includes/hyperdb/bb-10-hyper-db.php";
// Need object cache.
require "$api_root/includes/object-cache.php";

// The 1.1 endpoint uses JSON. 1.0 uses serialized PHP.
// Direct access to this file should only occur for CLI usage.
if ( 'cli' !== php_sapi_name() ) {
	if ( defined( 'JSON_RESPONSE' ) && JSON_RESPONSE ) {
		header( 'Content-Type: application/json; charset=UTF-8' );
	} elseif ( defined( 'JSON_RESPONSE' ) ) {
		header( 'Content-Type: text/plain; charset=UTF-8' );
	} else {
		header( 'HTTP/1.0 400 Bad Request', true, 400 );
		die( 'Bad request.' );
	}
}

// Get WP_Credits library.
require_once dirname( __FILE__ ) . '/wp-credits.php';

if ( ! function_exists( 'like_escape' ) ) :
function like_escape( $text ) {
	return str_replace( array( "%", "_") , array( "\\%", "\\_" ), $text );
}
endif;

if ( ! empty( $_GET['version'] ) ) {
	$version = preg_replace( '/^([.0-9]+).*/', '$1', $_GET['version'] );
} elseif ( 'cli' == php_sapi_name() && isset( $argv[1] ) ) {
	$version = preg_replace( '/^([.0-9]+).*/', '$1', $argv[1] );
} else {
	$version = WP_CORE_LATEST_RELEASE;
}

$locale = false;
// Convert a locale from a WP locale to a GP locale.
if ( ( isset( $_GET['locale'] ) && 'en_US' != $_GET['locale'] ) || ( 'cli' == php_sapi_name() && isset( $argv[2] ) ) ) {
	require WPORGPATH . 'translate/glotpress/locales/locales.php';
	$gp_locale = GP_Locales::by_field( 'wp_locale', isset( $argv[2] ) ? $argv[2] : $_GET['locale'] );
	if ( $gp_locale ) {
		$locale = $gp_locale;
	}
}

$credits = WP_Credits::factory( $version, $locale );
$credits->execute();

if ( 'cli' == php_sapi_name() )
	echo "\n";

