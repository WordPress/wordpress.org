<?php
/**
 * Plugin Name: WordPress.org 400 Bad Request
 * Description: Throw a 400 Bad Request for bad requests. This shouldn't be needed, but vulnerability scanners exist and it makes a mess of logs.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\BadRequest
 */

namespace WordPressdotorg\BadRequest;

/**
 * Detect invalid form field values in login requests.
 */
add_action( 'login_init', function() {
	$expected_string_fields = [
		'log', 'pwd', 'redirect_to', 'rememberme',
		'_wp_http_referer', '_wpnonce',
		'locale' // WordPress.org login localisation.
	];

	foreach ( $expected_string_fields as $field ) {
		if ( isset( $_REQUEST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
			die_bad_request( "non-scalar $field in login \$_REQUEST" );
		}
	}
} );

/**
 * Detect invalid query parameters being passed in Core query fields.
 * Generally causes WP_Query to throw a PHP Warning.
 */
add_action( 'send_headers', function( $wp ) {
	// Assumption: WP::$public_query_vars will only ever contain non-array query vars.
	// Assumption invalid. Some fields are valid.
	$array_fields = [ 'post_type' => true ];

	foreach ( (new \WP)->public_query_vars as $field ) {
		if ( isset( $wp->query_vars[ $field ] ) && ! is_scalar( $wp->query_vars[ $field ] ) && ! isset( $array_fields[ $field ] ) ) {
			die_bad_request( "non-scalar $field in \$public_query_vars" );
		}
	}
} );

/**
 * Detect invalid parameters being passed to REST API Endpoints.
 * Not all API endpoints sanitization callbacks check variable types.
 */
add_action( 'rest_api_init', function( $wp_rest_server ) {
	global $wp;

	// oEmbed endpoint has some not-so-great sanitize callbacks specified
	if ( '/oembed/1.0/embed' === $wp->query_vars['rest_route'] ) {
		foreach ( [ 'url', 'maxwidth' ] as $field ) {
			if ( isset( $_REQUEST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
				die_bad_request( "non-scalar $field in oEmbed call" );
			}
		}
	}

} );

/**
 * Die with a 400 Bad Request.
 * 
 * @param string $reference A unique identifying string to make it easier to read logs.
 */
function die_bad_request( $reference = '') {
	header( $_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request' );

	// Use a prettier error page on WordPress.org
	if (
		false !== stripos( $_SERVER['HTTP_HOST'], 'wordpress.org' ) &&
		defined( 'WPORGPATH' ) && file_exists( WPORGPATH . '/403.php' )
	) {
		$header_set_for_403 = true;
		include WPORGPATH . '/403.php';

		// Log it if possible.
		if ( function_exists( 'wporg_error_reporter' ) ) {
			wporg_error_reporter( E_USER_NOTICE, "400 Bad Request: $reference", __FILE__, __LINE__ );
		}
		exit;
	}

	\wp_die( 'Bad Request', 'Bad Request', [ 'code' => 400 ] );
}
