<?php

namespace WordPressdotorg\Forums;

class Performance_Optimizations {

	function __construct() {
		add_filter( 'bbp_after_has_topics_parse_args', array( __CLASS__, 'has_topics' ) );
	}

	/**
	 * Optimize queries for has_topics as much as possible to avoid breaking things.
	 */
	public static function has_topics( $r ) {
		/**
		 * Filter view queries so they only look at the last N days of topics.
		 */
		if ( bbp_is_single_view() ) {
			$view = bbp_get_view_id();
			// Exclude plugin and theme views from this restriction.
			// @todo Update date to a reasonable range once we're done importing.
			if ( ! in_array( $view, array( 'plugin', 'theme', 'review' ) ) ) {
				$r['date_query'] = array( 'after' => '19 months ago' );
			}

		/**
		 * Filter forum queries so they are not sorted by the post meta value of
		 * `_bbp_last_active_time`. This query needs additional optimization
		 * to run over large sets of posts.
		 * See also:
		 * - https://bbpress.trac.wordpress.org/ticket/1925
		 */
		} elseif ( bbp_is_single_forum() ) {
			unset( $r['meta_key'] );
			unset( $r['meta_type'] );

			$r['orderby'] = 'post_date';
			add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		}
		return $r;
	}

	/**
	 * If this is a single forum query, don't use SQL_CALC_FOUND_ROWS to find the
	 * total available topics.
	 */
	public static function pre_get_posts( $q ) {
		if (
			isset( $q->query['post_type'] ) && $q->query['post_type'] === bbp_get_topic_post_type()
		) {
			$q->set( 'no_found_rows', true );
			add_filter( 'posts_groupby', '__return_empty_string' );
			add_filter( 'bbp_topic_pagination', array( __CLASS__, 'topic_pagination' ) );
		}
	}

	/**
	 * Instead, use a COUNT(*) query to find total topics in a forum.
	 */
	public static function topic_pagination( $r ) {
		global $wpdb;

		if ( bbp_is_single_forum() ) {
			$per_page = bbp_get_topics_per_page();
			$forum_id = bbp_get_forum_id();
			$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE 1=1 AND post_type = 'topic' AND post_parent = %d AND post_status IN ( 'closed', 'publish' )", $forum_id ) );

			$r['total'] = ceil( (int) $total / (int) $per_page );
			remove_filter( 'bbp_topic_pagination', array( __CLASS__, 'topic_pagination' ) );
		}
		return $r;
	}
}
