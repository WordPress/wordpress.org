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
 * Determine if a user has a given badge
 *
 * @param $badge string The badge slug to check for.
 * @param $user  mixed  The user to check. A WP_User/ID/Login/Email.
 * @return bool
 */
function has_badge( string $badge, $user ) : bool {
	return isset( get_user_badges( $user )[ $badge ] );
}

/**
 * Get a list of users with a given badge.
 *
 * WARNING: Uncached. Excludes dynamically allocated badges.
 *
 * @param $badge string The badge group we're looking for.
 * @return array An array of user IDs.
 */
function get_users_with_badge( string $badge ) : array {
	global $wpdb;

	$users = $wpdb->get_col( $wpdb->prepare(
		"SELECT user_id
			FROM bpmain_wporg_groups_members m
			JOIN bpmain_wporg_groups g ON m.group_id = g.id
			WHERE g.slug = %s AND m.is_confirmed = 1 AND m.is_banned = 0",
		$badge
	) );

	return array_map( 'intval', $users );
}

/**
 * Get a list of badges for a given user.
 *
 * WARNING: Uncached. Excludes dynamically allocated badges.
 *
 * @param $users mixed The user to fetch the badged for. A WP_User/ID/Login/Email.
 * @return array An array of badge names keyed by slug.
 */
function get_user_badges( $user ) {
	global $wpdb;

	$user_id = find_user_id( $user );

	$badges = $wpdb->get_results( $wpdb->prepare(
		"SELECT slug, name
			FROM bpmain_wporg_groups_members m
			JOIN bpmain_wporg_groups g ON m.group_id = g.id
			WHERE m.user_id = %d AND m.is_confirmed = 1 AND m.is_banned = 0
			ORDER BY slug",
		$user_id
	), ARRAY_A );

	return array_column( $badges, 'name', 'slug' );
}

/**
 * Fetch the profiles data for a given user.
 *
 * NOTE: The returned array is currently uncached and if a field is empty/blank it's not set here.
 *
 * @param $user mixed The user to fetch the profile for. A WP_User/ID/Login/Email.
 * @return array|WP_Error An array of profile fields keyed by field name.
 */
function get_user_details( $user ) {
	global $wpdb;

	$user_id = find_user_id( $user );

	$data = $wpdb->get_results( $wpdb->prepare(
		"SELECT f.name, d.value, f.type, d.last_updated
			FROM bpmain_bp_xprofile_data d
				JOIN bpmain_bp_xprofile_fields f ON d.field_id = f.id
			WHERE d.user_id = %d",
		$user_id
	) );

	$result = [];
	foreach ( $data as $field ) {
		$name = sanitize_title_with_dashes( $field->name );
		if ( str_contains( $field->type, 'multi' ) || $field->type === 'checkbox' ) {
			$field->value = maybe_unserialize( $field->value );
		}
		$result[ $name ] = $field;
	}

	return $result;
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
 * @param $action string The action to perform; 'add', 'remove', 'list'.
 * @param $badge  string The badge group to assign.
 * @param $users  mixed  The user(s) to assign to. A WP_User/ID/Login/Email/Slug (or array of) of the user(s) to assign.
 * @return bool
 */
function badge_api( string $action, string $badge, $users = array() ) : bool {
	$users = is_object( $users ) ? [ $users ] : (array) $users;
	$users = array_filter( array_map( __NAMESPACE__ . '\find_user_id', $users ) );

	if ( ! $action || ! $badge || ! $users ) {
		return false;
	}

	if ( 'remove' === $action ) {
		$users = array_filter(
			$users,
			function( $user ) use ( $badge ) {
				return has_badge( $badge, $user );
			}
		);
	} elseif ( 'add' === $action ) {
		$users = array_filter(
			$users,
			function( $user ) use ( $badge ) {
				return ! has_badge( $badge, $user );
			}
		);
	}
	// If there are no users now, then the action must have already occured.
	if ( ! $users ) {
		return true;
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
 * Find a user ID from a variety of inputs.
 *
 * @param $user mixed A WP_User object, user ID, login, email, or slug.
 * @return int|false
 */
function find_user_id( $user ) {
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
