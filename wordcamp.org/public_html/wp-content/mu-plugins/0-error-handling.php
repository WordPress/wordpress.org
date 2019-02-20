<?php
defined( 'WPINC' ) or die();

define( 'ERROR_RATE_LIMITING_DIR', '/tmp/error_limiting' );

if ( is_readable( __DIR__ . '/includes/slack/send.php' ) ) {
	require_once( __DIR__ . '/includes/slack/send.php' );
}

set_error_handler( 'send_error_to_slack' );
register_shutdown_function( 'send_fatal_to_slack' );

/**
 * Error handler to send errors to Slack.
 *
 * Note: This should always return false so that default error handling still occurs as well.
 *
 * @todo We should consider splitting the functionality here into two or more separate functions.
 *       This way logging errors to files could be separate from sending messages to Slack. This would
 *       also potentially allow us to load the error logging part earlier, before WP functions
 *       are available.
 *
 * @param int    $err_no
 * @param string $err_msg
 * @param string $file
 * @param int    $line
 *
 * @return bool
 */
function send_error_to_slack( $err_no, $err_msg, $file, $line ) {
	if ( ! defined( 'WORDCAMP_ENVIRONMENT' )
	     || ( 'production' !==  WORDCAMP_ENVIRONMENT && ! defined( 'SANDBOX_SLACK_USERNAME' ) )
	) {
		return false;
	}

	if ( ! init_error_handling() ) {
		return false;
	}

	// Checks to see if the error-throwing expression is prepended with the @ control operator.
	// See https://secure.php.net/manual/en/function.set-error-handler.php
	if ( 0 === error_reporting() ) {
		return false;
	}

	$error_safelist = [
		E_ERROR,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
		E_WARNING,
		E_PARSE,
		E_CORE_WARNING,
		E_COMPILE_WARNING,
		E_USER_WARNING,
		E_NOTICE,
		E_USER_NOTICE,
		E_STRICT,
		E_DEPRECATED,
		E_USER_DEPRECATED,
	];

	if ( ! in_array( $err_no, $error_safelist ) ) {
		return false;
	}

	// Always use constants in the keys here to avoid path disclosure.
	$error_ignorelist = [
		// See https://core.trac.wordpress.org/ticket/29204
		ABSPATH . 'wp-includes/SimplePie/Registry.php:215' => 'Non-static method WP_Feed_Cache::create() should not be called statically',
	];

	if ( isset( $error_ignorelist[ "$file:$line" ] ) && false !== strpos( $err_msg, $error_ignorelist[ "$file:$line" ] ) ) {
		return false;
	}

	$err_key    = substr( base64_encode("$file-$line-$err_no" ), -254 ); // Max file length for ubuntu is 255.
	$error_file = ERROR_RATE_LIMITING_DIR . "/$err_key";
	$data       = array(
		'last_reported_at' => time(),
		'error_count'      => 0, // since last reported.
	);
	$messages    = explode( 'Stack trace:', $err_msg, 2 );
	$pretext     = $messages[0] ?: '';
	$stack_trace = ( ! empty( $messages[1] ) ) ? trim( sanitize_text_field( $messages[1] ) ) : '';
	$footer      = '';

	if ( ! file_exists( $error_file ) ) {
		file_put_contents( $error_file, wp_json_encode( $data ) );
	} else {
		$data                 = json_decode( file_get_contents( $error_file ), true );
		$data['error_count'] += 1;
		$time_elapsed         = time() - $data['last_reported_at'];

		if ( $time_elapsed > 600 ) {
			$data['last_reported_at']  = time();
			$data['error_count']       = 0;
			file_put_contents( $error_file, wp_json_encode( $data ) );

			$footer .= "Occurred *${data['error_count']} time(s)* since last reported";
		} else {
			file_put_contents( $error_file, wp_json_encode( $data ) );
			return false;
		}
	}

	$domain    = esc_url( get_site_url() );
	$page_slug = sanitize_text_field( untrailingslashit( $_SERVER['REQUEST_URI'] ) ) ?: '/';

	switch( $err_no ) {
		case E_ERROR:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR :
		case E_USER_ERROR:
		default:
			$color = '#ff0000'; // Red.
			break;
		case E_WARNING:
		case E_PARSE:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
		case E_USER_WARNING:
			$color = '#ffa500'; // Orange.
			break;
		case E_NOTICE:
		case E_USER_NOTICE:
		case E_STRICT:
		case E_DEPRECATED:
		case E_USER_DEPRECATED:
			$color = '#ffff00'; // Yellow.
			break;
	}

	$fields = [
		[
			'title' => 'Domain',
			'value' => $domain,
			'short' => false,
		],
		[
			'title' => 'Page',
			'value' => $page_slug,
			'short' => false,
		],
		[
			'title' => 'File',
			'value' => "$file:$line",
			'short' => false,
		],
	];

	if ( $stack_trace ) {
		$fields[] = [
			'title' => 'Stack Trace',
			'value' => $stack_trace,
			'short' => false,
		];
	}

	$attachment = array(
		'fallback'    => $pretext,
		'pretext'     => $pretext,
		'color'       => $color,
		'author_name' => 'WordCamp Logger',
		'fields'      => $fields,
		'footer'      => $footer,
	);

	$send = new \Dotorg\Slack\Send( SLACK_ERROR_REPORT_URL );
	$send->add_attachment( $attachment );

	if ( 'production' === WORDCAMP_ENVIRONMENT ) {
		$send->send( WORDCAMP_LOGS_SLACK_CHANNEL );
	} else {
		$send->send( SANDBOX_SLACK_USERNAME );
	}

	return false;
}

/**
 * Shutdown handler for catching fatal errors and sending them to Slack.
 *
 * Some error types cannot be handled directly by a custom error handler. However, we can catch them during shutdown
 * and redirect them to the custom handler callback.
 */
function send_fatal_to_slack() {
	$error = error_get_last();

	// See https://secure.php.net/manual/en/function.set-error-handler.php
	$unhandled_error_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING ];

	if ( ! empty( $error ) && in_array( $error['type'], $unhandled_error_types, true ) ) {
		send_error_to_slack( $error['type'], $error['message'], $error['file'], $error['line'] );
	}
}

/**
 * Check and create filesystem dirs to manage rate limiting in error handling.
 *
 * For legacy bugs we are doing rate limiting via filesystem. We would be investigating to see if we can instead use memcache to rate limit sometime in the future.
 *
 * @return bool Return true if file permissions etc are present
 */
function init_error_handling() {
	if ( ! file_exists( ERROR_RATE_LIMITING_DIR ) ) {
		mkdir( ERROR_RATE_LIMITING_DIR );
	}

	return is_dir( ERROR_RATE_LIMITING_DIR ) && is_writeable( ERROR_RATE_LIMITING_DIR );
}

/**
 * Remove temporary error rate limiting files.
 *
 * Function `send_error_to_slack` above also creates a bunch of files in /tmp/error_limiting folder in order to rate limit the notification.
 * This function will be used as a cron to clear these error_limiting files periodically.
 */
function handle_clear_error_rate_limiting_files() {
	if ( ! init_error_handling() ) {
		return;
	}

	foreach ( new DirectoryIterator( ERROR_RATE_LIMITING_DIR ) as $file_info ) {
		if ( ! $file_info->isDot() ) {
			unlink( $file_info->getPathname() );
		}
	}
}

if ( ! wp_next_scheduled( 'clear_error_rate_limiting_files' ) ) {
	wp_schedule_event( time(), 'daily', 'clear_error_rate_limiting_files' );
}

add_action( 'clear_error_rate_limiting_files', 'handle_clear_error_rate_limiting_files' );
