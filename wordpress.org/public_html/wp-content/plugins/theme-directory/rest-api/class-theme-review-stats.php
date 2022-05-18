<?php

namespace WordPressdotorg\Theme_Directory\Rest_API;

use WP_Error;
use WP_REST_Controller, WP_REST_Server, WP_REST_Response;

defined( 'WPINC' ) || die();

/**
 *
 * @see WP_REST_Controller
 */
class Theme_Review_Stats extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'themes/v1';
		$this->rest_base = 'stats';

		$this->register_routes();
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_uploaded_themes' ),
				'permission_callback' => array( $this, 'get_items_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/byThemeType',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_by_theme_type' ),
				'permission_callback' => array( $this, 'get_items_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bySegment',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_by_segment' ),
				'permission_callback' => array( $this, 'get_items_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/byAuthorType',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_by_author_type' ),
				'permission_callback' => array( $this, 'get_items_permissions' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reviewDays',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_review_days' ),
				'permission_callback' => array( $this, 'get_items_permissions' ),
			)
		);
	}

	/**
	 * Get start date param from request.
	 *
	 * @returns string
	 */
	function get_start_date( $request ) {
		$start_date = $request->get_param( 'startDate' );

		if ( empty( $start_date ) ) {
			$start_date = date( 'Y-m-d', strtotime( '-3 months' ) ); // Default to 3 months ago
		}

		return $start_date;
	}

	/**
	 * Return whether the user can retrieve the data.
	 *
	 * @return bool
	 */
	function get_items_permissions( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Returns an array of elements after the start date.
	 */
	function filter_items_before( $arr, $start_date ) {
		return array_filter(
			$arr,
			function ( $row ) use ( $start_date ) {
				return strtotime( $row[0] ) >= strtotime( $start_date );
			}
		);
	}

	/**
	 * Returns a list averages review times by month.
	 *
	 * @return void
	 */
	public function get_average_review_days( $start_date ) {
		$cached = wp_cache_get( __METHOD__, 'API:Theme-Stats' );
		if ( $cached ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"	
				SELECT LEFT( upload_date, 7 ) AS ym, AVG(days_to_review) AS average_days_to_review

				FROM (
					SELECT p.post_name,
					pm.meta_value,
					substring_index( substring_index( pm.meta_value, '\"', 4 ), '\"', -1 ) AS upload_date,
					p.post_date_gmt AS published_date,
					DATEDIFF( p.post_date_gmt, substring_index( substring_index( pm.meta_value, '\"', 4 ), '\"', -1 ) ) as days_to_review
			
					FROM {$wpdb->posts} p
					JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_upload_date'
			
					WHERE p.post_type = 'repopackage' and p.post_status = 'publish'
			
					ORDER BY p.ID desc
				)a
				
				WHERE published_date > upload_date AND days_to_review IS NOT NULL AND upload_date >= %s

				GROUP BY ym
				ORDER BY upload_date ASC
					",
				$start_date
			)
		);

		// Expire the cache in 1 hour
		wp_cache_set( __METHOD__, $results, 'API:Theme-Stats', 3600 );

		return $results;
	}

	/**
	 * Returns a list of published themes.
	 *
	 * @return void
	 */
	public function get_uploaded_themes() {
		$cached = wp_cache_get( __METHOD__, 'API:Theme-Stats' );
		if ( $cached ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			"	
			SELECT p.post_name AS postName,
				p.post_author AS post_author,
				substring_index( substring_index( pm.meta_value, '\"', 4 ), '\"', -1 ) AS author,
				LEFT(p.post_date, 7) AS published_on,
				group_concat( t.slug SEPARATOR ', ' ) AS tags

			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_author'
			LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'post_tag'
			LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id

			WHERE p.post_type = 'repopackage' AND p.post_status = 'publish'

			GROUP BY p.ID
			ORDER BY published_on ASC
			"
		);

		// Expire the cache in 1 hour
		wp_cache_set( __METHOD__, $results, 'API:Theme-Stats', 3600 );

		return $results;
	}

	/**
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_by_theme_type( $request ) {
		$start_date = $this->get_start_date( $request );
		$rows       = $this->get_uploaded_themes();
		$map        = array();

		foreach ( $rows as $value ) {
			$ym = $value->published_on;

			if ( ! isset( $map[ $ym ] ) ) {
				$map[ $ym ]['full-site-editing'] = 0;
				$map[ $ym ]['classic']           = 0;
			}

			if ( str_contains( $value->tags, 'full-site-editing' ) ) {
				$map[ $ym ]['full-site-editing']++;
			} else {
				$map[ $ym ]['classic']++;
			}
		}

		$out = array();
		foreach ( $map as $year_month => $value ) {
			array_push( $out, array( $year_month, $value['full-site-editing'], $value['classic'] ) );
		}

		$out = $this->filter_items_before( $out, $start_date );

		return rest_ensure_response( array_values( $out ) );
	}

	/**
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_by_segment( $request ) {
		$start_date = $this->get_start_date( $request );
		$rows       = $this->get_uploaded_themes();
		$author_map = array(); // Temporarily store themes per author
		$map        = array();

		foreach ( $rows as $value ) {
			$ym = $value->published_on;

			if ( ! isset( $map[ $ym ] ) ) {
				$map[ $ym ]['segment1'] = 0; // Counter for authors with 1 theme
				$map[ $ym ]['segment2'] = 0; // Counter for authors with 2 - 4 themes
				$map[ $ym ]['segment3'] = 0; // Counter for authors with 5+ themes
			}

			if ( isset( $author_map[ $value->post_author ] ) ) {
				if ( $author_map[ $value->post_author ] >= 5 ) {
					$map[ $ym ]['segment3']++;
				} else {
					$map[ $ym ]['segment2']++;
				}
				$author_map[ $value->post_author ]++;
			} else {
				$author_map[ $value->post_author ] = 1;
				$map[ $ym ]['segment1']++;
			}
		}

		$out = array();
		foreach ( $map as $year_month => $value ) {
			array_push( $out, array( $year_month, $value['segment1'], $value['segment2'], $value['segment3'] ) );
		}

		$out = $this->filter_items_before( $out, $start_date );

		return new WP_REST_Response( array_values( $out ), \WP_Http::OK );
	}

	/**
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_by_author_type( $request ) {
		$start_date = $this->get_start_date( $request );
		$rows       = $this->get_uploaded_themes();
		$author_map = array(); // Temporarily store themes per author
		$map        = array();

		foreach ( $rows as $value ) {
			$ym = $value->published_on;

			if ( ! isset( $map[ $ym ] ) ) {
				$map[ $ym ]['first-theme'] = 0; // Counter to represent whether author is uploading first theme
				$map[ $ym ]['many-themes'] = 0; // Counter to represent whether author has already uploaded a different theme
			}

			if ( isset( $author_map[ $value->post_author ] ) ) {
				$map[ $ym ]['many-themes']++;
			} else {
				$author_map[ $value->post_author ] = 1;
				$map[ $ym ]['first-theme']++;
			}
		}

		$out = array();
		foreach ( $map as $year_month => $value ) {
			$total = $value['first-theme'] + $value['many-themes'];
			array_push( $out, array( $year_month, $value['first-theme'], $value['many-themes'], $total ) );
		}

		$out = $this->filter_items_before( $out, $start_date );

		return new WP_REST_Response( array_values( $out ), \WP_Http::OK );
	}

	/**
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_items( $request ) {
		$start_date = $this->get_start_date( $request );
		$rows       = $this->get_uploaded_themes();

		return new WP_REST_Response( $rows, \WP_Http::OK );
	}

	/**
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_review_days( $request ) {
		$start_date = $this->get_start_date( $request );
		$rows       = $this->get_average_review_days( $start_date );
		$out        = array();

		foreach ( $rows as $value ) {
			$ym          = $value->ym;
			$review_days = round( floatval( $value->average_days_to_review ), 1 );
			array_push( $out, array( $ym, $review_days ) );
		}

		return new WP_REST_Response( $out, \WP_Http::OK );
	}
}

new Theme_Review_Stats();
