<?php
/**
 * User-related functionality.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class User {

	/**
	 * Maximum number of pending/concurrent submissions.
	 *
	 * Once this threshold is met, a user will be unable to make another
	 * submission until a current submission is approved or rejected.
	 *
	 * @see `get_concurrent_submission_limit()` for actually retrieving the maximum
	 * pending submissions for a user, since it can vary based on the user and may
	 * be filtered.
	 * @var int
	 */
	const MAX_PENDING_SUBMISSIONS = 5;

	/**
	 * The number of published posts before a given user is permitted to toggle
	 * all of the confirmation checkboxes when submitting a photo.
	 *
	 * @var int
	 */
	const TOGGLE_ALL_THRESHOLD = 30;

	public static function init() {
	}

	/**
	 * Returns a count of published photos for a user.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes global
	 *                     author. Default false.
	 * @return int
	 */
	public static function count_published_photos( $user_id = false ) {
		if (  ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID;
		}

		return count_user_posts( $user_id, Registrations::get_post_type(), true );
	}

	/**
	 * Returns a count of pending photos for a user.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes global
	 *                     author. Default false.
	 * @return int
	 */
	public static function count_pending_photos( $user_id = false ) {
		if (  ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID;
		}

		$pending = get_posts( [
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'author'         => $user_id,
			'post_status'    => 'pending',
			'post_type'      => Registrations::get_post_type(),
		] );

		return count( $pending );
	}

	/**
	 * Returns a count of rejected photos for a user.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes global
	 *                     author. Default false.
	 * @return int
	 */
	public static function count_rejected_photos( $user_id = false ) {
		global $wpdb;

		if (  ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID;
		}

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s AND post_author = %d",
			Registrations::get_post_type(),
			Rejection::get_post_status(),
			$user_id
		) );
	}

	/**
	 * Determines if a user is eligible to toggle all confirmation checkboxes on
	 * the photo upload form.
	 *
	 * The intent is that users who have submitted a decent number of published
	 * photos are as aware of the listed criteria as they're going to be and have
	 * demonstrated that they are able to abide by them.
	 *
	 * @todo Handle eventual case when a new checkbox is added or one is changed
	 *       enough to warrant making the user manually re-check the checkboxes.
	 *       The latest checkbox update date can be stored as a constant and, if
	 *       set, the contributor must also have a post (or N posts) published
	 *       after that date to requalify for the bulk toggle.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes current
	 *                     user. Default false.
	 * @return bool True if user can toggle confirmation checkboxes, else false.
	 */
	public static function can_toggle_confirmation_checkboxes( $user_id = false ) {
		$can = false;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id && self::count_published_photos( $user_id ) >= self::TOGGLE_ALL_THRESHOLD ) {
			$can = true;
		}

		return $can;
	}

	/**
	 * Returns the number of concurrent submissions allowed for a user.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes current
	 *                     user. Default false.
	 * @return int The number of concurrent submissions for a user.
	 */
	public static function get_concurrent_submission_limit( $user_id = false ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return 0;
		}

		return apply_filters( 'wporg_photos_max_concurrent_submissions', self::MAX_PENDING_SUBMISSIONS, $user_id );
	}

	/**
	 * Determines if user has reached concurrent submission limit for pending
	 * uploads (e.g. max number of photos awaiting moderation).
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes current
	 *                     user. Default false.
	 * @return bool True if user has exceeded pending sumission limit, else false.
	 */
	public static function has_reached_concurrent_submission_limit( $user_id = false ) {
		$limit_reached = true;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id ) {
			$max_pending_submissions = self::get_concurrent_submission_limit( $user_id );
			$posts = get_posts( [
				'fields'         => 'ids',
				'posts_per_page' => $max_pending_submissions,
				'author'         => $user_id,
				'post_status'    => 'pending',
				'post_type'      => Registrations::get_post_type(),
			] );

			if ( count( $posts ) < $max_pending_submissions ) {
				$limit_reached = false;
			}
		}

		return $limit_reached;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\User', 'init' ] );
