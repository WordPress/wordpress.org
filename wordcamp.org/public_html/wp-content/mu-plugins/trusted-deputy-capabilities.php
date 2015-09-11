<?php

namespace WordCamp\Trusted_Deputy_Capabilities;

/*
 * Allow trusted WordCamp Deputies to perform limited Super Admin functions
 *
 * They should be able to:
 *  - Perform administrator-level functions on all sites
 *  - Create new sites
 *  - Connect Jetpack to WordPress.com for all sites
 *  - Use the Payments dashboard
 *
 * They should not be able to network-activate plugins, modify users, write unfiltered_html, or any other
 * capability that isn't explicitly granted.
 */

/**
 * Give extra capabilities to trusted deputies.
 *
 * Uses the user_has_cap filter to add more primitive capabilities to trusted deputies.
 *
 * @param array  $allcaps   This user's capabilities.
 * @param string $caps      Requested set of capabilities.
 * @param array  $args      Adds the context to the cap.
 * @param int    $user      The WP_User object.
 *
 * @return array An array of this user's capabilities.
 */
function trusted_deputy_has_cap( $allcaps, $caps, $args, $user ) {
	if ( ! is_deputy( $user->ID ) )
		return $allcaps;

	$allcaps = array_merge( get_role( 'administrator' )->capabilities, array(
		'manage_network' => true,
		'manage_sites'   => true,

		'jetpack_network_admin_page' => true,
		'jetpack_network_sites_page' => true,
		'jetpack_network_settings_page' => true,
	) );

	return $allcaps;
}
add_filter( 'user_has_cap', __NAMESPACE__ . '\trusted_deputy_has_cap', 10, 4 );

/**
 * Filter meta-capabilities.
 *
 * Uses the map_meta_cap filter to add some additional logic around meta-caps.
 * Mainly we just map some custom meta-caps back to primitive ones.
 *
 * @param array $required_caps An array of capabilites required to perform $cap.
 * @param string $cap The requested capability.
 * @param int $user_id The user ID.
 *
 * @return array An array of required capababilities to perform $cap.
 */
function trusted_deputy_meta_caps( $required_caps, $cap, $user_id ) {
	if ( ! is_deputy( $user_id ) )
		return $required_caps;

	switch ( $cap ) {

		// With multisite and plugin menus turned off, core requires
		// the manage_network_plugins cap via a meta cap.
		case 'activate_plugins':
			if ( ! is_network_admin() ) {
				$required_caps = array( 'activate_plugins' );
			}
			break;

		// Map some Jetpack meta caps back to regular caps.
		// See https://github.com/Automattic/jetpack/commit/bf3f4b9a8eb8b689b33a106d2e9b2fefd9a4c2fb
		case 'jetpack_network_admin_page':
		case 'jetpack_network_sites_page':
		case 'jetpack_network_settings_page':
			$required_caps = array( $cap );
			break;
	}

	return $required_caps;
}
add_filter( 'map_meta_cap', __NAMESPACE__ . '\trusted_deputy_meta_caps', 10, 3 );

/**
 * Returns true if $user_id is a deputy.
 *
 * @param int $user_id A user ID.
 *
 * @return bool True if $user_id is a deputy.
 */
function is_deputy( $user_id = null ) {
	global $trusted_deputies;
	return in_array( $user_id, (array) $trusted_deputies );
}

/**
 * Automated tests for allow_trusted_deputy_capabilities()
 *
 * To use, uncomment the callback registration, and login as a trusted Deputy.
 *
 * Note: wporg_remove_super_caps() denies `import` to non-Super Admins if the domain isn't wordcamp.org, which
 * results in a false-negative on sandboxes with alternate domain names.
 */
function test_allow_trusted_deputy_capabilities() {
	$capabilities = array(
		'manage_network'     => true,
		'manage_sites'       => true,
		'activate_plugins'   => true,
		'export'             => true,
		'import'             => true,
		'edit_theme_options' => true,

		// Jetpack-specific caps.
		'jetpack_network_admin_page' => true,
		'jetpack_network_sites_page' => true,
		'jetpack_network_settings_page' => true,

		'manage_network_users'   => false,
		'manage_network_plugins' => false,
		'manage_network_themes'  => false,
		'manage_network_options' => false,
		'create_users'           => false,
		'delete_plugins'         => false,
		'delete_themes'          => false,
		'delete_users'           => false,
		'edit_files'             => false,
		'edit_plugins'           => false,
		'edit_themes'            => false,
		'edit_users'             => false,
	);

	foreach ( $capabilities as $capability => $allowed ) {
		printf(
			"<li>%s: %s should be %s and was %s</li>",
			$allowed == current_user_can( $capability ) ? 'OK' : 'ERROR',
			$capability,
			$allowed ? 'granted' : 'denied',
			current_user_can( $capability ) ? 'granted' : 'denied'
		);
	}

	wp_die();
}
// add_action( 'init', __NAMESPACE__ . '\test_allow_trusted_deputy_capabilities' );