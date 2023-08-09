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

	if ( '' === $name && $user_id ) {
		$name = get_user_by( 'id', $user_id )->user_nicename ?? (string) $user_id;
	}

	$name = maybe_replace_blocked_user_name( $name, $user_id );

	return $name;
}, 1, 3 );

// Filter `get_the_author_meta()`.
add_filter( 'get_the_author_display_name', function( $name, $user_id ) {

	if ( '' === $name && $user_id ) {
		$name = get_user_by( 'id', $user_id )->user_nicename ?? (string) $user_id;
	}

	$name = maybe_replace_blocked_user_name( $name, $user_id );

	return $name;
}, 1, 2 );

// bbPress skips user filtering and does it's own
add_filter( 'bbp_get_displayed_user_field', function( $value, $field, $filter ) {
	if ( 'edit' === $filter ) {

		// When editing a user, if the nickname is blank, fill it in with the nice name. 
		if ( 'nickname' === $field && empty( $value ) ) {
			$value = bbp_get_displayed_user_field( 'user_nicename', 'edit' );
		}

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
 * Filter the BuddyPress displayed name.
 */
add_filter( 'bp_displayed_user_fullname', function( $name ) {
	$userdata = buddypress()->displayed_user->userdata ?? false;

	if ( ! $userdata ) {
		return $name;
	}

	if ( '' === $name ) {
		$name = $userdata->user_nicename;
	}

	$name = maybe_replace_blocked_user_name( $name, $userdata );

	return $name;
} );

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
	if ( ! ( $user instanceof WP_User ) ) {
		if ( ! empty( $user->ID ) ) {
			$user_id = $user->ID;
		} elseif ( !empty( $user->id ) ) {
			$user_id = $user->id;
		} else {
			$user_id = $user;
		}

		$user = get_user_by( 'id', $user_id );
	}

	if ( ! $user || ! $user->exists() ) {
		return $name;
	}

	// If it's a recently blocked user, it'll have a broken user_pass, use that.
	if ( 'BLOCKED' === substr( $user->user_pass, 0, 7 ) ) {
		return $user->user_nicename;
	}

	if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
		$user->for_site( WPORG_SUPPORT_FORUMS_BLOGID );
	}

	// Cannot use $user->has_cap( 'bbp_blocked' ), as we may be within a capability filter.
	// Only works on the WordPress.org network.
	if ( ! empty( $user->roles ) && in_array( 'bbp_blocked', $user->roles ) ) {
		return $user->user_nicename;
	}

	return $name;
}
