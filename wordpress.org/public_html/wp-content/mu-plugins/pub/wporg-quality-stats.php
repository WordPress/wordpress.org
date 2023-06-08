<?php

namespace WordPressdotorg\MU_Plugins\REST_API\Site_Quality_Stats;

use WP_Error;
use WP_REST_Controller, WP_REST_Server, WP_REST_Response;

defined( 'WPINC' ) || die();

/**
 *
 * @see WP_REST_Controller
 */
class Site_Quality_Stats_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'site-quality/v1';
		$this->rest_base = 'github';

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);
	}

	/**
	 * A Permission Check callback which validates the request against a WP_ORG token.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function update_item_permissions_check( $request ) {
		return $this->permission_check_api_bearer( $request, 'AUTO_REVIEW_TRAC_API_GITHUB_BEARER_TOKEN' );
	}

	/**
	 * A Permission Check callback which validates the a request against a given token.
	 *
	 * @param \WP_REST_Request $request  The Rest API Request.
	 * @param string           $constant The constant that contains the expected bearer.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function permission_check_api_bearer( $request, $constant = false ) {
		$authorization_header = $request->get_header( 'authorization' );
		$authorization_header = trim( str_ireplace( 'bearer', '', $authorization_header ) );

		if (
			! $authorization_header ||
			! $constant ||
			! defined( $constant ) ||
			! hash_equals( constant( $constant ), $authorization_header )
		) {
			return new \WP_Error(
				'not_authorized',
				__( 'Sorry! You cannot do that.', 'wporg' ),
				array( 'status' => \WP_Http::UNAUTHORIZED )
			);
		}

		return true;
	}

	/**
	 * Save a stat to the database.
	 *
	 * @return bool True if the stat was saved, false otherwise.
	 */
	public function save_stat( $url, $category, $value ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'site_stats';

		$data = array(
			'url'      => $url,
			'category' => $category,
			'value'    => $value,
			'created'   => current_time( 'mysql' ),
		);

		return $wpdb->insert( $table_name, $data );
	}

	/**
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function update_item( $request ) {
		$body = $request->get_body();

		if ( empty( $body ) ) {
			return new \WP_Error(
				'rest_error_site_quality',
				__( 'Content was empty.', 'wporg' ),
				array( 'status' => \WP_Http::INTERNAL_SERVER_ERROR )
			);
		}

		$results = json_decode( urldecode( $body ), true );

		foreach ( $results as $item ) {
			foreach ( $item['summary'] as $key => $value ) {
				$result = $this->save_stat( $item['url'], $key, (int) ( $value * 100 ) );

				//TO DO: Where should I log failures?
			}
		}

		return new WP_REST_Response( $body, \WP_Http::OK );
	}
}

new Site_Quality_Stats_Controller();
