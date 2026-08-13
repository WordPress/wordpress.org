<?php
/**
 * Themes API 1.2 endpoint: GET-only wrapper with flat request support.
 *
 * @package WordPressdotorg\API\Themes
 */

/*
 * This is a stateless public API endpoint, so there is no session or nonce infrastructure.
 * The request is also read before the 1.0 endpoint loads WordPress, which means request
 * data has not been slashed at that point.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 */

// Version 1.2+ only accepts GET requests
if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
	$protocol = filter_var(
		$_SERVER['SERVER_PROTOCOL'] ?? '',
		FILTER_VALIDATE_REGEXP,
		array(
			'options' => array(
				'regexp'  => '#^HTTP/[0-9.]+$#',
				'default' => 'HTTP/1.0',
			),
		)
	);

	header( $protocol . ' 405 Method not allowed' );
	header( 'Allow: GET' );
	header( 'Content-Type: text/plain' );

	die( 'This API only serves GET requests.' );
}

if ( ! defined( 'THEMES_API_VERSION' ) ) {
	define( 'THEMES_API_VERSION', '1.2' );
}

// Support "flat" requests, ie. no '?request[slug]=..` needed, just '?slug=...'
if ( ! isset( $_GET['request'] ) ) {
	// 1.2 only supports GET requests.
	$requested_action = filter_var(
		$_GET['action'] ?? '',
		FILTER_VALIDATE_REGEXP,
		array(
			'options' => array(
				'regexp'  => '/^[a-z_]{1,32}$/',
				'default' => '',
			),
		)
	);

	$_GET = $_REQUEST = array(
		'action'  => $requested_action,
		'request' => array_diff_key( $_GET, [ 'action' => false, 'callback' => false ] ),
	);
}

require dirname( __DIR__ ) . '/1.1/index.php';
