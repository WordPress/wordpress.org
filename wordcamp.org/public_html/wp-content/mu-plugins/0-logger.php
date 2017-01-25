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
 * @todo add a $type param with 'error' and 'info' values. errors continue to go to std error log. info is logged
 *       to separate file, so they don't clutter error log. need to rotate that file. then update callers to use
 *       new param.
 *
 * @param string $error_code
 * @param array  $data
 */
function log( $error_code, $data = array() ) {
	$backtrace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 2 );

	if ( 'cli' === php_sapi_name() ) {
		$data['command'] = sprintf( '%s %s', $_SERVER['_'], implode( ' ', $_SERVER['argv'] ) );
	} else {
		$data['request_url'] = sprintf( '%s://%s%s', $_SERVER['REQUEST_SCHEME'], $_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI'] );

		// Fall back to IP address if it's too early to detect the user
		if ( did_action( 'after_setup_theme' ) > 0 ) {
			$data['current_user'] = get_current_user_id();
		} else {
			$data['remote_ip'] = $_SERVER['REMOTE_ADDR'];
		}
	}

	redact_keys( $data );
	$data = str_replace( "\n", '[newline]', wp_json_encode( $data ) );

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
	$redacted_keys = array( 'Authorization', 'password', 'user_pass', 'key', 'apikey', 'api_key', 'client_secret' );
	$redacted_keys = array_map( 'strtolower', $redacted_keys ); // to avoid human error

	foreach ( $data as $key => $value ) {
		/*
		 * If an object is cast to an array, the array key for the object's private/protected members will contain
		 * whitespace, which causes json_decode() to ignore it. So instead, just make a simple array.
		 *
		 * The normal backtrace will often be very large, and contain recursive elements, which could lead to a
		 * script timeout. WP's summary is enough for this purpose.
		 */
		if ( is_a( $value, 'Exception' ) ) {
			$value = array(
				'message' => $value->getMessage(),
				'code'    => $value->getCode(),
				'file'    => $value->getFile(),
				'line'    => $value->getLine(),
				'trace'   => wp_debug_backtrace_summary()
			);
		}

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
	if ( 'cli' === php_sapi_name() ) {
		$caller = $_SERVER['USER'] . $_SERVER['SSH_CONNECTION'];
	} else {
		$caller = $_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT'];
	}

	return hash( 'crc32b', $caller . $_SERVER['REQUEST_TIME_FLOAT'] );
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
		// todo bug: this should be recognized as native -- [18-Jan-2017 00:33:04 UTC] [b1955769] source-site-id-setting.php:29 - preview:post_val -- [null]

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
