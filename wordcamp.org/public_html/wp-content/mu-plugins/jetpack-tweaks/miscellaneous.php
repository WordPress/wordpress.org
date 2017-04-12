<?php

namespace WordCamp\Jetpack_Tweaks;
defined( 'WPINC' ) or die();

add_action( 'plugins_loaded', __NAMESPACE__ . '\modify_hooks_after_jetpack_init' );

// Allow Photon to fetch images that are served via HTTPS
add_filter( 'jetpack_photon_reject_https',    '__return_false' );

// Disable Snow to avoid performance and accessibility problems
add_filter( 'jetpack_is_holiday_snow_season', '__return_false' );

/**
 * Modify hooks after Jetpack::init()
 */
function modify_hooks_after_jetpack_init() {
	/*
	 * Many of these messages are just marketing upsells for services we don't need, don't want, or have
	 * alternatives for already. Those messages aren't relevant to organizers, so they just intrude on their work
	 * unnecessarily.
	 */
	add_filter( 'jetpack_just_in_time_msgs', '__return_false' );
}
