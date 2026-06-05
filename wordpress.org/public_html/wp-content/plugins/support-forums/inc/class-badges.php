<?php
/**
 * Support forum badge automation.
 *
 * @package wporg-forums
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Forums;

use function WordPressdotorg\Profiles\assign_badge;
use function WordPressdotorg\Profiles\has_badge;

/**
 * Awards Support badges based on forum role and reply activity.
 */
class Badges {

	const CONTRIBUTOR_REPLY_THRESHOLD = 50;

	/**
	 * Register badge assignment hooks.
	 */
	public function __construct() {
		add_filter( 'bbp_set_user_role', array( $this, 'maybe_award_team_badge_for_bbp_role' ), 20, 2 );
		add_action( 'add_user_role', array( $this, 'maybe_award_team_badge_for_role' ), 20, 2 );
		add_action( 'set_user_role', array( $this, 'maybe_award_team_badge_for_role' ), 20, 2 );

		add_action( 'bbp_new_reply', array( $this, 'maybe_award_contributor_badge_for_reply' ), 30 );
		add_action( 'bbp_approved_reply', array( $this, 'maybe_award_contributor_badge_for_reply' ), 30 );
		add_action( 'bbp_unspammed_reply', array( $this, 'maybe_award_contributor_badge_for_reply' ), 30 );
		add_action( 'bbp_untrashed_reply', array( $this, 'maybe_award_contributor_badge_for_reply' ), 30 );
		add_action( 'wporg_bbp_unarchived_reply', array( $this, 'maybe_award_contributor_badge_for_reply' ), 30 );
	}

	/**
	 * Award the Support Team badge when bbPress assigns the moderator role.
	 *
	 * @param string $new_role New forum role.
	 * @param int    $user_id  User ID.
	 * @return string Unmodified role for the filter.
	 */
	public function maybe_award_team_badge_for_bbp_role( $new_role, $user_id ) {
		$this->maybe_award_team_badge_for_role( $user_id, $new_role );

		return $new_role;
	}

	/**
	 * Award the Support Team badge when the moderator role is assigned.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role    Assigned role.
	 */
	public function maybe_award_team_badge_for_role( $user_id, $role ) {
		if ( bbp_get_moderator_role() !== $role ) {
			return;
		}

		$this->award_badge( 'support-team', $user_id );
	}

	/**
	 * Award the Support Contributor badge if a reply pushes the author to the threshold.
	 *
	 * @param int $reply_id Reply ID.
	 */
	public function maybe_award_contributor_badge_for_reply( $reply_id ) {
		if ( ! bbp_is_reply_published( $reply_id ) ) {
			return;
		}

		$this->maybe_award_contributor_badge( bbp_get_reply_author_id( $reply_id ) );
	}

	/**
	 * Award the Support Contributor badge to users at or above the reply threshold.
	 *
	 * @param int $user_id User ID.
	 */
	public function maybe_award_contributor_badge( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return;
		}

		if (
			function_exists( 'WordPressdotorg\Profiles\has_badge' ) &&
			has_badge( 'support-contributor', $user_id )
		) {
			return;
		}

		if ( $this->count_user_replies( $user_id ) < self::CONTRIBUTOR_REPLY_THRESHOLD ) {
			return;
		}

		$this->award_badge( 'support-contributor', $user_id );
	}

	/**
	 * Count a user's published forum replies.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Limit the count to the threshold needed for comparison.
	 * @return int
	 */
	public function count_user_replies( $user_id, $limit = self::CONTRIBUTOR_REPLY_THRESHOLD ) {
		global $wpdb;

		$user_id       = absint( $user_id );
		$limit         = max( 1, absint( $limit ) );
		$post_type     = $this->get_reply_post_type();
		$post_statuses = $this->get_counted_reply_statuses();
		$placeholders  = implode( ', ', array_fill( 0, count( $post_statuses ), '%s' ) );

		if ( ! $user_id || ! $post_statuses ) {
			return 0;
		}

		$prepared_values = array_merge( array( $user_id, $post_type ), $post_statuses, array( $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is generated from sanitized status values.
		$query = $wpdb->prepare(
			"SELECT COUNT(1)
				FROM (
					SELECT ID
					FROM {$wpdb->posts}
					WHERE post_author = %d
						AND post_type = %s
						AND post_status IN ( {$placeholders} )
					LIMIT %d
				) AS user_replies",
			...$prepared_values
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Runs on reply submission for a single author with a threshold-limited query.
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get the bbPress reply post type.
	 *
	 * @return string
	 */
	private function get_reply_post_type() {
		return function_exists( 'bbp_get_reply_post_type' ) ? bbp_get_reply_post_type() : 'reply';
	}

	/**
	 * Get reply statuses that count toward the Support Contributor badge.
	 *
	 * @return string[]
	 */
	private function get_counted_reply_statuses() {
		$post_statuses = function_exists( 'bbp_get_public_status_id' )
			? array( bbp_get_public_status_id() )
			: array( 'publish' );

		/**
		 * Filters the reply statuses that count toward Support Contributor badge eligibility.
		 *
		 * @param string[] $post_statuses Reply statuses to count.
		 */
		$post_statuses = (array) apply_filters( 'wporg_support_badges_counted_reply_statuses', $post_statuses );
		$post_statuses = array_map( 'sanitize_key', $post_statuses );
		$post_statuses = array_filter( $post_statuses );

		return array_values( array_unique( $post_statuses ) );
	}

	/**
	 * Assign a badge through the Profiles helper, once per request.
	 *
	 * @param string $badge   Badge slug.
	 * @param int    $user_id User ID.
	 * @return bool
	 */
	private function award_badge( $badge, $user_id ) {
		$user_id = absint( $user_id );

		if (
			! $user_id ||
			! function_exists( 'WordPressdotorg\Profiles\assign_badge' )
		) {
			return false;
		}

		return assign_badge( $badge, $user_id );
	}
}
