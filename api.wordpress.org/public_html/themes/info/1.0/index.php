<?php
namespace WordPressdotorg\API\Themes\Info;
use function WordPressdotorg\API\load_wordpress;

/*
 * This is a stateless public API endpoint, so there is no session or nonce infrastructure.
 * The request is also parsed before `load_wordpress()` runs below, which means request data
 * has not been slashed at that point.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 */

// This exposes the `load_wordpress()` function mentioned below.
require dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-init-ondemand.php';

//  wp_cache_switch_to_blog( WPORG_THEME_DIRECTORY_BLOGID ); // Uses is_multisite() which is unavailable.
$wp_object_cache->blog_prefix = WPORG_THEME_DIRECTORY_BLOGID;

/**
 * Bails out with an error message.
 *
 * @param string $error Error message.
 * @param int    $code  HTTP status code.
 */
function send_error( $error, $code = 404 ) {
	global $format;

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

	header( $protocol . ' ' . $code, true, $code );

	$response = (object) [
		'error' => $error	
	];

	/*
	 * Only used for a substring comparison, never output or stored.
	 * phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	 */
	$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

	// Browsers get a nicer action not implemented error.
	if (
		isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] &&
		false === strpos( $user_agent, 'WordPress/' ) &&
		false !== strpos( $error, 'Action not implemented.' )
	) {
		header( 'Content-Type: text/html; charset=utf-8' );
		/*
		 * Every caller passes a hard-coded message, one of which intentionally
		 * contains a link; WordPress and esc_html() are not loaded here.
		 * phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		 */
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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialized payload, not HTML.
		echo serialize( $response );
	} else {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload; no wp_json_encode() here.
		echo json_encode( $response );
	}

	die();
}

if ( ! defined( 'THEMES_API_VERSION' ) ) {
	define( 'THEMES_API_VERSION', '1.0' );
}

// Set up action and request information.
if ( defined( 'JSON_RESPONSE' ) && JSON_RESPONSE ) {
	/*
	 * Individual fields are type-checked and sanitized by Themes_API; all
	 * database access runs through prepared WP_Query calls.
	 * phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	 */
	$request = isset( $_REQUEST['request'] ) ? (object) $_REQUEST['request'] : '';
	$format = 'json';
} else {
	/*
	 * A serialized payload, screened for object injection below and unserialized
	 * with `allowed_classes` limited to stdClass.
	 * phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	 */
	$post_request = isset( $_POST['request'] ) && is_string( $_POST['request'] ) ? $_POST['request'] : '';
	if ( $post_request ) {
		// PHP Needs to get a non-urldecoded request, to avoid multibyte character malforming the request,
		// but we need to check for malicious content with the decoded style (in addition)
		$decoded = urldecode( $post_request );
		if (
			preg_match( '~[;{}][OC]:\+?\d+:~', $post_request ) ||
			preg_match( '~[;{}][OC]:\+?\d+:~', $decoded ) ||
			0 !== strpos( $decoded, 'O:8:"stdClass":' )
		) {
			send_error( 'Invalid request', 400 );
		}
	}

	$request = unserialize( $post_request, [ 'allowed_classes' => [ 'stdClass' ] ] );

	$format = 'php';
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- pre-existing; drives this endpoint's switch.
$action = filter_var(
	$_REQUEST['action'] ?? '',
	FILTER_VALIDATE_REGEXP,
	array(
		'options' => array(
			'regexp'  => '/^[a-z_]{1,32}$/',
			'default' => '',
		),
	)
);

// Validate the request.
switch ( $action ) {
	case 'theme_information':
		if ( isset( $request->slugs ) ) {
			// Validate that the slugs provided are valid.
			$slugs = $request->slugs ?? '';
			$slugs = is_array( $slugs ) ? $slugs : explode( ',', $slugs );

			if ( ! $slugs ) {
				send_error( 'Slugs not provided' );
			}

			foreach ( $slugs as $slug ) {
				if ( ! $slug || ! is_string( $slug ) || ! preg_match( '/^[a-z0-9-_]+$/', $slug ) ) {
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
			if ( ! is_string( $slug ) || ! preg_match( '/^[a-z0-9-_]+$/', $slug ) ) {
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

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON or serialized payload, not HTML.
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
