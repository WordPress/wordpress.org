<?php
/**
 * Define subroles and capabilities that can be assigned to specific WordCamp users.
 *
 * @package WordCamp\SubRoles
 */

namespace WordCamp\SubRoles;
defined( 'WPINC' ) || die();

/**
 * Get any subroles assigned to a specific user.
 *
 * @param int $user_id The ID of the user to retrieve subroles for.
 *
 * @return array A list of subrole strings.
 */
function get_user_subroles( $user_id ) {
	global $wcorg_subroles;

	if ( is_array( $wcorg_subroles ) && isset( $wcorg_subroles[ $user_id ] ) ) {
		return $wcorg_subroles[ $user_id ];
	}

	return array();
}

/**
 * Check if a particular user has a particular subrole.
 *
 * @param int    $user_id The ID of the user to check for a subrole.
 * @param string $subrole The subrole to check for.
 *
 * @return bool True if the user has the subrole.
 */
function has_subrole( $user_id, $subrole ) {
	$subroles = get_user_subroles( $user_id );

	return in_array( $subrole, $subroles, true );
}

/**
 * Check if the current request is proxied.
 *
 * @return bool True if the request is proxied.
 */
function is_proxied() {
	return defined( 'WPORG_PROXIED_REQUEST' ) && WPORG_PROXIED_REQUEST;
}

/**
 * Add capabilities to a user depending on their subroles.
 *
 * @param array    $allcaps The original list of caps for the given user.
 * @param array    $caps    Unused.
 * @param array    $args    Unused.
 * @param \WP_User $user    The user object.
 *
 * @return array The modified list of caps for the given user.
 */
function add_subrole_caps( $allcaps, $caps, $args, $user ) {
	$subroles = get_user_subroles( $user->ID );

	if ( empty( $subroles ) ) {
		return $allcaps;
	}

	foreach ( $subroles as $subrole ) {
		$newcaps = array();
		
		switch ( $subrole ) {
			case 'jetpack_connector' :
				$newcaps = array(
					'manage_network'             => true, // Access to network admin.
					'jetpack_connect'            => true,
					'jetpack_reconnect'          => true,
					'jetpack_disconnect'         => true,
					'jetpack_network_admin_page' => true,
					'jetpack_network_sites_page' => true,
					'jetpack_manage_modules'     => true,
				);
				break;

			case 'mentor_manager' :
				// These capabilities only apply on central.wordcamp.org.
				if ( BLOG_ID_CURRENT_SITE === get_current_blog_id() ) {
					$newcaps = array(
						'read'                    => true, // Access to wp-admin.
						'edit_posts'              => true, // Access to WCPT posts.
						'edit_published_posts'    => true, // Access to WCPT posts.
						'wordcamp_manage_mentors' => true,
					);
				}
				break;
		}

		$allcaps = array_merge( $allcaps, $newcaps );
	}

	return $allcaps;
}

add_filter( 'user_has_cap', __NAMESPACE__ . '\add_subrole_caps', 10, 4 );

/**
 * Capability mapping for subroles.
 *
 * @param array  $primitive_caps The original list of primitive caps mapped to the given meta cap.
 * @param string $meta_cap       The meta cap in question.
 * @param int    $user_id        The ID of the user.
 * @param array  $args           Additional information for the cap.
 *
 * @return array The modified list of primitive caps mapped to the given meta cap.
 */
function map_subrole_caps( $primitive_caps, $meta_cap, $user_id, $args ) {
	$required_caps = array();

	switch ( $meta_cap ) {
		case 'wordcamp_manage_mentors' :
			$required_caps[] = $meta_cap;
			break;

		// Allow mentor managers to edit WCPT posts.
		// @todo Change the capability type of WCPT to something custom so this isn't necessary.
		case 'edit_post' :
		case 'edit_others_posts' :
			if ( defined( 'WCPT_POST_TYPE_ID' ) && current_user_can( 'wordcamp_manage_mentors' ) ) {
				if ( ! empty( $args ) ) {
					$post_type = get_post_type( $args[0] );
				} else {
					$post_type = get_post_type();
				}

				if ( WCPT_POST_TYPE_ID === $post_type ) {
					$wcpt = get_post_type_object( WCPT_POST_TYPE_ID );
					$required_caps[] = $wcpt->cap->edit_posts;
				}
			}
			break;

		// Allow Jetpack Connectors to do connector stuff without needing caps like `manage_network_plugins`.
		// See Jetpack::jetpack_custom_caps()
		case 'jetpack_connect':
		case 'jetpack_reconnect':
		case 'jetpack_disconnect':
		case 'jetpack_network_admin_page':
		case 'jetpack_network_sites_page':
		case 'jetpack_manage_modules':
			if ( has_subrole( get_current_user_id(), 'jetpack_connector' ) ) {
				$required_caps[] = $meta_cap;
			}
			break;
	}

	if ( ! empty( $required_caps ) ) {
		return $required_caps;
	}

	return $primitive_caps;
}

add_filter( 'map_meta_cap', __NAMESPACE__ . '\map_subrole_caps', 10, 4 );
