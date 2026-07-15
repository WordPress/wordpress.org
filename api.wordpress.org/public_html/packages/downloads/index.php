<?php
/**
 * Composer Notify-Batch Endpoint.
 *
 * Receives POST requests from Composer clients with download/install telemetry.
 * Currently a stub that accepts and acknowledges the data without processing.
 *
 * POST body format (from Composer):
 * {
 *   "downloads": [
 *     { "name": "plugin/akismet", "version": "5.3.1" },
 *     ...
 *   ]
 * }
 *
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Access-Control-Allow-Origin: *' );

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Direct server variable check.
if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 405 Method Not Allowed', true, 405 );
	header( 'Allow: POST' );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WP loaded.
	echo json_encode( array( 'error' => 'Only POST requests are accepted.' ) );
	exit;
}

// TODO: Parse the body and bump stats.

// Accept the notification. Return 200 OK regardless.
http_response_code( 200 );

// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WP loaded.
echo json_encode( array( 'status' => 'ok' ) );
