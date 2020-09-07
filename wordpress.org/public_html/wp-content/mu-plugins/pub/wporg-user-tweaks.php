<?php
/**
 * Plugin Name: W.org User Tweaks
 */

// Some users have an empty display_name field, which shouldn't happen but does due to sanitization.
add_filter( 'user_display_name', function( $name, $user_id ) {
	if ( '' === $name ) {
		$name = get_user_by( 'id', $user_id )->user_nicename;
	}

	return $name;
}, 1, 2 );

// bbPress skips user filtering and does it's own
add_filter( 'bbp_get_displayed_user_field', function( $value, $field ) {
	if ( 'display_name' === $field && '' === $value ) {
		$value = bbpress()->displayed_user->user_nicename;
	}

	return $value;
}, 1, 2 );

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

	return $allcaps;
}, 1, 4 );