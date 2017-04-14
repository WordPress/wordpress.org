<?php
/**
 * REST Controller for Tasks.
 *
 * @package WordCamp\Mentors
 */

namespace WordCamp\Mentors\Tasks;
defined( 'WPINC' ) || die();

use WordCamp\Mentors;

/**
 * Class Controller.
 *
 * @package WordCamp\Mentors\Tasks
 */
class Controller extends \WP_REST_Posts_Controller {
	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * Based on the routes for the Posts controller, but more limited.
	 *
	 * @since 1.0.0
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		$get_item_args = array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
		);

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			'args' => array(
				'id' => array(
					'description' => __( 'Unique identifier for the object.' ),
					'type'        => 'integer',
				),
			),
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
				'args'                => $get_item_args,
			),
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Catchall permissions check for interacting with tasks via the REST API.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function get_permissions_check() {
		return current_user_can( Mentors\ORGANIZER_CAP ) || current_user_can( Mentors\MENTOR_CAP );
	}

	/**
	 * Retrieves the Task post's schema, conforming to JSON Schema.
	 *
	 * Task-specific modifications to the standard post schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		$schema = parent::get_item_schema();

		// Show the custom statuses in the REST response.
		if ( false === array_search( 'view', $schema['properties']['status']['context'], true ) ) {
			$schema['properties']['status']['context'][] = 'view';
		}

		// Specify custom statuses.
		$schema['properties']['status']['enum'] = array_keys( get_task_statuses() );

		// Add a localized, relative date string.
		$schema['properties']['modified']['type'] = 'object';
		$schema['properties']['modified']['properties'] = array(
			'raw' => array(
				'description' => __( "The date the object was last modified, in the site's timezone." ),
				'type'        => 'string',
				'format'      => 'date-time',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'relative' => array(
				'description' => __( 'The relative time since the object was last modified.' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Retrieves the query params for the posts collection.
	 *
	 * Task-specific modifications to the standard posts collection query params.
	 *
	 * @since 1.0.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		// Allow posts with our custom statuses.
		$query_params['status']['items']['enum'] = array_keys( get_task_statuses() );
		$query_params['status']['default'] = $query_params['status']['items']['enum'];

		// Allow a higher maximum for query results.
		$query_params['per_page']['maximum'] = 300;

		return $query_params;
	}

	/**
	 * Sanitizes and validates the list of post statuses, including whether the
	 * user can query private statuses.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|array     $statuses  One or more post statuses.
	 * @param  \WP_REST_Request $request   Full details about the request.
	 * @param  string           $parameter Additional parameter to pass to validation.
	 * @return array|\WP_Error A list of valid statuses, otherwise WP_Error object.
	 */
	public function sanitize_post_statuses( $statuses, $request, $parameter ) {
		$statuses = wp_parse_slug_list( $statuses );

		$task_statuses = array_keys( get_task_statuses() );

		foreach ( $statuses as $status ) {
			if ( in_array( $status, $task_statuses, true ) ) {
				continue;
			}

			if ( current_user_can( Mentors\ORGANIZER_CAP ) ) {
				$result = rest_validate_request_arg( $status, $request, $parameter );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			} else {
				return new \WP_Error(
					'rest_forbidden_status',
					__( 'Status is forbidden.' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		return $statuses;
	}
}
