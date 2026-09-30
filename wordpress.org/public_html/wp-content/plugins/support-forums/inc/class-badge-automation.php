<?php

namespace WordPressdotorg\Forums;

use WP_User;
use function WordPressdotorg\Profiles\{
	assign_badge,
	has_badge,
	remove_badge,
};

class Badge_Automation {

	const SUPPORT_CONTRIBUTOR_BADGE = 'support-contributor';
	const SUPPORT_TEAM_BADGE        = 'support-team';

	const CONTRIBUTOR_REPLY_THRESHOLD = 50;

	protected $support_team_badge_sync_users = [];

	public function __construct() {
		if ( ! function_exists( 'WordPressdotorg\Profiles\assign_badge' ) ) {
			return;
		}

		add_action( 'bbp_new_reply', [ $this, 'maybe_assign_support_contributor_badge_for_reply' ], 10, 1 );
		add_action( 'bbp_approved_reply', [ $this, 'maybe_assign_support_contributor_badge_for_reply' ], 10, 1 );
		add_action( 'transition_post_status', [ $this, 'maybe_assign_support_contributor_badge_on_status_change' ], 10, 3 );

		add_filter( 'bbp_set_user_role', [ $this, 'sync_support_team_badge' ], 10, 2 );
		add_action( 'remove_user_from_blog', [ $this, 'schedule_support_team_badge_sync' ], 10, 1 );
	}

	/**
	 * Award Support Contributor when a reply becomes public.
	 */
	public function maybe_assign_support_contributor_badge_on_status_change( $new_status, $old_status, $post ) {
		if (
			'publish' !== $new_status ||
			'publish' === $old_status ||
			! $post ||
			bbp_get_reply_post_type() !== $post->post_type
		) {
			return;
		}

		$this->maybe_assign_support_contributor_badge_for_reply( $post->ID );
	}

	/**
	 * Award Support Contributor once the reply author satisfies the approved criteria.
	 */
	public function maybe_assign_support_contributor_badge_for_reply( $reply_id ) {
		$reply = get_post( $reply_id );

		if (
			! $reply ||
			bbp_get_reply_post_type() !== $reply->post_type ||
			'publish' !== $reply->post_status ||
			empty( $reply->post_author )
		) {
			return;
		}

		$this->maybe_assign_support_contributor_badge( (int) $reply->post_author );
	}

	/**
	 * Keep the Support Team badge aligned with bbPress moderator/keymaster roles.
	 */
	public function sync_support_team_badge( $new_role, $user_id ) {
		$this->schedule_support_team_badge_sync( $user_id );

		return $new_role;
	}

	/**
	 * Defer role checks until the current request finishes changing user roles.
	 */
	public function schedule_support_team_badge_sync( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! $user_id ) {
			return;
		}

		if ( isset( $this->support_team_badge_sync_users[ $user_id ] ) ) {
			return;
		}

		$this->support_team_badge_sync_users[ $user_id ] = true;

		add_action(
			'shutdown',
			function () use ( $user_id ) {
				$this->update_support_team_badge( $user_id );
			}
		);
	}

	/**
	 * Award Support Contributor once the user has at least 50 forum replies.
	 */
	protected function maybe_assign_support_contributor_badge( $user_id ) {
		if (
			! $this->user_meets_contributor_criteria( $user_id ) ||
			has_badge( self::SUPPORT_CONTRIBUTOR_BADGE, $user_id )
		) {
			return;
		}

		assign_badge( self::SUPPORT_CONTRIBUTOR_BADGE, $user_id );
	}

	/**
	 * Add or remove Support Team depending on whether the user still has a team role.
	 */
	protected function update_support_team_badge( $user_id ) {
		$has_badge = has_badge( self::SUPPORT_TEAM_BADGE, $user_id );

		if ( $this->user_has_support_team_role( $user_id ) ) {
			if ( ! $has_badge ) {
				assign_badge( self::SUPPORT_TEAM_BADGE, $user_id );
			}
		} elseif ( $has_badge ) {
			remove_badge( self::SUPPORT_TEAM_BADGE, $user_id );
		}
	}

	/**
	 * Determine if the user satisfies the Support Contributor criteria.
	 */
	protected function user_meets_contributor_criteria( $user_id ) {
		return (int) bbp_get_user_reply_count( $user_id, true ) >= self::CONTRIBUTOR_REPLY_THRESHOLD;
	}

	/**
	 * Check whether the user has a Support Team bbPress role on any site.
	 */
	protected function user_has_support_team_role( $user_id ) {
		$team_roles = [
			bbp_get_moderator_role(),
			bbp_get_keymaster_role(),
		];

		foreach ( get_blogs_of_user( $user_id, true ) as $site ) {
			$user = new WP_User( $user_id, '', $site->userblog_id );

			if ( array_intersect( $team_roles, (array) $user->roles ) ) {
				return true;
			}
		}

		return false;
	}
}
