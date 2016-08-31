<?php

namespace WordPressdotorg\Forums;

class Performance_Optimizations {

	var $term = null;
	var $query = null;

	function __construct() {
		// Gravatar suppression on lists of topics.
		add_filter( 'bbp_after_get_topic_author_link_parse_args', array( $this, 'get_author_link' ) );
		add_filter( 'bbp_after_get_reply_author_link_parse_args', array( $this, 'get_author_link' ) );

		// Query simplification.
		add_filter( 'bbp_after_has_topics_parse_args', array( $this, 'has_topics' ) );
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

			// has_topics() uses this by default.
			if ( isset( $r['meta_key'] ) && '_bbp_last_active_time' == $r['meta_key'] ) {
				unset( $r['meta_key'] );
				unset( $r['meta_type'] );
				$r['orderby'] = 'ID';
			}

			// If this is a forum, limit the number of pages we're dealing with.
			if ( isset( $r['post_parent'] ) && get_post_type( $r['post_parent'] ) === bbp_get_forum_post_type() ) {
				$r['no_found_rows'] = true;
				add_filter( 'bbp_topic_pagination', array( $this, 'forum_pagination' ) );
				$this->query = $r;
			}
		}
		return $r;
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
			$count = $wpdb->query( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'topic' AND post_status = 'publish' LIMIT 1", $this->query['post_parent'] ) );
			if ( $count ) {
				$r['total'] = $count / bbp_get_topics_per_page();
				return $r;
			}
		}

		// If all else fails...
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
}
