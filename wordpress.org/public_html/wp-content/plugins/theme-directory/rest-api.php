<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;

/**
 * The WordPress REST API only allows jsonp support via the _jsonp parameter,
 * and it must be set prior to the REST API Server being initialized, prior to any
 * rest api specific filters are run.
 * 
 * This maps the parameter this API uses ?callback= to the REST API parameter.
 */
add_action( 'parse_request', function( $wp ) {
	if (
		! isset( $_GET['callback'] ) ||
		empty( $wp->query_vars['rest_route'] ) ||
		'/themes/' !== substr( $wp->query_vars['rest_route'], 0, 8 )
	) {
		return;
	}

	$_GET['_jsonp'] = $_GET['callback'];

	unset( $_GET['callback'], $_REQUEST['callback'] );
}, 9 );

// Define the 'THEMES_API_VERSION' constant for API requests.
add_action( 'rest_api_init', function() {
	global $wp;

	if ( preg_match( '!^/themes/(\d\.\d)/!', $wp->query_vars['rest_route'], $m ) ) {
		define( 'THEMES_API_VERSION', $m[1] );
	}
} );

// The /themes/1.0/* endpoints are Serialized PHP output when requested directly.
// Doesn't affect internal calls.
add_filter( 'rest_pre_echo_response', function( $result ) {
	global $wp;

	if ( defined( 'THEMES_API_VERSION' ) && '1.0' === THEMES_API_VERSION ) {
		echo serialize( $result );
		exit;
	}

	return $result;
} );

// Include the REST API Endpoints at the appropriate time.
add_action( 'rest_api_init', function() {
	include __DIR__ . '/rest-api/class-internal.php';
	include __DIR__ . '/rest-api/class-info-endpoint.php';
	include __DIR__ . '/rest-api/class-query-endpoint.php';
	include __DIR__ . '/rest-api/class-commercial-shops-endpoint.php';
	include __DIR__ . '/rest-api/class-features-endpoint.php';
	include __DIR__ . '/rest-api/class-tags-endpoint.php';
	include __DIR__ . '/rest-api/class-themes-auto-review.php';
	include __DIR__ . '/rest-api/class-theme-categorization.php';
	include __DIR__ . '/rest-api/class-theme-review-stats.php';
} );
