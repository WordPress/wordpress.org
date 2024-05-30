<?php
namespace WordPressdotorg\API\Themes\Info;
use function WordPressdotorg\API\load_wordpress;

// This exposes the `load_wordpress()` function mentioned below.
require dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-init-ondemand.php';

//  wp_cache_switch_to_blog( WPORG_THEME_DIRECTORY_BLOGID ); // Uses is_multisite() which is unavailable.
$wp_object_cache->blog_prefix = WPORG_THEME_DIRECTORY_BLOGID;

// Helper methods
function wp_unslash( $value ) {
	if ( is_string( $value ) ) {
		return stripslashes( $value );
	}
	if ( is_array( $value ) ) {
		return array_map( __FUNCTION__, $value );
	}

	return $value;
}

/**
 * Bails out with an error message.
 *
 * @param string $error Error message.
 * @param int    $code  HTTP status code.
 */
function send_error( $error, $code = 404 ) {
	global $format;

	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' ' . $code, true, $code );

	$response = (object) [
		'error' => $error	
	];

	// Browsers get a nicer action not implemented error.
	if (
		'GET' === $_SERVER['REQUEST_METHOD'] &&
		false === strpos( $_SERVER['HTTP_USER_AGENT'] ?? '', 'WordPress/' ) &&
		false !== strpos( $error, 'Action not implemented.' )
	) {
		header( 'Content-Type: text/html; charset=utf-8' );
		die( "<p>{$error}</p>" );
	}

	// Back-compat behaviour for the 1.0/1.1 API's
	if (
		defined( 'THEMES_API_VERSION' ) && THEMES_API_VERSION < 1.2 &&
		'Theme not found' == $response->error
	) {
		$response = false;
	}

	if ( 'php' === $format ) {
		echo serialize( $response );
	} else {
		// JSON format
		echo json_encode( $response );
	}

	die();
}

if ( ! defined( 'THEMES_API_VERSION' ) ) {
	define( 'THEMES_API_VERSION', '1.0' );
}

// Set up action and request information.
if ( defined( 'JSON_RESPONSE' ) && JSON_RESPONSE ) {
	$request = isset( $_REQUEST['request'] ) ? (object) wp_unslash( $_REQUEST['request'] ) : '';
	$format = 'json';
} else {
	$post_request = isset( $_POST['request'] ) ? urldecode( wp_unslash( $_POST['request'] ) ) : '';
	if ( $post_request && ( preg_match( '~[;{}][OC]:\+?\d+:~', $post_request ) || 0 !== strpos( $post_request, 'O:8:"stdClass":' ) ) ) {
		die( 'error' );
	}

	$request = unserialize( $post_request );

	$format = 'php';
}

$action = $_REQUEST['action'] ?? '';

// Validate the request.
switch ( $action ) {
	case 'theme_information':
		if ( isset( $request->slugs ) ) {
			// Validate that the slugs provided are valid.
			$slugs = $request->slugs ?? '';
			$slugs = is_array( $slugs ) ? $slugs : explode( ',', $slugs );

			if ( ! $slug ) {
				send_error( 'Slugs not provided' );
			}

			foreach ( $slugs as $slug ) {
				if ( ! $slug || ! is_string( $slug ) || ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
					send_error( 'Invalid slugs provided' );
				}

				// No check for 404 themes, as this bulk endpoint is low traffic and probably at least one theme will be found.
			}
			unset( $slug );
		} else {
			// Validate the slug provided is valid.
			$slug = $request->slug ?? '';
			if ( ! $slug ) {
				send_error( 'Slug not provided' );
			}
			if ( ! is_string( $slug ) || ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
				send_error( 'Invalid slug provided' );
			}

			// Check to see if this theme has been specified as not existing.
			if ( 'not_found' === wp_cache_get( $slug, 'theme_information_error' ) ) {
				send_error( 'Theme not found' );
			}
		}
		break;
	case 'query_themes':
	case 'hot_tags':
	case 'feature_list':
	case 'get_commercial_shops':
		// No validation for now, but valid endpoints.
		break;
	default:
		send_error( 'Action not implemented. <a href="https://codex.wordpress.org/WordPress.org_API">API Docs</a>' );
		die();
}

/**
 * Load WordPress, to serve this request.
 *
 * TODO: This causes a significant CPU load for the server, this should be cached in Memcache in addition to the existing caching.
 */
load_wordpress( 'https://wordpress.org/themes/' );

// Serve an API request.
$api = wporg_themes_query_api( $action, $request, 'api_object' );

$api->set_status_header();

echo $api->get_result( $format );

// Cache when a theme doesn't exist. See the validation handler above.
if (
	'theme_information' == $action &&
	isset( $slug ) &&
	404 == http_response_code() &&
	// Validate that the theme doesn't exist for update-checks, as a sanity check.
	! wp_cache_get( $slug, 'theme-update-check' ) &&
	// And that there were no DB errors.
	empty( $wpdb->last_error )
) {
	wp_cache_set( $slug, 'not_found', 'theme_information_error', WEEK_IN_SECONDS );
}
