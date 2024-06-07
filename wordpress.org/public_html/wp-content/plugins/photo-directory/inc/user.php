<?php
/**
 * User-related functionality.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class User {

	/**
	 * Maximum number of pending/concurrent submissions for infrequent contributors.
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
	 * Maximum number of pending/concurrent submissions for frequent contributors.
	 *
	 * Once this threshold is met, a user will be unable to make another
	 * submission until a current submission is approved or rejected.
	 *
	 * @see `get_concurrent_submission_limit()` for actually retrieving the maximum
	 * pending submissions for a user, since it can vary based on the user and may
	 * be filtered.
	 * @var int
	 */
	const MAX_PENDING_SUBMISSIONS_FREQUENT = 10;

	/**
	 * The number of published photos before a given user is granted additional
	 * privileges.
	 *
	 * Includes, but not necessarily limited to:
	 * - Increased pending submissions limit.
	 * - Ability to toggle all of the confirmation checkboxes when submitting a photo.
	 *
	 * @var int
	 */
	const TOGGLE_ALL_THRESHOLD = 30;

	/**
	 * Initializes class.
	 */
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

			$user_id = $authordata->ID ?? 0;
		}

		if ( ! $user_id ) {
			return 0;
		}

		return count_user_posts( $user_id, Registrations::get_post_type(), true );
	}

	/**
	 * Returns a count of photos published by a user on this calendar day.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes global
	 *                     author. Default false.
	 * @return int
	 */
	public static function count_published_photos_for_today( $user_id = false ) {
		if (  ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID ?? 0;
		}

		if ( ! $user_id ) {
			return 0;
		}

		$today = new \DateTime( 'now', new \DateTimeZone( wp_timezone_string() ) );
		// Set time to beginning of today.
		$today->setTime( 0, 0, 0 );

		$args = [
			'post_type'      => Registrations::get_post_type(),
			'post_status'    => 'publish',
			'author'         => $user_id,
			'date_query'     => [
				[
					'after'     => $today->format( 'Y-m-d H:i:s' ), // After start of today.
					'inclusive' => true,
				],
			],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Returns a count of pending photos for a user.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes global
	 *                     author. Default false.
	 * @return int
	 */
	public static function count_pending_photos( $user_id = false ) {
		return count( self::get_pending_photos( $user_id ) );
	}

	/**
	 * Returns the pending photos for a user.
	 *
	 * @param int    $user_id Optional. The user ID. If not defined, assumes global
	 *                        author. Default false.
	 * @param string $fields  Optional. The fields to return from the pending photos.
	 *                        Default 'ids'.
	 * @return array
	 */
	public static function get_pending_photos( $user_id = false, $fields = 'ids' ) {
		if (  ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID ?? 0;
		}

		if ( ! $user_id ) {
			return [];
		}

		return get_posts( [
			'fields'         => $fields,
			'posts_per_page' => -1,
			'author'         => $user_id,
			'post_status'    => Photo::get_pending_post_statuses(),
			'post_type'      => Registrations::get_post_type(),
		] );
	}

	/**
	 * Returns the most recent photos for a given user.
	 *
	 * @param int  $user_id The user ID.
	 * @param int  $number The number of photos to return.
	 * @param bool $include_pending Include pending photos?
	 * @return WP_Post[]
	 */
	public static function get_recent_photos( $user_id, $number, $include_pending = false ) {
		$post_statuses = $include_pending ? Photo::get_pending_post_statuses() : [];
		$post_statuses[] = 'publish';

		return get_posts( [
			'posts_per_page' => $number,
			'author'         => $user_id,
			'post_status'    => $post_statuses,
			'post_type'      => Registrations::get_post_type(),
		] );
	}

	/**
	 * Returns a count of rejected photos for a user.
	 *
	 * @param int $user_id                    Optional. The user ID. If not defined,
	 *                                        assumes global author. Default false.
	 * @param bool $exclude_submission_errors Optional. Should photos rejected due
	 *                                        to the 'submission-error' reason be
	 *                                        excluded from the count? Default true.
	 * @return int
	 */
	public static function count_rejected_photos( $user_id = false, $exclude_submission_errors = true ) {
		global $wpdb;

		if ( ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID;
		}

		if ( ! $user_id ) {
			return 0;
		}

		$args = [
			'post_type'      => Registrations::get_post_type(),
			'post_status'    => Rejection::get_post_status(),
			'author'         => $user_id,
			'fields'         => 'ids',
			'posts_per_page' => -1,
		];

		if ( $exclude_submission_errors ) {
			$args['meta_query'] = [
				[
					'key'      => 'rejected_reason',
					'value'    => 'submission-error',
					'compare'  => '!=',
				],
			];
		}

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Returns a count of flagged photos for a user.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes global
	 *                     author. Default false.
	 * @return int
	 */
	public static function count_flagged_photos( $user_id = false ) {
		global $wpdb;

		if (  ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID;
		}

		if ( ! $user_id ) {
			return 0;
		}

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s AND post_author = %d",
			Registrations::get_post_type(),
			Flagged::get_post_status(),
			$user_id
		) );
	}

	/**
	 * Returns the 'meta_query' value for use in finding posts moderated (and also
	 * optionally rejected) by a user.
	 *
	 * @param int $user_id            The user ID.
	 * @param int $include_rejections Optional. Should the count of photos
	 *                                rejected by the user be included in the
	 *                                count? Default false.
	 * @return array
	 */
	public static function get_moderator_meta_query( $user_id, $include_rejections = false ) {
		if ( ! $user_id ) {
			return [];
		}

		$moderator_query = [
			'key'   => Registrations::get_meta_key( 'moderator' ),
			'value' => $user_id,
		];
		$rejector_query = [
			'key'   => 'rejected_by',
			'value' => $user_id,
		];

		if ( $include_rejections ) {
			$meta_query = [
				'relation' => 'OR',
				$moderator_query,
				$rejector_query,
			];
		} else {
			$meta_query = [ $moderator_query ];
		}

		return $meta_query;
	}

	/**
	 * Returns the number of photos moderated by the user.
	 *
	 * By default, this does NOT include photo rejections unless the optional
	 * argument is enabled.
	 *
	 * @param int $user_id            Optional. The user ID. If not defined,
	 *                                assumes global author. Default false.
	 * @param int $include_rejections Optional. Should the count of photos
	 *                                rejected by the user be included in the
	 *                                count? Default false.
	 * @return int
	 */
	public static function count_photos_moderated( $user_id = false, $include_rejections = false ) {
		if ( ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID;
		}

		if ( ! $user_id ) {
			return 0;
		}

		$post_statuses = ['publish'];
		if ( $include_rejections ) {
			$post_statuses[] = Rejection::get_post_status();
		}

		$args = [
			'post_type'      => Registrations::get_post_type(),
			'post_status'    => $post_statuses,
			'meta_query'     => self::get_moderator_meta_query( $user_id, $include_rejections ),
			'fields'         => 'ids',
			'posts_per_page' => -1,
		];

		$query = new \WP_Query( $args );

		return $query->post_count;
	}

	/**
	 * Returns the number of photos rejected by the user as a moderator.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes global
	 *                     author. Default false.
	 * @return int
	 */
	public static function count_photos_rejected_as_moderator( $user_id = false ) {
		if ( ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID;
		}

		if ( ! $user_id ) {
			return 0;
		}

		$args = [
			'post_type'      => Registrations::get_post_type(),
			'post_status'    => Rejection::get_post_status(),
			'meta_query'     => [
				[
					'key'   => 'rejected_by',
					'value' => $user_id,
				],
			],
			'fields'         => 'ids',
			'posts_per_page' => -1,
		];

		$query = new \WP_Query( $args );

		return $query->post_count;
	}

	/**
	 * Determines if a user is considered a frequent contributor.
	 *
	 * @param int $user_id Optional. The user ID. If not defined, assumes current
	 *                     user. Default false.
	 * @return bool True if user is considered a frequent contributor, else false.
	 */
	public static function is_frequent_contributor( $user_id = false ) {
		$is_frequent = false;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id && self::count_published_photos( $user_id ) >= self::TOGGLE_ALL_THRESHOLD ) {
			$is_frequent = true;
		}

		return $is_frequent;
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
		return self::is_frequent_contributor( $user_id );
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

		$limit = self::is_frequent_contributor( $user_id )
			? self::MAX_PENDING_SUBMISSIONS_FREQUENT
			: self::MAX_PENDING_SUBMISSIONS;

		return apply_filters( 'wporg_photos_max_concurrent_submissions', $limit, $user_id );
	}

	/**
	 * Returns the file names for all of a user's pending submissions.
	 *
	 * @param int $user_id The user ID. If false, then assumes current user. Default false.
	 * @return array
	 */
	public static function get_pending_file_names( $user_id = false ) {
		global $wpdb;

		$names = [];

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id && $post_ids = self::get_pending_photos( $user_id ) ) {
			$post_ids = implode( ',', array_map( 'intval', array_values( $post_ids ) ) );
			$names = $wpdb->get_col( $wpdb->prepare(
				"SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s AND post_id IN ($post_ids)",
				Registrations::get_meta_key( 'original_filename' )
			) );
		}

		return $names;
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
			$pending_photos_count = self::count_pending_photos( $user_id );

			if ( $pending_photos_count < $max_pending_submissions ) {
				$limit_reached = false;
			}
		}

		return $limit_reached;
	}

	/**
	 * Returns the photo post most recently moderated by the user.
	 *
	 * @param int $user_id            Optional. The user ID. If not defined,
	 *                                assumes global author. Default false.
	 * @param int $include_rejections Optional. Should photos rejected by the
	 *                                user be considered? Default false.
	 * @return WP_Post|false The post, or false if no posts found.
	 */
	public static function get_last_moderated( $user_id = false, $include_rejections = false ) {
		if ( ! $user_id ) {
			global $authordata;

			$user_id = $authordata->ID;
		}

		if ( ! $user_id ) {
			return false;
		}

		$post_statuses = ['publish'];
		if ( $include_rejections ) {
			$post_statuses[] = Rejection::get_post_status();
		}

		$args = [
			'post_type'      => Registrations::get_post_type(),
			'post_status'    => $post_statuses,
			'meta_query'     => self::get_moderator_meta_query( $user_id, $include_rejections ),
			'posts_per_page' => 1,
		];

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return false;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\User', 'init' ] );
