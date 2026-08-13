<?php
namespace WordPressdotorg\API\Serve_Happy;

/*
 * This is a standalone, unauthenticated, stateless API endpoint: WordPress is not loaded,
 * so request data is never slashed, and there is no session or nonce infrastructure.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 */

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
	// Only a well-formed protocol version is echoed back into the status header.
	$server_protocol = filter_var(
		$_SERVER['SERVER_PROTOCOL'] ?? '',
		FILTER_VALIDATE_REGEXP,
		array(
			'options' => array(
				'regexp'  => '#^HTTP/[0-9]+(\.[0-9]+)?$#',
				'default' => 'HTTP/1.1',
			),
		)
	);
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

	// A JSONP callback is a JavaScript identifier, optionally namespaced; anything else is discarded.
	$callback = filter_var(
		$_GET['callback'] ?? '',
		FILTER_VALIDATE_REGEXP,
		array(
			'options' => array(
				'regexp'  => '/^[a-zA-Z0-9_.]+$/',
				'default' => '',
			),
			'flags'   => FILTER_REQUIRE_SCALAR,
		)
	);

	if ( $callback ) {
		call_headers( 'application/javascript' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated callback, JSON-encoded payload.
		echo '/**/' . $callback . '(' . $json_data . ')';
	} else {
		call_headers( 'application/json' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded payload served as application/json.
		echo $json_data;
	}
}