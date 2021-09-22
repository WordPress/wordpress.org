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
	check_for_invalid_query_vars( $wp->query_vars, '$public_query_vars' );
} );

/**
 * Check a set of internal query variables against the WordPress WP_Query values to detect invalid input.
 */
function check_for_invalid_query_vars( $vars, $ref = '$public_query_vars' ) {
	$query_vars = (new \WP)->public_query_vars;
	$query_vars[] = 'customize_changeset_uuid';
	$query_vars[] = 'forum';

	// Assumption: WP::$public_query_vars will only ever contain non-array query vars.
	// Assumption invalid. Some fields are valid.
	$array_fields = [ 'post_type' => true, 'cat' => true ];

	// Some fields only accept numeric values.
	$must_be_num = [
		'm'             => true,
		'p'             => true,
		'w'             => true,
		'page'          => true,
		'paged'         => true,
		'page_id'       => true,
		'attachment_id' => true,
		'year'          => true,
		'month'         => true,
		'monthnum'      => true,
		'day'           => true,
		'hour'          => true,
		'minute'        => true,
		'second'        => true,
	];

	foreach ( $query_vars as $field ) {
		if ( isset( $vars[ $field ] ) ) {
			if ( ! is_scalar( $vars[ $field ] ) && ! isset( $array_fields[ $field ] ) ) {
				die_bad_request( "non-scalar $field in $ref" );
			}

			if ( isset( $must_be_num[ $field ] ) && ! empty( $vars[ $field ] ) && ! is_numeric( $vars[ $field ] ) ) {

				// Allow the `p` variable to contain `p=12345/`: https://bbpress.trac.wordpress.org/ticket/3424
				if ( 'p' === $field && ( intval( $vars[ $field ] ) . '/' === $vars[ $field ] ) ) {
					continue;
				}

				die_bad_request( "non-numeric $field in $ref" );
			}
		}
	}
}

/**
 * Detect invalid parameters being passed to o2.
 */
add_action( 'wp_ajax_nopriv_o2_read', function() {
	foreach ( array( 'postId', 'rando', 'scripts', 'styles', 'since', 'method' ) as $field ) {
		if ( !empty( $_REQUEST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
			die_bad_request( "non-scalar input to o2" );
		}
	}
	if ( isset( $_REQUEST['queryVars'] ) ) {
		check_for_invalid_query_vars( $_REQUEST['queryVars'], 'o2 queryVars' );
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
 * Detect invalid requests from over hungry vulnerability scanners.
 */
add_action( 'setup_theme', function() {
	if ( isset( $_REQUEST['EGOTEC'] ) ) {
		die_bad_request( 'EGOTEC request parameter set' );
	}

	// Sorry, this shouldn't be needed.
	// rate limit bots requesting abnormally-high POST/GET vars
	// these often include 'pagename' and cause responses to change,
	// causing the scanner to target even more junk requests to the url.
	$items = max( count( $_GET ), count( $_POST ) );
	if (
		$items >= 8 &&
		! is_user_logged_in() &&
		empty( $_SERVER['HTTP_AUTHORIZATION'] ) &&
		in_array( $items, [ 8, 16, 32, 64, 128, 255, 256, 512, 1024 ], true ) &&
		wp_using_ext_object_cache()
	) {
		$key   = 'scanner:' . $_SERVER['REMOTE_ADDR'];
		$group = 'ip-ratelimit-bp';

		if ( function_exists( 'wp_cache_add_global_groups' ) ) {
			wp_cache_add_global_groups( $group );
		}

		$hits = wp_cache_get( $key, $group );
		if ( false === $hits ) {
			wp_cache_add( $key, 1, $group, HOUR_IN_SECONDS );
			return;
		}

		// Only increment it for high counts, but block on low counts if exceeded.
		if ( $items > 16 || $hits > 20 ) {
			$hits = wp_cache_incr( $key, 1, $group );
		}

		// Abnormally high numbers to be on the 'safe' side.
		if ( $hits >= 20 ) {
			// Since they hit the threshold and still kept going.. longer.
			if ( $hits == 50 ) {
				wp_cache_set( $key, $hit, $group, DAY_IN_SECONDS );
			}

			die_bad_request( "Bulk POST query scanner: $key" );
		}
	}
}, 0 );

/**
 * Detect invalid requests from vulnerability scanners to Jetpack Share by Email forms.
 */
add_action( 'send_headers', function() {
	if ( ! isset( $_REQUEST['share'] ) ) {
		return;
	}

	$share_by_email_fields = [
		'target_email',
		'source_email',
		'source_f_name',
		'source_name',
	];

	foreach ( $share_by_email_fields as $field ) {
		if ( isset( $_POST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
			die_bad_request( "non-scalar $field in Jetpack Share By Email" );
		}
	}
} );

/**
 * Detect invalid admin ajax requests.
 */
if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
	add_action( 'admin_init', function() {
		if ( ! empty( $_REQUEST['action'] ) && ! is_scalar( $_REQUEST['action'] ) ) {
			die_bad_request( "non-scalar action in admin-ajax." );
		}
	} );
}

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
		// Bare header & footer, we don't need all the extras.
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'wp_footer' );

		status_header( 400 );
		$header_set_for_403 = true;
		include WPORGPATH . '/403.php';
	} else {
		\wp_die( 'Bad Request', 'Bad Request', [ 'response' => 400 ] );
	}
	exit;
}
