<?php
/**
 * Search query customization.
 *
 * @package wporg-developer
 */

/**
 * Class to handle search query customizations.
 */
class DevHub_Search {

	/**
	 * Initializer
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Handles adding/removing hooks.
	 */
	public static function do_init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 20 );
		add_filter( 'posts_orderby', array( __CLASS__, 'search_posts_orderby' ), 10, 2 );
		add_filter( 'the_posts',     array( __CLASS__, 'rerun_empty_exact_search' ), 10, 2 );
	}

	/**
	 * Query modifications.
	 *
	 * @param \WP_Query $query
	 */
	public static function pre_get_posts( $query ) {
		// Don't modify anything if not a non-admin main search query.
		if ( ! ( ! is_admin() && $query->is_main_query() && $query->is_search() ) ) {
			return;
		}

		// Order search result in ascending order by title.
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );

		// Separates searches for handbook pages from non-handbook pages depending on
		// whether the search was performed within context of a handbook page or not.
		if ( wporg_is_handbook() ) {
			// Search only in current handbook post type.
			// Just to make sure. post type should already be set.
			$query->set( 'post_type', wporg_get_current_handbook() );
		} else {
			// If user has '()' at end of a search string, assume they want a specific function/method.
			$s = htmlentities( $query->get( 's' ) );
			if ( '()' === substr( $s, -2 ) ) {
				// Enable exact search.
				$query->set( 'exact',     true );
				// Modify the search query to omit the parentheses.
				$query->set( 's',         substr( $s, 0, -2 ) ); // remove '()'
				// Restrict search to function-like content.
				$query->set( 'post_type', array( 'wp-parser-function', 'wp-parser-method' ) );
			} else {
				// Search parsed post types instead of all post types.
				$query->set( 'post_type', DevHub\get_parsed_post_types() );
			}
		}
	}

	/**
	 * Filter the SQL for the ORDER BY clause for search queries.
	 *
	 * Adds ORDER BY condition with spaces replaced with underscores in 'post_title'.
	 * Adds ORDER BY condition to order by title length.
	 *
	 * @param string   $orderby The ORDER BY clause of the query.
	 * @param WP_Query $query   The WP_Query instance (passed by reference).
	 * @return string  Filtered order by clause
	 */
	public static function search_posts_orderby( $orderby, $query ) {
		global $wpdb;

		if ( $query->is_main_query() && is_search() && ! $query->get( 'exact' ) ) {

			$search_order_by_title = $query->get( 'search_orderby_title' );

			// Check if search_orderby_title is set by WP_Query::parse_search.
			if ( is_array( $search_order_by_title ) && $search_order_by_title ) {

				// Get search orderby query.
				$orderby = self::parse_search_order( $query->query_vars );

				// Add order by title length.
				$orderby .= " , CHAR_LENGTH( $wpdb->posts.post_title ) ASC, $wpdb->posts.post_title ASC";
			}
		}

		return $orderby;
	}

	/**
	 * Generate SQL for the ORDER BY condition based on passed search terms.
	 *
	 * Similar to WP_Query::parse_search_order.
	 * Adds ORDER BY condition with spaces replaced with underscores in 'post_title'.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array   $q Query variables.
	 * @return string ORDER BY clause.
	 */
	public static function parse_search_order( $q ) {
		global $wpdb;

		if ( $q['search_terms_count'] > 1 ) {
			$num_terms = count( $q['search_orderby_title'] );

			// If the search terms contain negative queries, don't bother ordering by sentence matches.
			$like = $_like = '';
			if ( ! preg_match( '/(?:\s|^)\-/', $q['s'] ) ) {
				$like = '%' . $wpdb->esc_like( $q['s'] ) . '%';
			}

			$search_orderby = '';

			// Sentence match in 'post_title'.
			if ( $like ) {
				$search_orderby .= $wpdb->prepare( "WHEN $wpdb->posts.post_title LIKE %s THEN 1 ", $like );
				$_like =  str_replace( '-', '_', sanitize_title_with_dashes( $q['s'] ) );
				$_like = '%' . $wpdb->esc_like( $_like ) . '%';
				if ( $_like !== $like ) {
					// Sentence match in 'post_title' with spaces replaced with underscores.
					$search_orderby .= $wpdb->prepare( "WHEN $wpdb->posts.post_title LIKE %s THEN 2 ", $_like );
				}
			}

			// Sanity limit, sort as sentence when more than 6 terms.
			// (few searches are longer than 6 terms and most titles are not)
			if ( $num_terms < 7 ) {
				// all words in title
				$search_orderby .= 'WHEN ' . implode( ' AND ', $q['search_orderby_title'] ) . ' THEN 3 ';
				// any word in title, not needed when $num_terms == 1
				if ( $num_terms > 1 )
					$search_orderby .= 'WHEN ' . implode( ' OR ', $q['search_orderby_title'] ) . ' THEN 4 ';
			}

			// Sentence match in 'post_content'.
			if ( $like ) {
				$search_orderby .= $wpdb->prepare( "WHEN $wpdb->posts.post_content LIKE %s THEN 5 ", $like );
			}

			if ( $search_orderby ) {
				$search_orderby = '(CASE ' . $search_orderby . 'ELSE 6 END)';
			}
		} else {
			// Single word or sentence search.
			$search_orderby = reset( $q['search_orderby_title'] ) . ' DESC';
		}

		return $search_orderby;
	}

	/**
	 * Rerun an exact search with the same criteria except exactness if no posts
	 * were found.
	 *
	 * @access public
	 *
	 * @param  array    $posts Array of posts after the main query
	 * @param  WP_Query $query WP_Query object
	 * @return array
	 */
	public static function rerun_empty_exact_search( $posts, $query ) {
		if ( is_search() && true === $query->get( 'exact' ) && ! $query->found_posts ) {
			$query->set( 'exact', false );
			$posts = $query->get_posts();
		}

		return $posts;
	}

} // DevHub_Search

DevHub_Search::init();
