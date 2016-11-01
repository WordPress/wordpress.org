<?php

namespace WordCamp\Logger;
defined( 'WPINC' ) or die();

/**
 * Log an event, with optional additional data
 *
 * SECURITY WARNING: You must redact any sensitive info before it's written to the log file. The best way to do
 * that is to add entries to redact_keys(), so that the caller remain clean. You can also do it before
 * passing the data to this function, though.
 *
 * @todo add current username to every entry, and update wp-cli command parsing regex to match
 *
 * @param string $error_code
 * @param array  $data
 */
function log( $error_code, $data = array() ) {
	$backtrace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 2 );

	if ( $data && ! is_scalar( $data ) ) {
		$data = (array) $data;
		redact_keys( $data );
		$data = str_replace( "\n", '[newline]', wp_json_encode( $data ) );
	}

	$log_entry = sprintf(
		'[%s] %s:%s - %s:%s%s',
		get_unique_request_id(),
		basename( $backtrace[0]['file'] ),
		$backtrace[0]['line'],
		$backtrace[1]['function'],
		$error_code,
		$data ? ' -- ' . $data : ''
	);

	error_log( $log_entry );
}

/**
 * Redact sensitive values from log entries
 *
 * Right now this only redacts array keys, but it can be made more sophisticated in the future to handle
 * additional cases.
 *
 * In the future, it may be helpful to decode JSON values and search them for redactable values, and to allow
 * the caller to pass an array of strings that are sensitive and should be redacted. The latter would allow for
 * redacting values that aren't known until runtime, and could be helpful in other cases too.
 *
 * @param array $data
 *
 * @return array
 */
function redact_keys( & $data ) {
	$redacted_keys = array( 'Authorization', 'password', 'user_pass' );
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

/**
 * Generate a unique ID for the current request
 *
 * This is useful when debugging race conditions, etc, so that you can identify which log entries belong to each thread.
 *
 * Based on https://stackoverflow.com/a/22508709/450127
 *
 * @return string
 */
function get_unique_request_id() {
	return hash( 'crc32b', $_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT'] . $_SERVER['REQUEST_TIME_FLOAT'] );
}

/**
 * Format entries created with log()
 *
 * See WordCamp_CLI_Miscellaneous::format_log() for usage instructions.
 *
 * @param string $raw_log
 * @param string $foreign_entries `ignore` or `include` entries that weren't created with log()
 *
 * @return string
 */
function format_log( $raw_log, $foreign_entries = 'include' ) {
	$formatted_log        = '';
	$raw_entries          = explode( "\n", $raw_log );
	$native_entry_pattern = '/(\[.*?\]) (\[\w+\]) (.*?:.*?) - (.*?) -- (\{.*\})/';

	foreach ( $raw_entries as $entry ) {
		$is_native_entry = 1 === preg_match( $native_entry_pattern, $entry, $entry_parts );

		if ( $is_native_entry ) {
			$formatted_log .= sprintf(
				"\n%s %s %s - %s\n%s",
				$entry_parts[1],
				$entry_parts[2],
				$entry_parts[3],
				$entry_parts[4],
				print_r( json_decode( $entry_parts[5], true ), true )
			);
		} else {
			if ( 'ignore' !== $foreign_entries ) {
				$formatted_log .= $entry . "\n";
			}
		}
	}

	return $formatted_log;
}
