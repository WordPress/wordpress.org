<?php

defined( 'WPINC' ) or die();
use WordCamp\Logger;

/*
 * Miscellaneous helper functions.
 */


/**
 * Determine if a specific feature should be skipped on the current site
 *
 * Often times we want to add new functionality to plugins and themes, but can't let it run on older sites
 * because that would break backwards compatibility. To get around that, we set a flag on older sites to
 * indicate that they should not have the new feature, and then setup the feature to run on sites that
 * don't have the flag, i.e., to run by default.
 *
 * Doing it this way means that local development environments like the Meta Environment don't to have add any
 * new filters in order to start using the new functionality.
 *
 * See WordCamp_CLI_Miscellaneous::set_skip_feature_flag() for how to set the flags.
 *
 * @param string $flag
 *
 * @return bool
 */
function wcorg_skip_feature( $flag ) {
	$flags = get_option( 'wordcamp_skip_features', array() );

	return isset( $flags[ $flag ] );
}

/**
 * Get a user by the username or nicename
 *
 * Note: This intentionally doesn't lookup users by the display name or nickname, because those can be set by the
 * user, which could result in false-positive matches.
 *
 * @param string $name
 *
 * @return false|WP_User
 */
function wcorg_get_user_by_canonical_names( $name ) {
	if ( ! $user = get_user_by( 'login', $name ) ) {    // user_login
		$user = get_user_by( 'slug', $name );           // user_nicename
	}

	return $user;
}

/**
 * Get ISO-3166 country names and codes
 *
 * @todo move the real functionality from get_valid_countries_iso3166() to here, then have the Budgets plugin,
 * QBO, etc call this.
 *
 * @return array
 */
function wcorg_get_countries() {
	require_once( WP_PLUGIN_DIR . '/wordcamp-payments/includes/wordcamp-budgets.php' );

	return WordCamp_Budgets::get_valid_countries_iso3166();
}

/**
 * Make a remote HTTP request, and retry if it fails
 *
 * Sometimes the HTTP request times out, or there's a temporary server-side error, etc. Some use cases require a
 * successful request, like stats scripts, where the resulting data would be distorted by a failed response.
 *
 * @todo Add support for wp_remote_post() too
 * @todo Remove this if https://github.com/rmccue/Requests/issues/222 is implemented
 * @todo maybe `set_time_limit( absint( ini_get( 'max_execution_time' ) ) + $retry_after );` before sleep()'ing to
 *       avoid php timeout
 *
 * @param string $request_url
 * @param array  $request_args
 *
 * @return array|WP_Error
 */
function wcorg_redundant_remote_get( $request_url, $request_args = array() ) {
	$attempt_count = 1;

	while ( true ) {
		$response    = wp_remote_get( $request_url, $request_args );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$retry_after = wp_remote_retrieve_header( $response, 'retry-after' ) ?: 5;
		$retry_after = min( $retry_after * $attempt_count, 30 );

		if ( ! is_wp_error( $response ) && 200 === $status_code && $body ) {
			break;
		}

		if ( $attempt_count < 3 ) {
			Logger\log( 'request_failed_temporarily', compact( 'request_url', 'request_args', 'response', 'attempt_count', 'retry_after' ) );
			sleep( $retry_after );
		} else {
			Logger\log( 'request_failed_permenantly', compact( 'request_url', 'request_args', 'response' ) );
			break;
		}

		$attempt_count++;
	}

	return $response;
}

/**
 * Register post meta so that it only appears on a specific REST API endpoint
 *
 * As of WordPress 4.7, there's no way to register a meta field for a specific post type. Registering a
 * field registers it for all post types, and registering it with `show_in_rest => true` exposes it in all
 * API endpoints. Doing that could lead to unintentional privacy leaks. There's no officially-supported
 * way to avoid that, other than using `register_rest_field()`.
 *
 * See https://core.trac.wordpress.org/ticket/38323
 *
 * `register_rest_field()` isn't an ideal solution for post meta, though, because it's logically
 * inconsistent; i.e., meta fields would not show up in the `meta` tree of the response, where other meta
 * fields are located, and where a client would expect to find them. Instead, meta fields would show up as
 * top-level fields in the response, as if they were first-class `post` object fields, or as if they were
 * arbitrary fields (which is what `register_rest_field()` is really intended for).
 *
 * Having meta fields at the top-level also clutters the post item, making it harder to read, and annoying
 * the crap out of grumpy, old, anal-retentive developers like @iandunn.
 *
 * So, in order to safely add meta fields in the `meta` tree where they belong, but without exposing them
 * on endpoints where they don't belong, an ugly workaround is used. `register_meta()` is only called
 * for `wcb_session` meta fields during API requests for the `sessions` endpoint. During all other API
 * and non-API requests, it is not called.
 *
 * This only works if you don't need the meta registered in non-API contexts, but that's usually true.
 *
 * @todo Once #38323 is resolved, this can be removed and the calling functions can be updated to use
 *       whatever the officially supported solution turns out to be.
 *
 * @param string $meta_type   Type of object this meta is registered to. 'post', 'user', 'term', etc
 * @param array  $meta_fields An array index by the field slug, with values to be passed to `register_meta()` as
 *                            `$args`. For example, `array( '_wcpt_session_slides' => array( 'single' => true ) )`
 * @param string $endpoint    The partial path of the endpoint. For example, '/wp-json/wp/v2/sessions'.
 */
function wcorg_register_meta_only_on_endpoint( $meta_type, $meta_fields, $endpoint ) {
	$is_correct_endpoint_request = false !== strpos( $_SERVER['REQUEST_URI'], untrailingslashit( $endpoint ) );

	if ( ! $is_correct_endpoint_request ) {
		return;
	}

	foreach ( $meta_fields as $field_key => $arguments ) {
		$arguments = array_merge( $arguments, array( 'show_in_rest' => true ) );

		register_meta( $meta_type, $field_key, $arguments );
	}
}
