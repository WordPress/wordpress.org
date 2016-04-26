<?php

namespace WordPressdotorg\GlotPress\Customizations\REST_API\Endpoints;

use WordPressdotorg\GlotPress\Customizations\REST_API\Base;
use WP_Error;
use WP_REST_Server;

class Jobs_Controller extends Base {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/jobs', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permission_check_internal_api_bearer' ],
				'args'                => [
					'timestamp'  => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ $this, 'validate_timestamp' ],
					],
					'recurrence' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ $this, 'validate_recurrence' ],
					],
					'hook'       => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'args'       => [
						'default' => [],
						'validate_callback' => [ $this, 'validate_args' ],
					],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permission_check_internal_api_bearer' ],
				'args'                => [
					'timestamp'  => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => [ $this, 'validate_timestamp' ],
					],
					'hook'       => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'args'       => [
						'default' => [],
						'validate_callback' => [ $this, 'validate_args' ],
					],
				],
			],
		] );
	}

	/**
	 * Get a collection of jobs.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items() {
		$cron_array = _get_cron_array();
		return rest_ensure_response( $cron_array );
	}

	/**
	 * Creates a new job.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_Error|\WP_REST_Response WP_Error on failure, WP_REST_Response on success.
	 */
	public function create_item( $request ) {
		if ( 'once' === $request['recurrence'] ) {
			$result = wp_schedule_single_event( $request['timestamp'], $request['hook'], $request['args'] );
			if ( false === $result ) {
				return new WP_Error( 'insert_failed' );
			}

			$next_scheduled = wp_next_scheduled( $request['hook'], $request['args'] );
			$data = [
				'hook'    => $request['hook'],
				'start'   => $request['timestamp'],
				'nextrun' => (int) $next_scheduled,
				'args'    => $request['args'],
			];
			return rest_ensure_response( $data );
		}

		$result = wp_schedule_event( $request['timestamp'], $request['recurrence'], $request['hook'], $request['args'] );
		if ( false === $result ) {
			return new WP_Error( 'insert_failed' );
		}

		$next_scheduled = wp_next_scheduled( $request['hook'], $request['args'] );
		$data = [
			'hook'    => $request['hook'],
			'start'   => $request['timestamp'],
			'nextrun' => (int) $next_scheduled,
			'args'    => $request['args'],
		];
		return rest_ensure_response( $data );
	}

	/**
	 * Delete one job from the collection.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_Error|bool WP_Error on failure, true on success.
	 */
	public function delete_item( $request ) {
		$result = wp_unschedule_event( $request['timestamp'], $request['hook'], $request['args'] );
		if ( false === $result ) {
			return new WP_Error( 'insert_failed' );
		}

		return true;
	}

	/**
	 * Validates a timestamp.
	 *
	 * @param int $timestamp The timestamp to validate.
	 * @return bool True if valid, false if not.
	 */
	public function validate_timestamp( $timestamp ) {
		if ( is_numeric( $timestamp ) && $timestamp > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Validates a recurrence value.
	 *
	 * @param string $recurrence The recurrence value to validate.
	 * @return bool True if valid, false if not.
	 */
	public function validate_recurrence( $recurrence ) {
		if ( 'once' === $recurrence ) {
			return true;
		}

		$schedules = wp_get_schedules();

		if ( isset( $schedules[ $recurrence ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validates the args parameter for a job.
	 *
	 * @param array $args The args parameter to validate.
	 * @return bool True if valid, false if not.
	 */
	public function validate_args( $args ) {
		return is_array( $args );
	}
}
