<?php
namespace WordPressdotorg\User_Tweaks;
use WP_User;
use add_filter;

/**
 * Plugin Name: W.org User Tweaks
 */

// Some users have an empty display_name field, which shouldn't happen but does due to sanitization.
add_filter( 'user_display_name', function( $name, $user_id, $context ) {
	if ( 'edit' === $context ) {
		return $name;
	}

	if ( '' === $name ) {
		$name = get_user_by( 'id', $user_id )->user_nicename;
	}

	$name = maybe_replace_blocked_user_name( $name, $user_id );

	return $name;
}, 1, 3 );

// bbPress skips user filtering and does it's own
add_filter( 'bbp_get_displayed_user_field', function( $value, $field, $filter ) {
	if ( 'edit' === $filter ) {
		return $value;
	}

	if ( 'display_name' === $field ) {
		if ( '' === $value ) {
			$value = bbpress()->displayed_user->user_nicename;
		}

		$value = maybe_replace_blocked_user_name( $value, bbpress()->displayed_user );
	}

	return $value;
}, 1, 3 );

/**
 * Some users have an empty display_name field, which shouldn't happen, but does due to sanitization.
 *
 * bbPress also doesn't use it's own functions sometimes, and instead uses get_userdata( bbp_get_user_id() )
 * 
 * Users have no good generic filters, so we hook in on the capabilities filter, in addition to the above filters which are used elsewhere.
 */
add_filter( 'user_has_cap', function( $allcaps, $caps, $args, $wp_user ) {
	if ( '' === $wp_user->display_name ) {
		$wp_user->display_name = $wp_user->user_nicename;
	}

	// Intentionally not used, as this will override in edit contexts too.
	//$wp_user->display_name = maybe_replace_blocked_user_name( $wp_user->display_name, $wp_user );

	return $allcaps;
}, 1, 4 );

/**
 * Use the nicename field for blocked users.
 */
function maybe_replace_blocked_user_name( $name, $user ) {
	if ( ! defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
		return $name;
	}

	$user_id = is_object( $user ) ? $user->ID : $user;
	if ( ! $user_id ) {
		return $name;
	}

	if ( ! ( $user instanceof WP_User ) ) {
		$user = get_user_by( 'id', $user );
	}

	if ( ! $user ) {
		return $name;
	}

	$user->for_site( WPORG_SUPPORT_FORUMS_BLOGID );

	/* Cannot use $user->has_cap( 'bbp_blocked' ), as we may be within a capability filter. */
	if ( !empty( $user->roles ) && in_array( 'bbp_blocked', $user->roles ) ) {
		return $user->user_nicename;
	}

	return $name;
}
