<?php
/**
 * Define subroles and capabilities that can be assigned to specific WordCamp users.
 *
 * @package WordCamp\SubRoles
 */

namespace WordCamp\SubRoles;
use WordPress_Community\Applications\Meetup_Application;

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
			/**
			 * Mentor Manager
			 *
			 * - Access and use the WordCamp Mentors Dashboard screen on Central.
			 * - Edit `wordcamp` posts on Central.
			 */
			case 'mentor_manager' :
				// These capabilities only apply on central.wordcamp.org.
				if ( BLOG_ID_CURRENT_SITE === get_current_blog_id() ) {
					$newcaps = array(
						'read'                       => true, // Access to wp-admin.
						'wordcamp_manage_mentors'    => true,
						'wordcamp_wrangle_wordcamps' => true,
					);
				}
				break;

			/**
			 * WordCamp Wrangler
			 *
			 * - Edit `wordcamp` posts on Central.
			 */
			case 'wordcamp_wrangler' :
				// These capabilities only apply on central.wordcamp.org.
				if ( BLOG_ID_CURRENT_SITE === get_current_blog_id() ) {
					$newcaps = array(
						'read'                       => true, // Access to wp-admin.
						'wordcamp_wrangle_wordcamps' => true,
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
	$current_user  = get_user_by( 'id', $user_id );

	switch ( $meta_cap ) {
		case 'wordcamp_manage_mentors' :
		case 'wordcamp_wrangle_wordcamps' :
			$required_caps[] = $meta_cap;
			break;

		// Allow WordCamp Wranglers to edit WordCamp posts.
		case 'edit_wordcamps' :
		case 'edit_published_wordcamps' :
		case 'edit_wordcamp' :
		case 'edit_others_wordcamps' :
			if ( $current_user && $current_user->has_cap( 'wordcamp_wrangle_wordcamps' ) ) {
				$required_caps[] = 'wordcamp_wrangle_wordcamps';
			}
			break;

		// WP_Posts_List_Table checks the `edit_post` cap regardless of post type :/
		case 'edit_post' :
			if ( ! empty( $args ) ) {
				$post_type = get_post_type( $args[0] );
			} else {
				$post_type = get_post_type();
			}

			if ( defined( 'WCPT_POST_TYPE_ID' ) && WCPT_POST_TYPE_ID === $post_type ) {
				if ( $current_user && $current_user->has_cap( 'wordcamp_wrangle_wordcamps' ) ) {
					$required_caps[] = 'wordcamp_wrangle_wordcamps';
				}
			}

			if ( Meetup_Application::POST_TYPE === $post_type ) {
				// Use same permission for meetups as well as wordcamps.
				// TODO: In future consider changing this to wrangle_events
				if ( $current_user && $current_user->has_cap( 'wordcamp_wrangle_wordcamps' ) ) {
					$required_caps[] = 'wordcamp_wrangle_wordcamps';
				}
			}
			break;
	}

	if ( ! empty( $required_caps ) ) {
		return $required_caps;
	}

	return $primitive_caps;
}

add_filter( 'map_meta_cap', __NAMESPACE__ . '\map_subrole_caps', 10, 4 );
