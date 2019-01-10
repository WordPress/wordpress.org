<?php
namespace WordPressdotorg\API\Serve_Happy;

function determine_request( $request = false ) {
	$request = $request ?: $_GET; // For unit testing.

	if ( empty( $request['php_version'] ) ) {
		return bail( 'missing_param', 'Missing parameter: php_version', 400 );
	}

	$php_version = false;
	// PHP versions on hosts vary and include extra data, we're only interested in the major core PHP version component:
	if ( preg_match( '!^([0-9]+\.([0-9]+\.)?[0-9]+)!', $request['php_version'], $m ) ) {
		$php_version = $m[1];
	}

	if ( ! $php_version ) {
		return bail( 'invalid_param', 'Invalid parameter: php_version', 400 );
	}

	return compact( 'php_version' );
}

function parse_request( $request ) {
	$php_version = $request['php_version'];

	return array(
		'recommended_version' => RECOMMENDED_PHP,
		'minimum_version'     => MINIMUM_PHP,
		'is_supported'        => version_compare( $php_version, SUPPORTED_PHP, '>=' ),
		'is_secure'           => version_compare( $php_version, SECURE_PHP, '>=' ),
		'is_acceptable'       => version_compare( $php_version, ACCEPTABLE_PHP, '>=' ),
	);
}

// Compat for Meta environment:
if ( ! function_exists( 'call_headers' ) ) {
	function call_headers( $content_type ) {
		header( "Content-Type: {$content_type}; charset=utf-8" );
	}
}