<?php

namespace WordCamp\Jetpack_Tweaks;
defined( 'WPINC' ) or die();

add_filter( 'jetpack_get_default_modules',                     __NAMESPACE__ . '\default_jetpack_modules'       );
add_filter( 'pre_update_site_option_jetpack-network-settings', __NAMESPACE__ . '\auto_connect_new_sites', 10, 2 );
add_action( 'wpmu_new_blog',                                   __NAMESPACE__ . '\schedule_connect_new_site'     );
add_action( 'wcorg_connect_new_site',                          __NAMESPACE__ . '\wcorg_connect_new_site', 10, 2 );

/*
 * Determine which Jetpack modules should be automatically activated when new sites are created
 */
function default_jetpack_modules( $modules ) {
	$modules = array_diff( $modules, array( 'widget-visibility' ) );
	array_push( $modules, 'contact-form', 'shortcodes', 'custom-css', 'subscriptions' );

	return $modules;
}

/**
 * Never automatically connect new sites to WordPress.com.
 * We offload this part to wp-cron.php because of https.
 *
 * @param array $new_value
 * @param array $old_value
 *
 * @return array
 */
function auto_connect_new_sites( $new_value, $old_value ) {
	$new_value['auto-connect'] = 0;

	return $new_value;
}

/**
 * Schedule an attempt to connect this site to Jetpack.
 *
 * @param int $blog_id The blog id.
 */
function schedule_connect_new_site( $blog_id ) {
	wp_schedule_single_event(
		time() + 12 * HOUR_IN_SECONDS + 600,
		'wcorg_connect_new_site',
		array( $blog_id, get_current_user_id() )
	);
}

/**
 * Connect the new site to Jetpack. Runs during wp-cron.php.
 *
 * @param int $blog_id The blog_id to connect.
 * @param int $user_id The user ID who created the new site.
 */
function wcorg_connect_new_site( $blog_id, $user_id ) {
	if ( ! class_exists( 'Jetpack_Network' ) ) {
		return;
	}

	$network         = \Jetpack_Network::init();
	$current_user_id = get_current_user_id();

	wp_set_current_user( $user_id );
	$network->do_subsiteregister( $blog_id );
	wp_set_current_user( $current_user_id );

	error_log( sprintf( 'Connecting new site %d for user %d.', $blog_id, $user_id ) );
}
