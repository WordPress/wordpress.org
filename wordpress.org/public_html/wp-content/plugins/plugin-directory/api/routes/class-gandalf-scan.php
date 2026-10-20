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
 * Callback endpoint for security scan results.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Gandalf_Scan extends Base {

	/**
	 * Registers the callback route.
	 *
	 * The args carry the callback body schema per the integration contract.
	 * The schema is strict only about what the directory acts on: the REST
	 * server rejects violations before the callback runs, so anything
	 * stricter — length caps, unknown-field rejection, display-only enums —
	 * would void whole deliveries when the scanner evolves.
	 */
	public function __construct() {
		register_rest_route(
			'plugins/v1',
			'/plugin/(?P<plugin_slug>[^/]+)/gandalf-scan',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'scan_callback' ],
				'permission_callback' => function ( $request ) {
					return $this->permission_check_api_bearer( $request, 'WP_GANDALF_SCAN_SHARED_SECRET' );
				},
				'args'                => [
					'plugin_slug'     => [
						'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
					],
					'status'          => [
						'type'              => 'string',
						'enum'              => [ 'completed', 'failed' ],
						'required'          => true,
						'validate_callback' => [ $this, 'validate_status_callback' ],
					],
					'scan_id'         => [
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
					],
					'subject_type'    => [
						'type'     => 'string',
						'enum'     => [ 'plugin' ],
						'required' => true,
					],
					'slug'            => [
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
					],
					'version'         => [
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
					],
					'release_ref'     => [
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
					],
					'completed_at'    => [
						'type'    => 'integer',
						'minimum' => 0,
					],
					'verdict_hash'    => [
						'type'      => 'string',
						'minLength' => 1,
					],
					'findings_count'  => [
						'type'    => 'integer',
						'minimum' => 0,
					],
					'max_risk_score'  => [
						'type' => 'number',
					],
					'findings'        => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							// Only the score is acted on; descriptive fields are read defensively.
							'required'   => [ 'risk_score' ],
							'properties' => [
								'id'            => [
									'type'      => 'string',
									'minLength' => 1,
								],
								'ref'           => [
									'type'      => 'string',
									'minLength' => 1,
								],
								'title'         => [
									'type'      => 'string',
									'minLength' => 1,
								],
								'severity'      => [
									'type'      => 'string',
									'minLength' => 1,
								],
								'file_path'     => [
									'type'      => 'string',
									'minLength' => 1,
								],
								'line'          => [
									'type' => 'integer',
								],
								'code_snippet'  => [
									'type' => 'string',
								],
								'explanation'   => [
									'type' => 'string',
								],
								'risk_score'    => [
									'type' => 'number',
								],
								'investigation' => [
									'type'       => 'object',
									'properties' => [
										'status'  => [
											'type'      => 'string',
											'minLength' => 1,
										],
										'result'  => [
											'type'      => 'string',
											'minLength' => 1,
										],
										'summary' => [
											'type'      => 'string',
											'minLength' => 1,
										],
									],
								],
							],
						],
					],
					'severity_counts' => [
						'type'                 => 'object',
						'additionalProperties' => [
							'type'    => 'integer',
							'minimum' => 0,
						],
					],
					'scanner_version' => [
						'type' => 'string',
					],
					'report_url'      => [
						'type'      => 'string',
						'format'    => 'uri',
						'minLength' => 1,
					],
					'error'           => [
						'type'       => 'object',
						'required'   => [ 'kind', 'message' ],
						'properties' => [
							'kind'    => [
								'type'      => 'string',
								'minLength' => 1,
							],
							'message' => [
								'type'      => 'string',
								'minLength' => 1,
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Validate that a callback body carries the fields its status implies.
	 *
	 * The contract has two halves — a completed scan reports a verdict, a
	 * failed one reports an error — and `required` cannot express either,
	 * being unconditional. Enforcing it here keeps the whole body schema in
	 * the route, so the callback runs only on a payload it can read directly.
	 *
	 * @param mixed            $value   The status value.
	 * @param \WP_REST_Request $request The request.
	 * @param string           $param   The parameter name.
	 * @return true|WP_Error True when the body matches its status.
	 */
	public function validate_status_callback( $value, $request, $param ) {
		$valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$required = 'completed' === $value
			? [ 'verdict_hash', 'findings_count', 'severity_counts', 'max_risk_score', 'findings', 'report_url' ]
			: [ 'error' ];

		foreach ( $required as $field ) {
			if ( null === $request->get_param( $field ) ) {
				return new WP_Error(
					'rest_missing_callback_param',
					/* translators: 1: Field name, 2: Callback status. */
					sprintf( __( '%1$s is required for a %2$s security scan callback.', 'wporg-plugins' ), $field, $value ),
					[ 'status' => WP_Http::BAD_REQUEST ]
				);
			}
		}

		return true;
	}

	/**
	 * Receive a security scan callback.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array|WP_Error Callback response, or an error.
	 */
	public function scan_callback( $request ) {
		// JSON body params outrank URL params in get_param(); the URL segment is the routed identity.
		$plugin = Plugin_Directory::get_plugin_post( $request->get_url_params()['plugin_slug'] );
		if ( ! $plugin ) {
			return new WP_Error(
				'plugin_not_found',
				__( 'Plugin not found.', 'wporg-plugins' ),
				[ 'status' => WP_Http::NOT_FOUND ]
			);
		}

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$error = new WP_Error(
				'invalid_gandalf_scan_callback',
				__( 'Invalid Gandalf scan callback.', 'wporg-plugins' ),
				[ 'status' => WP_Http::BAD_REQUEST ]
			);

			Plugin_Scan_Gandalf::record_invalid_callback( $plugin, $error );
			return $error;
		}

		// The payload must assert the same plugin the callback was routed to.
		if ( ( $data['slug'] ?? '' ) !== $plugin->post_name ) {
			$error = new WP_Error( 'invalid_gandalf_scan', 'Security scan callback slug does not match the plugin.', [ 'status' => WP_Http::BAD_REQUEST ] );
			Plugin_Scan_Gandalf::record_invalid_callback( $plugin, $error, sanitize_text_field( (string) ( $data['scan_id'] ?? '' ) ) );
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
}
