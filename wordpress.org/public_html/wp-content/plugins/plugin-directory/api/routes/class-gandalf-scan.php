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
	 * The args carry the callback body schema per the integration contract;
	 * cross-field invariants are validated in validate_callback_data().
	 *
	 * The contract is strict only about what the directory acts on — the
	 * status branching, the plugin identity, and field types. It is
	 * deliberately lenient about the descriptive payload: no length caps, no
	 * unknown-field rejection, no enums on display-only fields, and no upper
	 * bound on scores. The REST server rejects arg violations before the
	 * callback runs, so anything stricter would void whole deliveries when
	 * the scanner evolves — an over-long model-generated string, a new
	 * severity, or an added field must not cost a verdict.
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
						'type'     => 'string',
						'enum'     => [ 'completed', 'failed' ],
						'required' => true,
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
							'required'   => [ 'id', 'ref', 'title', 'severity', 'file_path', 'risk_score', 'investigation' ],
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
									'required'   => [ 'status', 'result', 'summary' ],
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
	 * Validate the cross-field invariants of a security scan callback.
	 *
	 * Field-level validation — types and the status enum — is handled by the
	 * route args; this checks only the per-status required fields, which a
	 * per-field schema cannot express. Unknown fields are deliberately not
	 * rejected, so the scanner can add fields without breaking deliveries.
	 *
	 * @param array $data The security scan callback data.
	 * @return WP_Error|null An error for invalid callbacks, null otherwise.
	 */
	public static function validate_callback_data( $data ) {
		if ( 'completed' === ( $data['status'] ?? '' ) ) {
			$required_fields = [ 'verdict_hash', 'findings_count', 'findings', 'max_risk_score', 'severity_counts', 'report_url' ];
		} else {
			$required_fields = [ 'error' ];
		}

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				return new WP_Error(
					'invalid_gandalf_scan_callback',
					sprintf( 'Invalid security scan callback: missing %s.', $field ),
					[ 'status' => WP_Http::BAD_REQUEST ]
				);
			}
		}

		return null;
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

		$error = self::validate_callback_data( $data );

		// The payload must assert the same plugin the callback was routed to.
		if ( ! $error && ( $data['slug'] ?? '' ) !== $plugin->post_name ) {
			$error = new WP_Error( 'invalid_gandalf_scan', 'Security scan callback slug does not match the plugin.', [ 'status' => WP_Http::BAD_REQUEST ] );
		}

		if ( is_wp_error( $error ) ) {
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
