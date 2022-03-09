<?php
namespace WordPressdotorg\Forums;
/**
 * Some basic Statistics for forum actions.
 * 
 * These are very ad-hoc and added on an as-needed basis.
 * 
 * No user-specific detail is stored, only that a `key: value` action occurred `x` times.
 */
class Stats {

	public function __construct() {
		// Definition: bump_stats_extra( $name, $value, $views = 1 )
		if ( ! function_exists( 'bump_stats_extra' ) ) {
			return;
		}

		// Term subscriptions (add/remove)
		add_action( 'wporg_bbp_add_user_term_subscription',    array( $this, 'term_subsription_changes' ), 10, 2 );
		add_action( 'wporg_bbp_remove_user_term_subscription', array( $this, 'term_subsription_changes' ), 10, 2 );

		// Subscription emails sent
		add_action( 'bbp_pre_notify_subscribers',       array( $this, 'topic_subscribers' ), 10, 3 );
		add_action( 'bbp_pre_notify_forum_subscribers', array( $this, 'forum_subscribers' ), 10, 3 );
	}

	/**
	 * Record stats for how many subscriptions are added/removed.
	 */
	public function term_subsription_changes( $user_id, $term_id ) {
		$action = 'wporg_bbp_add_user_term_subscription' == current_filter() ? 'subscribe' : 'unsubscribe';
		$type   = str_replace( 'topic-', '', get_term( $term_id )->taxonomy );

		bump_stats_extra( 'wporg-support', $action . '-' . $type );

		// Tokenised links are from email unsubscribe links, record these in duplicate.
		if ( isset( $_GET['token'] ) ) {
			$type .= '_email';
			if ( isset( $_POST['List-Unsubscribe'] ) && 'One-Click' === $_POST['List-Unsubscribe'] ) {
				$type .= '_oneclick';
			}

			bump_stats_extra( 'wporg-support', $action . '-' . $type );
		}
		
	}

	/**
	 * Record stats for how many subscription emails (per type) are sent daily.
	 */
	public function topic_subscribers( $reply_id, $topic_id, $user_ids ) {
		$type = 'topic-unknown';

		// Either "notify me of replies" or "subscribed to tag/plugin/theme"
		if ( ! has_filter( 'bbp_topic_subscription_user_ids' ) ) {
			$type = 'topic-subscribed';
		} else {
			// Determine which taxonomy triggered it.
			$class_instance = $this->which_subscription_class_is_filtering( 'bbp_topic_subscription_user_ids' );
			if ( $class_instance ) {
				$type = $class_instance->taxonomy;
			}
		}

		bump_stats_extra( 'email', $type, count( $user_ids ) );
	}

	/**
	 * Record stats for how many subscription emails (per type) are sent daily.
	 */
	public function forum_subscribers( $topic_id, $forum_id, $user_ids ) {
		$type = 'forum-unknown';

		// Subscribed to tag/plugin/theme
		if ( ! has_filter( 'bbp_forum_subscription_user_ids' ) ) {
			$type = 'forum-subscribed'; // Should be none..
		} else {
			// Determine which taxonomy triggered it.
			$class_instance = $this->which_subscription_class_is_filtering( 'bbp_forum_subscription_user_ids' );
			if ( $class_instance ) {
				$type = $class_instance->taxonomy;
			}
		}

		bump_stats_extra( 'email', $type, count( $user_ids ) );
	}

	/**
	 * Determine which instance of WordPressdotorg\Forums\Term_Subscription\Plugin is currently filtering
	 * the forum/topic subscriptions.
	 * 
	 * There's multiple instances of this class with different parameters stored in different places.
	 */
	protected function which_subscription_class_is_filtering( $filter ) {
		$forums = Plugin::get_instance();

		$subscription_instances = [
			$forums->plugin_subscriptions ?? false,
			$forums->theme_subscriptions ?? false,
			$GLOBALS['wporg_bbp_topic_term_subscriptions'] ?? false
		];

		foreach ( $subscription_instances as $instance ) {
			if ( $instance && has_filter( $filter, [ $instance, 'add_term_subscribers' ] ) ) {
				return $instance;
			}
		}

		return false;
	}
}