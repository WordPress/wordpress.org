<?php

namespace WordCamp\Privacy;

defined( 'WPINC' ) || die();

add_filter( 'privacy_policy_url', __NAMESPACE__ . '\set_privacy_policy_url', 10 );
add_filter( 'map_meta_cap', __NAMESPACE__ . '\disable_496_privacy_tools', 10, 4 );

/**
 * Set a consistent Privacy Policy across all sites.
 *
 * @param string $url
 *
 * @return string
 */
function set_privacy_policy_url( $url ) {
	return 'https://wordpress.org/about/privacy/';
}

/**
 * Disable the privacy tools added in WordPress 4.9.6.
 *
 * Note that this is temporary until we have a system in place for handling privacy-related requests
 * on a network-wide basis.
 *
 * @param array  $required_capabilities The primitive capabilities that are required to perform the requested meta capability.
 * @param string $requested_capability  The requested meta capability
 * @param int    $user_id               The user ID.
 * @param array  $args                  Adds the context to the cap. Typically the object ID.
 *
 * @return array The primitive capabilities that are required to perform the requested meta capability.
 */
function disable_496_privacy_tools( $required_capabilities, $requested_capability, $user_id, $args ) {
	$privacy_capabilities = array( 'manage_privacy_options', 'erase_others_personal_data', 'export_others_personal_data' );

	if ( in_array( $requested_capability, $privacy_capabilities ) ) {
		$required_capabilities[] = 'do_not_allow';
	}

	return $required_capabilities;
}
