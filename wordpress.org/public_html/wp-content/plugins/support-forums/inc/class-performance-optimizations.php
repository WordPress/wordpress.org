<?php

namespace WordPressdotorg\Forums;

class Performance_Optimizations {

	function __construct() {
		// Gravatar suppression on lists of topics.
		add_filter( 'bbp_after_get_topic_author_link_parse_args', array( __CLASS__, 'get_author_link' ) );
		add_filter( 'bbp_after_get_reply_author_link_parse_args', array( __CLASS__, 'get_author_link' ) );

		// Query simplification.
		add_filter( 'bbp_after_has_topics_parse_args', array( __CLASS__, 'has_topics' ) );
	}

	/**
	 * Remove unnecessary Gravatar display on lists of topics.
	 */
	public static function get_author_link( $r ) {
		if ( ! bbp_is_single_topic() || bbp_is_topic_edit() ) {
			$r['type'] = 'name';
		}
		return $r;
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
			} else {
				$term = self::get_term();

				// If there are a lot of results for a single plugin or theme,
				// order by post_date to avoid an INNER JOIN ON.
				if ( $term && ! is_wp_error( $term ) && property_exists( $term, 'count' ) ) {
					if ( $term->count > 10000 ) {
						unset( $r['meta_key'] );
						unset( $r['meta_type'] );

						$r['orderby'] = 'post_date';
					}
				}
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

			// This only works because we don't edit dates on forum topics.
			$r['orderby'] = 'post_date';
		}
		return $r;
	}

	/**
	 * Get the term for a plugin or theme view from query_var.
	 */
	public static function get_term() {
		if ( ! empty( get_query_var( Plugin::get_instance()->plugins->query_var() ) ) ) {
			$slug = Plugin::get_instance()->plugins->slug();
			$tax  = Plugin::get_instance()->plugins->taxonomy();
		} elseif ( ! empty( get_query_var( Plugin::get_instance()->themes->query_var() ) ) ) {
			$slug = Plugin::get_instance()->themes->slug();
			$tax  = Plugin::get_instance()->themes->taxonomy();
		}
		$term = get_term( $slug, $tax );
		return $term;
	}
}
