<?php
/**
 * Search functionality.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Search {

	/**
	 * Initializes component.
	 */
	public static function init() {
		add_action( 'pre_get_posts',        [ __CLASS__, 'default_search_to_only_photos' ] );
		add_filter( 'posts_join',           [ __CLASS__, 'tag_join_for_search' ], 10, 2 );
		add_filter( 'posts_search',         [ __CLASS__, 'tag_where_for_search' ], 10, 2 );
		add_filter( 'posts_groupby',        [ __CLASS__, 'tag_groupby_for_search' ], 10, 2 );
		add_filter( 'posts_search_orderby', [ __CLASS__, 'tag_orderby_for_search' ], 10, 2 );
	}

	/**
	 * Determines if the request is for a frontend search.
	 *
	 * @param WP_Query $query The WP_Query object.
	 * @return bool True if request is for a frontend search, else false.
	 */
	public static function is_search( $query ) {
		return ! is_admin() && $query->is_search() && $query->is_main_query();
	}

	/**
	 * Changes default search to search only photos.
	 *
	 * @param WP_Query $query The WP_Query object.
	 */
	public static function default_search_to_only_photos( $query ) {
		if ( self::is_search( $query ) ) {
			$query->set( 'post_type', Registrations::get_post_type() );
			$query->set( 'post_status', [ 'publish' ] );
		}
	}

	/**
	 * Customizes the JOIN clause for frontend searches.
	 *
	 * Searches should also check for tags, so join those tables.
	 *
	 *
	 * @param string   $join  The JOIN clause of the query.
	 * @param WP_Query $query The WP_Query object.
	 * @return string
	 */
	public static function tag_join_for_search( $join, $query ) {
		global $wpdb;

		if ( self::is_search( $query ) ) {
			$join .= "
				LEFT JOIN
				  {$wpdb->term_relationships} ON {$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id
				LEFT JOIN
				  {$wpdb->term_taxonomy} ON {$wpdb->term_taxonomy}.term_taxonomy_id = {$wpdb->term_relationships}.term_taxonomy_id
				LEFT JOIN
				  {$wpdb->terms} ON {$wpdb->terms}.term_id = {$wpdb->term_taxonomy}.term_id
			  ";
		}

		return $join;
	}

	/**
	 * Customizes the WHERE clause for frontend searches.
	 *
	 * Searches should also check for tags, so also search there.
	 *
	 * @param string   $where Search SQL for WHERE clause.
	 * @param WP_Query $query The WP_Query object.
	 * @return string
	 */
	public static function tag_where_for_search( $where, $query ) {
		global $wpdb;

		if ( self::is_search( $query ) ) {
			$insert_at = strpos( $where, 'OR (' . $wpdb->prefix . 'posts.post_content LIKE ' );
			if ( $insert_at ) {
				$or_tag = $wpdb->prepare( "OR
					(
						{$wpdb->term_taxonomy}.taxonomy = %s
						AND
						{$wpdb->terms}.name = %s
					) ",
					Registrations::get_taxonomy( 'tags' ),
					get_query_var('s')
				);
				$where = substr_replace( $where, $or_tag, $insert_at, 0 );
			}
		}

		return $where;
	}

	/**
	 * Customizes the GROUP BY clause for frontend searches.
	 *
	 * Groups posts by ID to avoid duplicates due to the join.
	 *
	 * @param string   $groupby The GROUP BY clause of the query.
	 * @param WP_Query $query   The WP_Query object.
	 * @return string
	 */
	public static function tag_groupby_for_search( $groupby, $query ) {
		global $wpdb;

		if ( self::is_search( $query ) ) {
			$groupby = "{$wpdb->posts}.ID";
		}

		return $groupby;
	}

	/**
	 * Customizes ORDER BY clause for frontend searches to prevent ordering by
	 * post title.
	 *
	 * Unsets the search orderby since results should not be sorted by the
	 * default of post_title since post titles are meaningless. Results will
	 * fall back to being sorted DESC by post_date.
	 *
	 * @param string   $orderby The ORDER BY clause.
	 * @param WP_Query $query   The WP_Query object.
	 * @return string
	 */
	public static function tag_orderby_for_search( $orderby, $query ) {
		if ( self::is_search( $query ) ) {
			$orderby = "";
		}

		return $orderby;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Search', 'init' ] );
