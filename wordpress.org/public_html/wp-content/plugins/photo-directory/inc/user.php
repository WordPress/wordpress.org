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
	 * @var int
	 */
	const MAX_PENDING_SUBMISSIONS = 5;

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

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\User', 'init' ] );
