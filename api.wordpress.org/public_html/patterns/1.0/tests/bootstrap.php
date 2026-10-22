<?php
/**
 * PHPUnit bootstrap for the Patterns API tests.
 *
 * @package WordPressdotorg\API\Patterns\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Patterns\Tests;

// The vendor directory's depth differs between the production server tree and an open-source checkout.
if ( file_exists( dirname( __DIR__, 5 ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__, 5 ) . '/vendor/autoload.php';
} else {
	require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';
}

// Defines WPORG_SANDBOXED when running on a WordPress.org sandbox; absent locally and in CI.
$api_init_file = dirname( __DIR__, 3 ) . '/init.php';
if ( file_exists( $api_init_file ) ) {
	require_once $api_init_file;
}

/**
 * Make a real HTTP request to the live Patterns API.
 *
 * @param string $path Request path including query string, e.g. "/patterns/1.0/?per_page=20".
 * @return object { body: string, status_code: int }
 * @throws \RuntimeException When the request fails at the transport layer.
 */
function send_request( string $path ): object {
	// On a sandbox, the request has to be made to localhost with the Host header overridden.
	$sandboxed = defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED;
	$host      = $sandboxed ? '127.0.0.1' : 'api.wordpress.org';
	$url       = 'https://' . $host . $path;

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WordPress isn't loaded, so its HTTP API isn't available.
	$body = file_get_contents(
		$url,
		false,
		stream_context_create(
			array(
				'http' => array(
					'header'        => 'Host: api.wordpress.org',
					'ignore_errors' => true,
					'user_agent'    => 'WordPress/' . PHP_VERSION . '; https://example.org',
					'timeout'       => 30,
				),
				'ssl'  => array(
					'verify_peer'      => ! $sandboxed,
					'verify_peer_name' => ! $sandboxed,
				),
			)
		)
	);

	if ( false === $body ) {
		$last_error = error_get_last();
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test bootstrap; message is CLI output for PHPUnit, not HTML.
		throw new \RuntimeException( sprintf( 'Failed to fetch %s: %s', $url, $last_error['message'] ?? 'unknown error' ) );
	}

	$status_code = 0;
	foreach ( $http_response_header ?? array() as $header ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $header, $m ) ) {
			$status_code = (int) $m[1];
		}
	}

	return (object) array(
		'body'        => $body,
		'status_code' => $status_code,
	);
}
