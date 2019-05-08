<?php

defined( 'WPINC' ) or die();
use function WordCamp\Logger\log;

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
			log( 'request_failed_temporarily', compact( 'request_url', 'request_args', 'response', 'attempt_count', 'retry_after' ) );
			sleep( $retry_after );
		} else {
			log( 'request_failed_permenantly', compact( 'request_url', 'request_args', 'response' ) );
			break;
		}

		$attempt_count++;
	}

	return $response;
}

/**
 * Display the indicator that marks a form field as required
 */
function wcorg_required_indicator() {
	?>

	<span class="wcorg-required" aria-hidden="true">
		<?php // translators: The symbol to indicate the form field is required ?>
		<?php _e( '*', 'wordcamporg' ); ?>
	</span>

	<span class="screen-reader-text">
		<?php _e( 'required field', 'wordcamporg' ); ?>
	</span>

	<?php
}
