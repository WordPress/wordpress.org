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
		'user_login', 'user_email',
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
 *
 * @see https://core.trac.wordpress.org/ticket/17737
 */
add_action( 'send_headers', function( $wp ) {
	// Assumption: WP::$public_query_vars will only ever contain non-array query vars.
	// Assumption invalid. Some fields are valid.
	$array_fields = [ 'post_type' => true, 'cat' => true ];

	foreach ( (new \WP)->public_query_vars as $field ) {
		if ( isset( $wp->query_vars[ $field ] ) && ! is_scalar( $wp->query_vars[ $field ] ) && ! isset( $array_fields[ $field ] ) ) {
			die_bad_request( "non-scalar $field in \$public_query_vars" );
		}
	}
} );

/**
 * Detect invalid parameters being passed to o2.
 */
add_action( 'wp_ajax_nopriv_o2_read', function() {
	foreach ( array( 'postId', 'rando', 'scripts', 'styles', 'since', 'method' ) as $field ) {
		if ( !empty( $_REQUEST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
			die_bad_request( "non-scalar input to o2" );
		}
	}
}, 9 );

/**
 * Detect badly formed XMLRPC requests.
 * pingback.ping is not a valid multicall target, blocking due to the excessive requests.
 */
add_action( 'xmlrpc_call', function() {
	global $HTTP_RAW_POST_DATA;
	if (
		false !== stripos( $HTTP_RAW_POST_DATA, '<methodName>system.multicall</methodName>' ) &&
		false !== stripos( $HTTP_RAW_POST_DATA, '<name>methodName</name><value>pingback.ping</value>' )
	) {
		die_bad_request( 'pingback.ping inside a system.multicall' );
	}
}, 1 );

/**
 * Die with a 400 Bad Request.
 *
 * @param string $reference A unique identifying string to make it easier to read logs.
 */
function die_bad_request( $reference = '' ) {
	// Log it if possible, and not on a sandbox
	if ( ! defined( 'WPORG_SANDBOXED' ) || ! WPORG_SANDBOXED ) {
		if ( function_exists( 'wporg_error_reporter' ) && ! empty( $_COOKIE['wporg_logged_in'] ) ) {
			wporg_error_reporter( E_USER_NOTICE, "400 Bad Request: $reference", __FILE__, __LINE__ );
		}
	}

	// Use a prettier error page on WordPress.org
	if (
		false !== stripos( $_SERVER['HTTP_HOST'], 'wordpress.org' ) &&
		defined( 'WPORGPATH' ) && file_exists( WPORGPATH . '/403.php' ) &&
		! defined( 'XMLRPC_REQUEST' ) && ! defined( 'REST_REQUEST' )
	) {
		// Bare header, we don't need Block assets.
		remove_all_actions( 'wp_head' );

		status_header( 400 );
		$header_set_for_403 = true;
		include WPORGPATH . '/403.php';
	} else {
		\wp_die( 'Bad Request', 'Bad Request', [ 'response' => 400 ] );
	}
	exit;
}
