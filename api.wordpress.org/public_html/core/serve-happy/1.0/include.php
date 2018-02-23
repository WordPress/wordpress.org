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

	// Including the IP address here as an optional parameter incase it's wanted later to return dynamic responses for hosts.
	$ip_adress = false;
	if ( ! empty( $request['ip_address'] ) ) {
		$ip_address = $request['ip_address'];

		// Only allow a IP range to be passed. for example: 123.123.123.0 instead of 123.123.123.45
		if ( '.0' != substr( $ip_address, -2 ) ) {
			return bail( 'invalid_param', 'Invalid parameter: ip_address', 400 );
		}
	}

	return compact(
		'php_version',
		'ip_address'
	);
}

function parse_request( $request ) {
	$php_version = $request['php_version'];

	$out_of_date                = version_compare( $php_version, TRIGGER_PHP_VERSION,            '<'  );
	$receiving_security_updates = version_compare( $php_version, PHP_RECEIVING_SECURITY_UPDATES, '>=' );

	$status = 'ok';
	if ( $out_of_date ) {
		$status = 'out_of_date';
	} elseif ( ! $receiving_security_updates ) {
		$status = 'no_security_updates';
	}

	$recommended_php = RECOMMENDED_PHP;
	$secure_php      = PHP_RECEIVING_SECURITY_UPDATES;
	$update_url      = ''; // Potential future use based on $request['ip_address']

	return compact(
		'php_version',
		'recommended_php',
		'secure_php',
		'status',
		'update_url',

		// Including for debugging purposes, see https://meta.trac.wordpress.org/ticket/3474
		'out_of_date',
		'receiving_security_updates'
	);
}

// Compat for Meta environment:
if ( ! function_exists( 'call_headers' ) ) {
	function call_headers( $content_type ) {
		header( "Content-Type: {$content_type}; charset=utf-8" );
	}
}