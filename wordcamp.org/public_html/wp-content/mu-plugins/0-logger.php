<?php

namespace WordCamp\Logger;
defined( 'WPINC' ) or die();

/**
 * Log an event, with optional additional data
 *
 * SECURITY WARNING: You must redact any sensitive info before it's written to the log file. The best way to do
 * that is to add entries to wcorg_log_redact_keys(), so that the caller remain clean. You can also do it before
 * passing the data to this function, though. Right now this only redacts array keys, but it can be made more
 * sophisticated in the future to handle additional cases.
 *
 * In the future, it may be helpful to decode JSON values and search them for redactable values, and to allow
 * the caller to pass an array of strings that are sensitive and should be redacted. The latter would allow for
 * redacting values that aren't known until runtime, and could be helpful in other cases too.
 *
 * @param string $error_code
 * @param array  $data
 */
function log( $error_code, $data = array() ) {
	$backtrace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 2 );

	if ( $data && ! is_scalar( $data ) ) {
		$data = (array) $data;
		redact_keys( $data );
		$data = str_replace( "\n", '[newline]', serialize( $data ) );
	}

	$log_entry = sprintf(
		'%s:%s - %s:%s%s',
		basename( $backtrace[0]['file'] ),
		$backtrace[0]['line'],
		$backtrace[1]['function'],
		$error_code,
		$data ? ' - ' . $data : ''
	);

	error_log( $log_entry );
}

/**
 * Redact sensitive values from log entries
 *
 * @param array $data
 *
 * @return array
 */
function redact_keys( & $data ) {
	$redacted_keys = array( 'Authorization' );
	$redacted_keys = array_map( 'strtolower', $redacted_keys ); // to avoid human error

	foreach ( $data as $key => $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( in_array( strtolower( $key ), $redacted_keys, true ) ) {
			 $data[ $key ] = '[redacted]';
		}

		if ( is_array( $value ) ) {
			$data[ $key ] = redact_keys( $value );
		}
	}

	return $data;
}
