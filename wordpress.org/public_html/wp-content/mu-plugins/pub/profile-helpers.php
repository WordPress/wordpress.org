<?php
namespace WordPressdotorg\Profiles;

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
 * See `wporg-profiles-activity-handler.php` and `wporg-profiles-association-handler.php`.
 */
function api( array $args ) {
	$url       = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';
	$headers   = [];
	$sslverify = true;

	// Requests to w.org sandbox should also use profiles.w.org sandbox, for testing end to end.
	if ( 'staging' === wp_get_environment_type() ) {
		$url       = str_replace( 'profiles.wordpress.org', '127.0.0.1', $url );
		$sslverify = false; // wp_remote_get() cannot verify SSL if the hostname is 127.0.0.1.
	}

	// Note: Authentication is handled elsewhere transparently.
	return wp_remote_post(
		$url,
		[
			'body'      => $args,
			'headers'   => [
				'host' => 'profiles.wordpress.org',
			],
			'sslverify' => $sslverify,
		]
	);
}