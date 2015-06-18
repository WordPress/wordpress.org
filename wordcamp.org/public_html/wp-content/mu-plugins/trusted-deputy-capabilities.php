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
 * Give extra capabilities to trusted Deputies
 *
 * @param array  $required_capabilities The primitive capabilities that are required to perform the requested meta capability
 * @param string $requested_capability  The requested meta capability
 * @param int    $user_id               The user ID.
 * @param array  $args                  Adds the context to the cap. Typically the object ID.
 *
 * @return array
 */
function allow_trusted_deputy_capabilities( $required_capabilities, $requested_capability, $user_id, $args ) {
	$allow_capability = true;

	if ( ! user_is_trusted_deputy( $user_id ) ) {
		$allow_capability = false;
	} else if ( in_array( 'do_not_allow', $required_capabilities ) ) {
		$allow_capability = false;
	} else if ( ! is_allowed_capability( $requested_capability, $required_capabilities ) ) {
		$allow_capability = false;
	}

	if ( $allow_capability ) {
		$required_capabilities = array();
	}

	return $required_capabilities;
}
add_filter( 'map_meta_cap', __NAMESPACE__ . '\allow_trusted_deputy_capabilities', 10, 4 );

/**
 * Determine if the given user is a trusted Deputy
 *
 * @param int $user_id
 *
 * @return bool
 */
function user_is_trusted_deputy( $user_id ) {
	$trusted_deputies = array(
		642041,   // brandondove
		385876,   // kcristiano
		14470969, // trusteddeputy
		499931,   // karenalma
	);

	return in_array( $user_id, $trusted_deputies );
}

/**
 * Determine if the given capability should be allowed for trusted Deputies
 *
 * @param string $capability
 * @param array  $dependent_capabilities
 *
 * @return bool
 */
function is_allowed_capability( $capability, $dependent_capabilities ) {
	$allowed = false;
	$deputy_capabilities = get_trusted_deputy_capabilities();
	
	if ( array_key_exists( $capability, $deputy_capabilities ) ) {
		$allowed = true;
	} else {
		foreach ( $dependent_capabilities as $dependent_capability ) {
			if ( array_key_exists( $dependent_capability, $deputy_capabilities ) ) {
				$allowed = true;
				break;
			}
		}
	}

	return $allowed;
}

/**
 * Get the capabilities that trusted Deputies should have
 *
 * @return array
 */
function get_trusted_deputy_capabilities() {
	$administrator_role = get_role( 'administrator' );

	$capabilities = array_merge(
		$administrator_role->capabilities,
		array(
			'manage_network' => true,
			'manage_sites'   => true,
		)
	);

	return $capabilities;
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
			"<li>%s should be %s and was %s</li>",
			$capability,
			$allowed ? 'granted' : 'denied',
			current_user_can( $capability ) ? 'granted' : 'denied'
		);
	}

	wp_die();
}
//add_action( 'init', __NAMESPACE__ . '\test_allow_trusted_deputy_capabilities' );
