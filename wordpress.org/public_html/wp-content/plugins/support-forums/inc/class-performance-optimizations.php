<?php

namespace WordPressdotorg\Forums;

class Performance_Optimizations {

	var $term = null;
	var $query = null;
	var $bound_id = array();

	function __construct() {
		// Gravatar suppression on lists of topics.
		add_filter( 'bbp_after_get_topic_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_reply_author_link_parse_args', array( $this, 'get_author_link' ) );

		// Query simplification.
		add_filter( 'bbp_after_has_topics_parse_args', array( $this, 'has_topics' ) );
		add_filter( 'bbp_register_view_no_replies', array( $this, 'no_replies' ) );
	}

	/**
	 * Remove unnecessary Gravatar display on lists of topics.
	 */
	public function get_author_link( $r ) {
		if ( ! bbp_is_single_topic() || bbp_is_topic_edit() ) {
			$r['type'] = 'name';
		}
		return $r;
	}

	/**
	 * Optimize queries for has_topics as much as possible.
	 */
	public function has_topics( $r ) {
		/**
		 * Filter queries so they are not sorted by the post meta value of
		 * `_bbp_last_active_time`. This query needs additional optimization
		 * to run over large sets of posts.
		 * See also:
		 * - https://bbpress.trac.wordpress.org/ticket/1925
		 */
		if ( isset( $r['post_type'] ) && 'topic' == $r['post_type'] ) {
			// Theme and plugin views rely on taxonomy queries.
			if ( isset( $r['tax_query'] ) ) {
				return $r;
			}

			if ( isset( $r['meta_key'] ) ) {
				// has_topics() uses this by default.
				if ( '_bbp_last_active_time' == $r['meta_key'] ) {
					unset( $r['meta_key'] );
					unset( $r['meta_type'] );
					$r['orderby'] = 'ID';
				// Some views use meta key lookups and should only look at known
				// open topics.
				} elseif ( ! empty( $r['meta_key'] ) ) {
					$r['orderby'] = 'ID';
					add_filter( 'posts_where', array( $this, 'posts_in_last_six_months' ) );
				}
			}

			// If this is a forum, limit the number of pages we're dealing with.
			if ( bbp_is_single_forum() && isset( $r['post_parent'] ) && get_post_type( $r['post_parent'] ) === bbp_get_forum_post_type() ) {
				$r['no_found_rows'] = true;
				add_filter( 'bbp_topic_pagination', array( $this, 'forum_pagination' ) );
				$this->query = $r;
			}
		}
		return $r;
	}

	public function no_replies( $r ) {
		$r['post_parent__not_in'] = array( Plugin::THEMES_FORUM_ID, Plugin::PLUGINS_FORUM_ID, Plugin::REVIEWS_FORUM_ID );
		return $r;
	}

	public function posts_in_last_six_months( $w ) {
		global $wpdb;

		$bound_id = $this->get_bound_id( '6 MONTH' );
		$w .= $wpdb->prepare( " AND ( $wpdb->posts.ID >= %d )", $bound_id );
		return $w;
	}

	public function forum_pagination( $r ) {
		global $wpdb;

		// Try the stored topic count.
		$count = get_post_meta( $this->query['post_parent'], '_bbp_topic_count', true );
		if ( ! empty( $count ) ) {
			$r['total'] = $count / bbp_get_topics_per_page();
			return $r;
		}

		// Try SQL.
		if ( ! is_null( $this->query ) ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'topic' AND post_status IN ( 'publish', 'closed' ) LIMIT 1", $this->query['post_parent'] ) );
			if ( $count ) {
				$r['total'] = $count / bbp_get_topics_per_page();
				update_post_meta( $this->query['post_parent'], '_bbp_topic_count', $count );
				update_post_meta( $this->query['post_parent'], '_bbp_total_topic_count', $count );
				return $r;
			}
		}

		// Give a reasonable default to fall back on.
		$r['total'] = 10;
		return $r;
	}

	/**
	 * Get the term for a plugin or theme view from query_var.
	 */
	public function get_term() {
		if ( null !== $this->term ) {
			return $this->term;
		}

		$slug = false;
		if ( ! empty( get_query_var( Plugin::get_instance()->plugins->query_var() ) ) ) {
			$slug = Plugin::get_instance()->plugins->slug();
			$tax  = Plugin::get_instance()->plugins->taxonomy();
		} elseif ( ! empty( get_query_var( Plugin::get_instance()->themes->query_var() ) ) ) {
			$slug = Plugin::get_instance()->themes->slug();
			$tax  = Plugin::get_instance()->themes->taxonomy();
		}
		if ( $slug ) {
			$term = get_term_by( 'slug', $slug, $tax );
		} else {
			return false;
		}
		return $term;
	}

	/**
	 * Get the ID from a topic one year ago so that we can only look at topics
	 * after that ID.
	 */
	public function get_bound_id( $interval ) {
		global $wpdb;

		if ( ! in_array( $interval, array( '1 WEEK', '1 MONTH', '6 MONTH', '1 YEAR' ) ) ) {
			$interval = '1 WEEK';
		}

		if ( array_key_exists( $interval, $this->bound_id ) ) {
			return $this->bound_id[ $interval ];
		}

		// Check cache.
		$cache_key = str_replace( ' ', '-', $interval );
		$cache_group = 'topic-bound-ids';
		$bound_id = wp_cache_get( $cache_key, $cache_group );
		if ( false === $bound_id ) {

			// Use the type_status_date index.
			$bound_id = $wpdb->get_var( "
				SELECT `ID`
				FROM $wpdb->posts
				WHERE post_type = 'topic'
					AND post_status IN ( 'publish', 'closed' )
					AND post_date < DATE_SUB( NOW(), INTERVAL $interval )
				ORDER BY `ID` DESC
				LIMIT 1 " );
			// Set the bound id to 1 if there is not a suitable range.
			if ( ! $bound_id ) {
				$bound_id = 1;
			}
			$this->bound_id[ $interval ] = $bound_id;

			wp_cache_set( $cache_key, $bound_id, $cache_group, 86400 );
		}
		return $bound_id;
	}
}
