<?php

namespace WordCamp\Jetpack_Tweaks;
defined( 'WPINC' ) || die();

// Allow Photon to fetch images that are served via HTTPS
add_filter( 'jetpack_photon_reject_https',    '__return_false' );

// Disable Snow to avoid performance and accessibility problems
add_filter( 'jetpack_is_holiday_snow_season', '__return_false' );

/**
 * Modify hooks after Jetpack::init()
 *
 * @todo This may be unnecessary and removable since there is now a similar wporg mu-plugin. See [dotorg13221].
 */
function modify_hooks_after_jetpack_init() {
	/*
	 * Many of these messages are just marketing upsells for services we don't need, don't want, or have
	 * alternatives for already. Those messages aren't relevant to organizers, so they just intrude on their work
	 * unnecessarily.
	 */
	add_filter( 'jetpack_just_in_time_msgs', '__return_false', 11 );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\modify_hooks_after_jetpack_init' );

/**
 * Filter the post types Jetpack has access to, and can synchronize with WordPress.com.
 *
 * @see Jetpack's WPCOM_JSON_API_ENDPOINT::_get_whitelisted_post_types();
 *
 * @param array $allowed_types Array of whitelisted post types.
 *
 * @return array Modified array of whitelisted post types.
 */
function add_post_types_to_rest_api( $allowed_types ) {
	$allowed_types += array( 'wcb_speaker', 'wcb_session', 'wcb_sponsor' );

	return $allowed_types;
}

add_filter( 'rest_api_allowed_post_types', __NAMESPACE__ . '\add_post_types_to_rest_api' );
