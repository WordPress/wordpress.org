<?php

/*
 * These functions provide a simple interface for other plugins to modify WordPress.org Profile data, such as badges, activity entries, and xProfile fields.
 *
 * See `api.w.org/includes/profiles/profiles.php` for a similar badge function that is tailored to the API.
 */

namespace WordPressdotorg\Profiles;
use WP_Error, WP_User;

/**
 * Assign a badge to a given user.
 * 
 * @param $badge string The badge group to assign.
 * @param $users mixed  The user(s) to assign. A WP_User/ID/Login/Email (or array of) of the user(s) to assign.
 * @return bool
 */
function assign_badge( string $badge, $users ) : bool {
	return badge_api( 'add', $badge, $users );
}

/**
 * Remove a badge from a given user.
 * 
 * @param $badge string The badge group to assign.
 * @param $users mixed  The user(s) to assign. A WP_User/ID/Login/Email (or array of) of the user(s) to assign.
 * @return bool
 */
function remove_badge( string $badge, $users ) : bool {
	return badge_api( 'remove', $badge, $users );
}

/**
 * Record an activity item for a user.
 * 
 * @param $component string     The component to be used for the acitivity.
 * @param $type      string     The type of the activity in that component.
 * @param $user      int|string ID, Login, or Slug of user.
 * @param $args      array      The args for the activity item. See `bp_activity_add()`.
 */
function add_activity( string $component, string $type, $user, array $args ) {
	$request = api( [
		'action'    => 'wporg_handle_activity',
		'source'    => 'generic',
		'component' => $component,
		'type'      => $type,
		'user'      => $user,
		'args'      => $args,
	] );

	return ( 200 === wp_remote_retrieve_response_code( $request ) );
}

/**
 * Update a BuddyPress profile field with a value.
 *
 * @param $field string      The profile field name to update.
 * @param $value mixed       The value to update it to.
 * @param $user  int|WP_User The user object or user ID to update.
 * @return bool
 */
function update_profile( $field, $value, $user ) {
	$request = api( [
		'action' => 'wporg_update_profile',
		'user'   => $user instanceOf WP_User ? $user->ID : $user,
		'fields' => [
			$field => $value
		],
	] );

	return ( 200 === wp_remote_retrieve_response_code( $request ) );
}

/**
 * Assign a badge to a given user.
 * 
 * @param $action string The action to perform; 'add' or 'remove'.
 * @param $badge  string The badge group to assign.
 * @param $users  mixed  The user(s) to assign to. A WP_User/ID/Login/Email/Slug (or array of) of the user(s) to assign.
 * @return bool
 */
function badge_api( string $action, string $badge, $users ) : bool {
	$users = (array) $users;
	$users = array_filter( array_map( function( $user ) {
		// WP_User-like object.
		if ( is_object( $user ) ) {
			return $user->ID ?? false;
		}

		// User ID.
		if ( is_numeric( $user ) && absint( $user ) == $user ) {
			return (int) $user;
		}

		// Support user login / email / slug.
		$_user = get_user_by( 'login', $user );
		if ( ! $_user && is_email( $user ) ) {
			$_user = get_user_by( 'email', $user );
		}
		if ( ! $_user ) {
			$_user = get_user_by( 'slug', $user );
		}

		return $_user->ID ?? false;
	}, $users ) );

	if ( ! $action || ! $badge || ! $users ) {
		return false;
	}

	$request = api( [
		'action'  => 'wporg_handle_association',
		'source'  => 'generic-badge',
		'command' => $action,
		'users'   => $users,
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
