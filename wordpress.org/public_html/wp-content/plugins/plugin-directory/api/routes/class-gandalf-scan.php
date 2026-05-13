<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Updates_Gandalf;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Callback endpoint for advisory Gandalf scans.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Gandalf_Scan extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/gandalf-scan', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'scan_callback' ),
			'args'                => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				),
			),
			'permission_callback' => array( $this, 'permission_check_gandalf_scan_bearer' ),
		) );
	}

	/**
	 * Permission check for Gandalf callbacks.
	 */
	public function permission_check_gandalf_scan_bearer( $request ) {
		return $this->permission_check_api_bearer( $request, 'WP_GANDALF_SCAN_SHARED_SECRET' );
	}

	/**
	 * Receive a Gandalf scan callback.
	 */
	public function scan_callback( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		if ( ! $plugin ) {
			return new \WP_Error(
				'plugin_not_found',
				__( 'Plugin not found.', 'wporg-plugins' ),
				array( 'status' => \WP_Http::NOT_FOUND )
			);
		}

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$error = new \WP_Error(
				'invalid_gandalf_scan_callback',
				__( 'Invalid Gandalf scan callback.', 'wporg-plugins' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);

			Plugin_Updates_Gandalf::record_invalid_callback( $plugin, $error );
			return $error;
		}

		$validated_data = $this->validate_callback_payload( $plugin, $data );
		if ( is_wp_error( $validated_data ) ) {
			$scan_id = isset( $data['scan_id'] ) && is_string( $data['scan_id'] ) ? $data['scan_id'] : '';
			Plugin_Updates_Gandalf::record_invalid_callback( $plugin, $validated_data, $scan_id );
			return $validated_data;
		}

		$result = Plugin_Updates_Gandalf::handle_callback( $plugin, $validated_data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
		);
	}

	/**
	 * Validate and normalize a Gandalf callback payload.
	 */
	protected function validate_callback_payload( $plugin, $data ) {
		$invalid = static function( $message ) {
			return new \WP_Error(
				'invalid_gandalf_scan_callback',
				$message,
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		};

		foreach ( array( 'status', 'scan_id', 'subject_type', 'slug', 'version', 'release_ref', 'report_url' ) as $field ) {
			if ( ! $this->is_non_empty_string( $data, $field ) ) {
				return $invalid( "Gandalf scan callback missing required field: {$field}." );
			}
		}

		if ( ! wp_is_uuid( $data['scan_id'], 4 ) ) {
			return $invalid( 'Gandalf scan callback scan_id must be a UUIDv4.' );
		}

		if ( 'plugin' !== $data['subject_type'] || $plugin->post_name !== $data['slug'] ) {
			return $invalid( 'Gandalf scan callback does not match this plugin.' );
		}

		if ( ! in_array( $data['status'], array( 'completed', 'failed' ), true ) ) {
			return $invalid( 'Gandalf scan callback status must be completed or failed.' );
		}

		if ( ! isset( $data['completed_at'] ) || ! is_int( $data['completed_at'] ) || $data['completed_at'] < 0 ) {
			return $invalid( 'Gandalf scan callback completed_at must be a Unix timestamp.' );
		}

		if ( ! $this->is_https_url( $data['report_url'] ) ) {
			return $invalid( 'Gandalf scan callback report_url must be an HTTPS URL.' );
		}

		$validated = array(
			'status'       => $data['status'],
			'scan_id'      => sanitize_text_field( $data['scan_id'] ),
			'subject_type' => 'plugin',
			'slug'         => $plugin->post_name,
			'version'      => sanitize_text_field( $data['version'] ),
			'release_ref'  => sanitize_text_field( $data['release_ref'] ),
			'completed_at' => absint( $data['completed_at'] ),
			'report_url'   => esc_url_raw( $data['report_url'] ),
		);

		if ( 'completed' === $data['status'] ) {
			$unknown_fields = array_diff(
				array_keys( $data ),
				array( 'status', 'scan_id', 'subject_type', 'slug', 'version', 'release_ref', 'completed_at', 'verdict_hash', 'findings_count', 'severity_counts', 'scanner_version', 'report_url' )
			);
			if ( $unknown_fields ) {
				return $invalid( 'Gandalf scan completed callback includes unknown fields.' );
			}

			foreach ( array( 'verdict_hash', 'scanner_version' ) as $field ) {
				if ( ! $this->is_non_empty_string( $data, $field ) ) {
					return $invalid( "Gandalf scan completed callback missing required field: {$field}." );
				}
			}

			if ( ! isset( $data['findings_count'] ) || ! is_int( $data['findings_count'] ) || $data['findings_count'] < 0 ) {
				return $invalid( 'Gandalf scan completed callback findings_count must be an integer.' );
			}

			if ( ! isset( $data['severity_counts'] ) || ! is_array( $data['severity_counts'] ) ) {
				return $invalid( 'Gandalf scan completed callback severity_counts must be an object.' );
			}

			$severity_counts = array();
			foreach ( $data['severity_counts'] as $severity => $count ) {
				if (
					! is_string( $severity ) ||
					'' === $severity ||
					sanitize_key( $severity ) !== $severity ||
					! is_int( $count ) ||
					$count < 0
				) {
					return $invalid( 'Gandalf scan completed callback severity_counts must be severity-to-integer pairs.' );
				}

				$severity_counts[ $severity ] = $count;
			}

			$validated['verdict_hash']    = sanitize_text_field( $data['verdict_hash'] );
			$validated['findings_count']  = absint( $data['findings_count'] );
			$validated['severity_counts'] = $severity_counts;
			$validated['scanner_version'] = sanitize_text_field( $data['scanner_version'] );
		} else {
			$unknown_fields = array_diff(
				array_keys( $data ),
				array( 'status', 'scan_id', 'subject_type', 'slug', 'version', 'release_ref', 'completed_at', 'report_url', 'error' )
			);
			if ( $unknown_fields ) {
				return $invalid( 'Gandalf scan failed callback includes unknown fields.' );
			}

			if ( empty( $data['error'] ) || ! is_array( $data['error'] ) ) {
				return $invalid( 'Gandalf scan failed callback requires error data.' );
			}

			if ( array_diff( array_keys( $data['error'] ), array( 'kind', 'message' ) ) ) {
				return $invalid( 'Gandalf scan failed callback error includes unknown fields.' );
			}

			if (
				! $this->is_non_empty_string( $data['error'], 'kind' ) ||
				sanitize_key( $data['error']['kind'] ) !== $data['error']['kind'] ||
				! $this->is_non_empty_string( $data['error'], 'message' )
			) {
				return $invalid( 'Gandalf scan failed callback error data is invalid.' );
			}

			$validated['error'] = array(
				'kind'    => $data['error']['kind'],
				'message' => sanitize_text_field( $data['error']['message'] ),
			);
		}

		return $validated;
	}

	protected function is_non_empty_string( $data, $field ) {
		return isset( $data[ $field ] ) && is_string( $data[ $field ] ) && '' !== trim( $data[ $field ] );
	}

	protected function is_https_url( $value ) {
		return is_string( $value ) && wp_http_validate_url( $value ) && 'https' === wp_parse_url( $value, PHP_URL_SCHEME );
	}
}
