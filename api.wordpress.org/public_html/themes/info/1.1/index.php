<?php
/**
 * Themes API 1.1 endpoint: JSON wrapper around the 1.0 API.
 *
 * This is a stateless public API endpoint, so there is no session or nonce infrastructure.
 * The request is also read before the 1.0 endpoint loads WordPress, which means request
 * data has not been slashed at that point.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 *
 * @package WordPressdotorg\API\Themes
 */

header( 'Access-Control-Allow-Origin: *' );

if ( isset( $_GET['callback'] ) && is_string( $_GET['callback'] ) ) {
	$callback = preg_replace(
		'/[^a-z0-9_]/i',
		'',
		filter_var( $_GET['callback'], FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW )
	);

	// A callback that is not a valid JavaScript identifier falls back to plain JSON.
	if ( $callback && ! preg_match( '/^[a-z_]/i', $callback ) ) {
		$callback = false;
	}
} else {
	$callback = false;
}

define( 'JSON_RESPONSE', true );

if ( ! defined( 'THEMES_API_VERSION' ) ) {
	define( 'THEMES_API_VERSION', '1.1' );
}

ob_start();
require dirname( __DIR__ ) . '/1.0/index.php';
$response = ob_get_clean();

if ( $callback ) {
	header( 'Content-Type: text/javascript; charset=UTF-8' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated callback, JSON payload.
	echo "$callback($response);";
} else {
	header( 'Content-Type: application/json; charset=UTF-8' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload served as application/json.
	echo $response;
}
