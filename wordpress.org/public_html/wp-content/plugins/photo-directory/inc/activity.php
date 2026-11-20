<?php
/**
 * Profile activity handling.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

use WP_Post;
use function WordPressdotorg\Profiles\{ add_activity, queue };

/**
 * Sends moderation activity to WordPress.org profiles.
 *
 * A photo is reviewed when a moderator approves (publishes) or rejects it.
 * The moderator gets a daily "Reviewed N photos" digest, and, when the
 * photo is approved, its contributor gets a "Published photo" entry.
 */
class Activity {

	/**
	 * Initializes component.
	 */
	public static function init() {
		if ( ! function_exists( 'WordPressdotorg\Profiles\add_activity' ) ) {
			return;
		}

		add_action( 'transition_post_status', [ __CLASS__, 'status_transitions' ], 10, 3 );
	}

	/**
	 * Sends profile activity when a photo is moderated.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function status_transitions( $new_status, $old_status, $post ) {
		$reviewer_id = self::get_reviewer_id( $new_status, $old_status, $post, get_current_user_id() );

		// The review is a daily digest, which the `photos` source of the Profiles handler keeps.
		if ( $reviewer_id ) {
			queue(
				[
					'action'    => 'wporg_handle_activity',
					'source'    => 'photos',
					'component' => 'photos',
					'type'      => 'photos_photo_reviewed',
					'user_id'   => $reviewer_id,
				]
			);
		}

		if ( self::is_approval( $new_status, $old_status, $post ) ) {
			add_activity( 'photos', 'photos_photo_published', (int) $post->post_author, self::get_published_args( $post ) );
		}
	}

	/**
	 * Determines if a change of post status approves or rejects a photo for the first time.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return bool
	 */
	public static function is_review( $new_status, $old_status, $post ) {
		// Only concerned with photo post type.
		if ( Registrations::get_post_type() !== get_post_type( $post ) ) {
			return false;
		}

		$reviewed_statuses = [ 'publish', Rejection::get_post_status() ];

		return in_array( $new_status, $reviewed_statuses, true ) && ! in_array( $old_status, $reviewed_statuses, true );
	}

	/**
	 * Determines if a change of post status approves a photo for the first time.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return bool
	 */
	public static function is_approval( $new_status, $old_status, $post ) {
		return 'publish' === $new_status && self::is_review( $new_status, $old_status, $post ) && (int) $post->post_author > 0;
	}

	/**
	 * Returns the user to credit with reviewing a photo.
	 *
	 * Reviewing one's own photo, or changing its status with no current user (like from WP-CLI), isn't credited.
	 *
	 * @param string  $new_status   New post status.
	 * @param string  $old_status   Old post status.
	 * @param WP_Post $post         Post object.
	 * @param int     $moderator_id ID of the user changing the status, 0 if none.
	 * @return int The user ID, or 0 if no one is credited.
	 */
	public static function get_reviewer_id( $new_status, $old_status, $post, $moderator_id ) {
		$moderator_id = (int) $moderator_id;

		if ( ! $moderator_id || (int) $post->post_author === $moderator_id ) {
			return 0;
		}

		return self::is_review( $new_status, $old_status, $post ) ? $moderator_id : 0;
	}

	/**
	 * Returns the activity args for a published photo, for the contributor's profile.
	 *
	 * @param WP_Post $post Post object.
	 * @return array The args for `add_activity()`.
	 */
	public static function get_published_args( $post ) {
		$permalink = get_permalink( $post );

		return [
			'item_id'      => $post->ID,
			'primary_link' => $permalink,
			'message'      => sprintf(
				'Published photo <a href="%1$s">%2$s</a> on <a href="%3$s">wordpress.org/photos/</a>.',
				esc_url( $permalink ),
				esc_html( get_the_title( $post ) ),
				'https://wordpress.org/photos/'
			),
		];
	}
}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Activity', 'init' ] );
