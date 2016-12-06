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
	require GLOTPRESS_LOCALES_PATH;

	// Temporary: Add language variants to the list of GlotPress locales.
	$GLOBALS['gp_locales'] = new GP_Locales();

	$de_formal = clone GP_Locales::by_field( 'wp_locale', 'de_DE' );
	$de_formal->english_name = 'German (Formal)';
	$de_formal->native_name = 'Deutsch (Sie)';
	$de_formal->slug = 'de/formal';
	$de_formal->wp_locale = 'de_DE_formal';
	$GLOBALS['gp_locales']->locales['de/formal'] = $de_formal;

	$nl_formal = clone GP_Locales::by_field( 'wp_locale', 'nl_NL' );
	$nl_formal->english_name = 'Dutch (Formal)';
	$nl_formal->native_name = 'Nederlands (Formeel)';
	$nl_formal->slug = 'nl/formal';
	$nl_formal->wp_locale = 'nl_NL_formal';
	$GLOBALS['gp_locales']->locales['nl/formal'] = $nl_formal;

	$de_ch_informal = clone GP_Locales::by_field( 'wp_locale', 'de_CH' );
	$de_ch_informal->english_name = 'German (Switzerland, Informal)';
	$de_ch_informal->native_name = 'Deutsch (Schweiz, Du)';
	$de_ch_informal->slug = 'de-ch/informal';
	$de_ch_informal->wp_locale = 'de_CH_informal';
	$GLOBALS['gp_locales']->locales['de-ch/informal'] = $de_ch_informal;

	$gp_locale = GP_Locales::by_field( 'wp_locale', isset( $argv[2] ) ? $argv[2] : $_GET['locale'] );
	if ( $gp_locale ) {
		$locale = $gp_locale;
	}
}

$credits = WP_Credits::factory( $version, $locale );
$credits->execute();

if ( 'cli' == php_sapi_name() )
	echo "\n";

