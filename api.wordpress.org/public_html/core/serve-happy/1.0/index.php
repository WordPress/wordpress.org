<?php
/**
 * Serve Happy API: reports whether a site's PHP version is still supported.
 *
 * Standalone endpoint; WordPress is not loaded here, so its sanitizers are
 * unavailable. The JSONP callback name is restricted to [a-zA-Z0-9_.] inline.
 *
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput
 *
 * @package WordPressdotorg\API\Serve_Happy
 */

namespace WordPressdotorg\API\Serve_Happy;

define( 'API_VERSION', '1.0' );

require dirname( dirname( dirname( __DIR__ ) ) ) . '/init.php';

require __DIR__ . '/include.php';
require WPORGPATH . 'wp-content/mu-plugins/pub/servehappy-config.php';

// Output the API response.
output_response(
	parse_request(
		determine_request()
	)
);

// Output functions
function bail( $error_code, $error_text, $http_code = 400, $http_code_text = false ) {
	$server_protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
	$http_code_texts = [
		400 => 'Bad Request',
	];
	$http_code_text = $http_code_text ?? ( $http_code_texts[ $http_code ] ?? $http_code_text[ 400 ] );

	header( "$server_protocol $http_code $http_code_text" );

	output_response( array(
		'code'    => $error_code,
		'message' => $error_text,
		'status'  => $http_code
	) );
	die();
}

// Output as JSON, allowing for JSONP through the `?callback=` parameter.
function output_response( $data ) {
	$json_data = json_encode( $data );

	header( 'Access-Control-Allow-Origin: *' );

	if ( !empty( $_GET['callback'] ) ) {
		call_headers( 'application/javascript' );

		echo '/**/' .
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The callback name is restricted to [a-zA-Z0-9_.] inline.
			preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['callback'] ) .
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSONP response body; json_encode() output, which an HTML escaper would corrupt.
			'(' . $json_data . ')';
	} else {
		call_headers( 'application/json' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response body; json_encode() output, which an HTML escaper would corrupt.
		echo $json_data;
	}
}