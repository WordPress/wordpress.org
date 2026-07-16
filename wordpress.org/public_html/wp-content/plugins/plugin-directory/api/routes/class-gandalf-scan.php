<?php
/**
 * Gandalf scan callback REST route.
 *
 * @package WordPressdotorg_Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WP_Error;
use WP_Http;

/**
 * Callback endpoint for Gandalf scans.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Gandalf_Scan extends Base {

	/**
	 * Registers the callback route.
	 */
	public function __construct() {
		register_rest_route(
			'plugins/v1',
			'/plugin/(?P<plugin_slug>[^/]+)/gandalf-scan',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'scan_callback' ],
				'args'                => [
					'plugin_slug'     => [
						'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
					],
					'scan_id'         => [
						'required' => true,
						'type'     => 'string',
					],
					'status'          => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_status_callback' ],
					],
					'version'         => [
						'required' => true,
						'type'     => 'string',
					],
					'release_ref'     => [
						'required' => true,
						'type'     => 'string',
					],

					/*
					 * A clean scan lets a release skip the rest of its delay, so the count has to
					 * be a real number: `(int) 'abc'` is 0, which would read as clean. The minimum
					 * rejects a negative count, which is a contract violation rather than a verdict.
					 */
					'findings_count'  => [
						'type'    => 'integer',
						'minimum' => 0,
					],
					'severity_counts' => [
						'type' => 'object',
					],
					'verdict_hash'    => [
						'type' => 'string',
					],
					'report_url'      => [
						'type'   => 'string',
						'format' => 'uri',
					],
					'error'           => [
						'type'       => 'object',
						'properties' => [
							'kind'    => [ 'type' => 'string' ],
							'message' => [ 'type' => 'string' ],
						],
					],
				],
				'permission_callback' => function ( $request ) {
					return $this->permission_check_api_bearer( $request, 'WP_GANDALF_SCAN_SHARED_SECRET' );
				},
			]
		);
	}

	/**
	 * Receive a Gandalf scan callback.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array|WP_Error Callback response, or an error.
	 */
	public function scan_callback( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$error = new WP_Error( 'invalid_gandalf_scan_callback', 'Invalid Gandalf scan callback.', [ 'status' => WP_Http::BAD_REQUEST ] );

			Plugin_Scan_Gandalf::record_invalid_callback( $plugin, $error );
			return $error;
		}

		$result = Plugin_Scan_Gandalf::handle_callback( $plugin, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'success' => true,
		];
	}

	/**
	 * Validate `status`, and with it the fields that a given status has to carry.
	 *
	 * The args schema can express "findings_count must be a non-negative integer" but not
	 * "and it must be present when the scan completed", which is the case that matters: the
	 * handler reads the count to decide whether a release skips the rest of its delay.
	 *
	 * @param mixed            $value   The status.
	 * @param \WP_REST_Request $request The request.
	 * @param string           $param   The parameter name.
	 * @return true|WP_Error True when the status and its companion fields are usable.
	 */
	public function validate_status_callback( $value, $request, $param ) {
		$valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( 'completed' === $value ) {
			if ( null === $request->get_param( 'findings_count' ) ) {
				return new WP_Error( 'invalid_gandalf_findings_count', 'A completed Gandalf scan must report a findings_count.', [ 'status' => WP_Http::BAD_REQUEST ] );
			}

			return true;
		}

		$error = $request->get_param( 'error' );
		if ( ! isset( $error['kind'], $error['message'] ) ) {
			return new WP_Error( 'invalid_gandalf_scan_error', 'A Gandalf scan that did not complete must describe the error.', [ 'status' => WP_Http::BAD_REQUEST ] );
		}

		return true;
	}
}
