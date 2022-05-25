<?php
/**
 * Profile Badges handling.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;
use function WordPressdotorg\Profiles\{ assign_badge, remove_badge };

class Badges {

	public static function init() {
		if ( ! function_exists( 'WordPressdotorg\Profiles\assign_badge' ) ) {
			return;
		}

		add_action( 'transition_post_status', [ __CLASS__, 'status_transitions' ], 10, 3 );
		add_action( 'remove_user_from_blog',  [ __CLASS__, 'remove_user_from_blog' ], 10, 1 );
		add_action( 'set_user_role',          [ __CLASS__, 'set_user_role' ], 10, 2 );
	}

	/**
	 * Watch for post status changes, and assign (or remove) the Photo Contributor badge as appropriate.
	 */
	public static function status_transitions( $new_status, $old_status, $post ) {
		$post = get_post( $post );

		if ( Registrations::get_post_type() !== get_post_type( $post ) ) {
			return;
		}

		if ( 'publish' === $new_status ) {
			assign_badge( 'photo-contributor', $post->post_author );

		} elseif ( 'publish' === $old_status && 'publish' !== $new_status ) {
			// If the user now has no published Photos, remove the badge.
			if ( ! Photo::count_user_published_photos( $post->post_author ) ) {
				remove_badge( 'photo-contributor', $post->post_author );
			}
		}
	}

	/**
	 * Remove the 'Photos Team' badge from a user when they're removed from the Photos site.
	 */
	public static function remove_user_from_blog( $user_id ) {
		remove_badge( 'photos-team', $user_id );
	}

	/**
	 * Add/Remove the 'Photos Team' badge from a user when their role changes.
	 *
	 * The badge is added for all roles except for Contributor and Subscriber.
	 * The badge is removed when the role is set to Contributor or Subscriber.
	 */
	public static function set_user_role( $user_id, $role ) {
		if ( 'subscriber' === $role || 'contributor' === $role ) {
			remove_badge( 'photos-team', $user_id );
		} else {
			assign_badge( 'photos-team', $user_id );
		}
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Badges', 'init' ] );