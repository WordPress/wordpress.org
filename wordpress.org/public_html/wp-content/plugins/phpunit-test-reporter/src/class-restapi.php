<?php
namespace PTR;

use WP_Error;

class RestAPI {

	/**
	 * Register REST API routes.
	 *
	 * @action rest_api_init
	 */
	public static function register_routes() {
		register_rest_route(
			'wp-unit-test-api/v1', 'results', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'add_results_callback' ),
				'args'                => array(
					'commit'  => array(
						'required'          => true,
						'description'       => 'The SVN commit changeset number.',
						'type'              => 'numeric',
						'validate_callback' => array( __CLASS__, 'validate_callback' ),
					),
					'results' => array(
						'required'          => true,
						'description'       => 'phpunit results in JSON format.',
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_callback' ),
					),
					'message' => array(
						'required'          => true,
						'description'       => 'The SVN commit message.',
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_callback' ),
					),
					'env'     => array(
						'required'          => true,
						'description'       => 'JSON blob containing information about the environment.',
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_callback' ),
					),
				),
				'permission_callback' => array( __CLASS__, 'permission' ),
			)
		);
	}

	public static function validate_callback( $value, $request, $key ) {
		switch ( $key ) {
			case 'commit':
				if ( ! is_numeric( $value ) ) {
					return new WP_Error(
						'rest_invalid', __( 'Value must be numeric.', 'ptr' ), array(
							'status' => 400,
						)
					);
				}
				return true;
			case 'message':
				if ( empty( $value ) || ! is_string( $value ) ) {
					return new WP_Error(
						'rest_invalid', __( 'Value must be a non-empty string.', 'ptr' ), array(
							'status' => 400,
						)
					);
				}
				return true;
			case 'env':
			case 'results':
				if ( null === json_decode( $value ) ) {
					return new WP_Error(
						'rest_invalid', __( 'Value must be encoded JSON.', 'ptr' ), array(
							'status' => 400,
						)
					);
				}
				return true;
		}
		return new WP_Error(
			'rest_invalid', __( 'Invalid key specified.', 'ptr' ), array(
				'status' => 400,
			)
		);
	}

	public static function permission() {
		if ( ! current_user_can( 'edit_results' ) ) {
			return new WP_Error(
				'rest_unauthorized', __( 'Sorry, you are not allowed to create results.', 'ptr' ), array(
					'status' => is_user_logged_in() ? 403 : 401,
				)
			);
		}
		return true;
	}

	public static function add_results_callback( $data ) {
		$parameters = $data->get_params();

		$slug = 'r' . $parameters['commit'];
		$post = get_page_by_path( $slug, 'OBJECT', 'result' );
		if ( $post ) {
			$parent_id = $post->ID;
		} else {
			$parent_id = wp_insert_post(
				array(
					'post_title'  => $parameters['message'],
					'post_name'   => $slug,
					'post_status' => 'publish',
					'post_type'   => 'result',
				)
			);
		}

		$current_user = wp_get_current_user();

		$args = array(
			'post_parent' => $parent_id,
			'post_type'   => 'result',
			'numberposts' => 1,
			'author'      => $current_user->ID,
		);

		// Check to see if the test result already exist.
		$results = get_posts( $args );
		if ( $results ) {
			$post_id = $results[0]->ID;
		} else {
			$results = array(
				'post_title'   => $current_user->user_login . ' - ' . $slug,
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => $current_user->ID,
				'post_type'    => 'result',
				'post_parent'  => $parent_id,
			);

			// Store the results.
			$post_id = wp_insert_post( $results, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$env     = isset( $parameters['env'] ) ? json_decode( $parameters['env'], true ) : array();
		$results = isset( $parameters['results'] ) ? json_decode( $parameters['results'], true ) : array();

		update_post_meta( $post_id, 'env', $env );
		update_post_meta( $post_id, 'results', $results );

		// Create the response object.
		$response = new \WP_REST_Response(
			array(
				'id'   => $post_id,
				'link' => get_permalink( $post_id ),
			)
		);

		// Add a custom status code.
		$response->set_status( 201 );

		$response->header( 'Content-Type', 'application/json' );

		return $response;
	}
}
