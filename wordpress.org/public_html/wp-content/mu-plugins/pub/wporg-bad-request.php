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
add_action( 'parse_request', function( $wp ) {
	check_for_invalid_query_vars( $wp->query_vars, '$public_query_vars' );
}, 0 );

/**
 * Detect invalid query parameters being passed in Core query fields on requests that bbPress is intercepting.
 */
add_filter( 'bbp_request', function( $query_vars ) {
	check_for_invalid_query_vars( $query_vars, '$bbp_request' );

	return $query_vars;
}, 0 );

/**
 * Detect invalid query parameters being passed in Core query fields, before the 'request' action.
 * Generally causing warnings & fatals in `wp_resolve_numeric_slug_conflicts()`.
 */
add_filter( 'do_parse_request', function( $process ) {
	if ( $process ) {
		// See https://github.com/WordPress/wordpress-develop/blob/50fb4086b7afbfa012c5d1f2eeff79b1bae3b00e/src/wp-includes/rewrite.php#L400-L407
		$wp_resolve_numeric_slug_conflict_fields = [
			'year',
			'monthnum',
			'day'
		];

		foreach ( $wp_resolve_numeric_slug_conflict_fields as $field ) {
			if ( isset( $_REQUEST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
				die_bad_request( "non-scalar $field in do_parse_request" );
			}
		}
	}

	return $process;
}, 1001 );

/**
 * Check a set of internal query variables against the WordPress WP_Query values to detect invalid input.
 */
function check_for_invalid_query_vars( $vars, $ref = '$public_query_vars' ) {
	$query_vars = (new \WP)->public_query_vars;
	$query_vars[] = 'customize_changeset_uuid';
	$query_vars[] = 'forum';
	$query_vars[] = 'action';
	$query_vars[] = 'bbp_search';
	$query_vars[] = 'url';
	$query_vars[] = 'replytocom';
	$query_vars[] = 'tag_slug__and'; // Theme Directory has added this as a public query var.

	// Assumption: WP::$public_query_vars will only ever contain non-array query vars.
	// Assumption invalid. Some fields are valid as arrays.
	// We'll limit these to a flat array, not nested.
	$maybe_array_fields = [
		'post_type'     => true,
		'cat'           => true,
		'tag'           => true,
		'tag_slug__and' => true,
	];

	// Fields that must be an array if set.
	$must_be_array_fields = [
		// Core treats this as an array before casting strings to arrays.
		// See Theme Directory above.
		// https://core.trac.wordpress.org/ticket/60745
		'tag_slug__and' => true,
	];

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
		'replytocom'    => true,
	];

	foreach ( $query_vars as $field ) {
		if ( ! isset( $vars[ $field ] ) ) {
			continue;
		}

		if ( isset( $maybe_array_fields[ $field ] ) && ! is_scalar( $vars[ $field ] ) ) {
			if ( array_filter( $vars[ $field ], function( $item ) { return ! is_scalar( $item ); } ) ) {
				die_bad_request( "non-scalar value in {$field}[] in $ref" );
			}
		} elseif ( isset( $must_be_array_fields[ $field ] ) && ! is_array( $vars[ $field ] ) ) {
			die_bad_request( "non-array $field in $ref" );
		} else if ( ! is_scalar( $vars[ $field ] ) ) {
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

/**
 * Detect invalid parameters being passed to o2.
 */
add_action( 'wp_ajax_nopriv_o2_read', function() {
	foreach ( array( 'rando', 'scripts', 'styles', 'since', 'method' ) as $field ) {
		if ( !empty( $_REQUEST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
			die_bad_request( "non-scalar input to o2" );
		}
	}

	if ( !empty( $_REQUEST['postId'] ) && ! is_numeric( $_REQUEST['postId'] ) ) {
		die_bad_request( "Bad post ID to o2" );
	}

	if ( isset( $_REQUEST['queryVars'] ) ) {
		check_for_invalid_query_vars( $_REQUEST['queryVars'], 'o2 queryVars' );
	}
}, 9 );

/**
 * Detect invalid query parameters being passed in BuddyPress fields on requests that BuddyPress is intercepting.
 *
 * The component directory search forms (e.g. bp_directory_groups_search_form())
 * pass `$_REQUEST[ "{component}_search" ]` straight into stripslashes(), which
 * fatals when a scanner submits it as an array (e.g. `groups_search[]=the`).
 */
add_action( 'bp_template_redirect', function() {
	$expected_string_fields = [
		'members_search',
		'groups_search',
		'blogs_search',
		'activity_search',
		'forums_search',
	];

	foreach ( $expected_string_fields as $field ) {
		if ( isset( $_REQUEST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
			die_bad_request( "non-scalar $field in buddypress \$_REQUEST" );
		}
	}
}, -1 );

/**
 * Detect invalid input to the bp-classic (BP Default theme) directory AJAX handlers.
 *
 * The members/groups/blogs/forums directory filters and the activity loaders
 * pass these POST fields straight into string functions without type checks —
 * sanitize_title( $_POST['object'] ) and urldecode( $_POST['cookie'] ) — and
 * fatal when a vulnerability scanner submits them as arrays, e.g.
 * `object[]=groups` or `cookie[]=…`.
 *
 * @see bp_dtheme_object_template_loader(), bp_dtheme_ajax_querystring()
 */
add_action( 'admin_init', function() {
	// admin-ajax.php fires admin_init before the wp_ajax_{action} dispatch, so
	// this runs before bp_dtheme_object_template_loader() and friends.
	if ( ! wp_doing_ajax() || empty( $_REQUEST['action'] ) ) {
		return;
	}

	$bp_classic_ajax_actions = [
		'blogs_filter',
		'forums_filter',
		'groups_filter',
		'members_filter',
		'activity_get_older_updates',
		'activity_widget_filter',
	];

	if ( ! in_array( $_REQUEST['action'], $bp_classic_ajax_actions, true ) ) {
		return;
	}

	$scalar_fields = [
		'object',
		'cookie',
		'filter',
		'scope',
		'page',
		'search_terms',
	];

	foreach ( $scalar_fields as $field ) {
		if ( isset( $_POST[ $field ] ) && ! is_scalar( $_POST[ $field ] ) ) {
			die_bad_request( "non-scalar $field in bp-classic directory AJAX" );
		}
	}
} );

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
 * Disable Demo XMLRPC endpoints that are easy to trigger noisy fatals with invalid inputs.
 */
add_filter( 'xmlrpc_methods', function( $methods ) {
	unset( $methods['demo.addTwoNumbers'] );

	return $methods;
} );

/**
 * Detect invalid requests from over hungry vulnerability scanners.
 */
add_action( 'send_headers', function() {
	if ( isset( $_REQUEST['EGOTEC'] ) ) {
		die_bad_request( 'EGOTEC request parameter set' );
	} elseif ( str_contains( $_SERVER['REQUEST_URI'], '$acunetix' ) ) {
		die_bad_request( 'acunetix request' );
	}
} );

/**
 * Detect non-scalar values in Pattern Directory query parameters.
 *
 * Scanners pass nested arrays like `curation[$in][]=all` which cause PHP
 * warnings downstream when the value is used in esc_attr().
 */
add_action( 'send_headers', function() {
	if ( ! str_starts_with( $_SERVER['REQUEST_URI'], '/patterns/' ) ) {
		return;
	}

	$scalar_only = [ 'curation', 'pattern-categories' ];

	foreach ( $scalar_only as $field ) {
		if ( isset( $_REQUEST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
			die_bad_request( "non-scalar $field in \$_REQUEST" );
		}
	}
} );

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
		'email-share-nonce',
	];

	foreach ( $share_by_email_fields as $field ) {
		if ( isset( $_POST[ $field ] ) && ! is_scalar( $_REQUEST[ $field ] ) ) {
			die_bad_request( "non-scalar $field in Jetpack Share By Email" );
		}
	}
} );

/**
 * Detect invalid requests from vulnerability scanners to Jetpack Contact forms.
 */
add_action( 'send_headers', function() {
	if ( ! $_POST || ! isset( $_REQUEST['action'] ) || 'grunion-contact-form' !== $_REQUEST['action'] ) {
		return;
	}

	$combined = serialize( $_POST );
	if (
		false !== stripos( $combined, 'email.tst' ) ||
		false !== stripos( $combined, 'vulnweb' )
	) {
		die_bad_request( "Vulnerability testing spam input to Contact Form." );
	}
} );

/**
 * Detect invalid charsets to trackbacks.
 * Hotfix for https://core.trac.wordpress.org/ticket/60261
 */
add_action( 'template_redirect', function() {
	if ( ! is_trackback() ) {
		return;
	}

	$charset = str_replace( array( ',', ' ' ), '', strtoupper( trim( $_POST['charset'] ?? '' ) ) );

	if ( function_exists( 'mb_list_encodings' ) && ! in_array( $charset, mb_list_encodings(), true ) ) {
		die_bad_request( 'Invalid Charset' );
	}
} );

/**
 * Detect invalid requests to GlotPress
 *
 * Hotfix for https://github.com/GlotPress/GlotPress/pull/1835
 */
add_action( 'gp_init', function() {
	$only_array_values = [ 'filters', 'sort' ];

	foreach ( $only_array_values as $query_var ) {
		if ( isset( $_GET[ $query_var ] ) && ! is_array( $_GET[ $query_var ] ) ) {
			if ( empty( $_GET[ $query_var ] ) ) {
				// If it's not set to anything, just silently discard the value.
				unset( $_GET[ $query_var ], $_REQUEST[ $query_var ] );
				continue;
			}

			die_bad_request( "non-array $query_var in GlotPress" );
		}
	}
} );

/**
 * Detect invalid requests to the multisite user activation page.
 * We don't use this at all on WordPress.org and it causes PHP fatals
 * due to the mismatch between active themes and no plugins.
 */
add_action( 'init', function() {
	if ( ! defined( 'WP_INSTALLING' ) || ! WP_INSTALLING ) {
		return;
	}

	$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( str_ends_with( $path, '/wp-activate.php' ) ) {
		die_bad_request( 'Invalid request to wp-activate.php' );
	}
} );

/**
 * Die with a 400 Bad Request.
 *
 * @param string $reference A unique identifying string to make it easier to read logs.
 */
function die_bad_request( $reference = '' ) {
	// When the user is logged in, log it if possible
	if (
		'production' === wp_get_environment_type() &&
		function_exists( 'wporg_error_reporter' ) &&
		(
			// If we've loaded WordPress, use the validated cookie value, otherwise, cookie being present.
			did_action( 'init' ) ?
				is_user_logged_in() :
				! empty( $_COOKIE['wporg_logged_in'] )
		)
	) {
		wporg_error_reporter( E_USER_NOTICE, "400 Bad Request: $reference", __FILE__, __LINE__ );
	}

	// Use a prettier error page on WordPress.org
	if (
		str_contains( $_SERVER['HTTP_HOST'], 'wordpress.org' ) &&
		! defined( 'XMLRPC_REQUEST' ) && ! defined( 'REST_REQUEST' ) &&
		! is_admin() /* admin-ajax, admin-post */
	) {
		status_header( 400 );
		$header_set_for_403 = true;
		include WPORGPATH . '/403.php';
	} else {
		\wp_die( 'Bad Request: Your request contained query variables that are unexpected. Please contact #meta.', 'Bad Request', [ 'response' => 400 ] );
	}
	exit;
}
