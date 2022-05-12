<?php

/*
 * These functions provide a simple interface for other plugins to add/remove badges and activity entries from
 * profiles.w.org.
 *
 * See `api.w.org/includes/profiles/profiles.php` for a similar function that is tailored to the API.
 */

namespace WordPressdotorg\Profiles;
use WP_Error;

/**
 * Assign a badge to a given user.
 * 
 * @param $badge string The badge group to assign.
 * @param $user  mixed  The user to assign.
 * @return bool
 */
function assign_badge( string $badge, $user ) : bool {
	return badge_api( 'add', $badge, $user );
}

/**
 * Remove a badge from a given user.
 * 
 * @param $badge string The badge group to assign.
 * @param $user  mixed  The user to assign.
 * @return bool
 */
function remove_badge( string $badge, $user ) : bool {
	return badge_api( 'remove', $badge, $user );
}

/**
 * Assign a badge to a given user.
 * 
 * @param $action string 'Add' or 'Remove'.
 * @param $badge  string The badge group to assign.
 * @param $user   mixed  The user to assign to.
 * @return bool
 */
function badge_api( string $action, string $badge, $user ) : bool {
	if ( is_object( $user ) && isset( $user->ID ) ) {
		$user = $user->ID;
	}

	if ( ! $action || ! $badge || ! is_scalar( $user ) ) {
		return false;
	}

	$request = api( [
		'action'  => 'wporg_handle_association',
		'source'  => 'generic-badge',
		'command' => $action,
		'user'    => $user,
		'badge'   => $badge,
	] );

	// Note: Success or error message may be present in the return cookies.
	return ( 200 === wp_remote_retrieve_response_code( $request ) );
}

/**
 * Send requests to Profiles and handle errors.
 *
 * `$request_args` should match what the handler expects for a particular request.
 * See `wporg-profiles-activity-handler.php` and `wporg-profiles-association-handler.php`
 *
 * @return array|WP_Error
 */
function api( array $args ) {
	$url       = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';
	$headers   = [];
	$sslverify = true;
	$error     = '';

	// Requests to w.org sandbox should also use profiles.w.org sandbox, for testing end to end.
	if ( 'staging' === wp_get_environment_type() ) {
		$url             = str_replace( 'profiles.wordpress.org', '127.0.0.1', $url );
		$sslverify       = false; // wp_remote_get() cannot verify SSL if the hostname is 127.0.0.1.
		$headers['host'] = 'profiles.wordpress.org';
	}

	// Note: Authentication is handled transparently by `enable_wporg_profiles_ajax_handler()` on Profiles.
	$response = wp_remote_post(
		$url,
		[
			'body'      => $args,
			'timeout'   => 10,
			'headers'   => $headers,
			'sslverify' => $sslverify,
		]
	);

	if ( is_wp_error( $response ) ) {
		$error = $response->get_error_message();

	} elseif ( 200 != wp_remote_retrieve_response_code( $response ) || 1 !== (int) wp_remote_retrieve_body( $response ) ) {
		$error = sprintf(
			'Error %s %s',
			$response['response']['code'],
			$response['body']
		);
	}

	if ( $error ) {
		trigger_error( wp_kses_post( $error ), E_USER_WARNING );
	}

	return $response;
}
