<?php
/**
 * Advisory Gandalf scan integration for plugin updates.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */

namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * Sends plugin updates to Gandalf for advisory security scans.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Updates_Gandalf {

	/** Pending scans keyed by scan_id, used to recognize callbacks. */
	const PENDING_META_KEY = '_gandalf_scan_pending';

	/** Verdict hashes already sent to Slack, to avoid duplicate alerts. */
	const NOTIFIED_META_KEY = '_gandalf_scan_notified';

	/** Last dispatch or callback error for quick operator debugging. */
	const LAST_ERROR_META_KEY = '_gandalf_scan_last_error';

	/** Gandalf scan endpoint. */
	const ENDPOINT = 'https://gandalf.wordpress.org/scan';

	/**
	 * Dispatch a Gandalf scan from the importer context carried through cron.
	 *
	 * @param \WP_Post $plugin         The plugin post.
	 * @param array    $import_context The importer context.
	 * @return bool Whether the request was accepted.
	 */
	public static function dispatch_from_import_context( $plugin, $import_context ) {
		if ( ! defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) || ! WP_GANDALF_SCAN_SHARED_SECRET ) {
			return false;
		}

		if ( ! is_array( $import_context ) ) {
			return false;
		}

		// These are wporg_plugins_imported facts carried through scan_plugin cron.
		// If they are incomplete or malformed, skip instead of guessing a release.
		if (
			! isset( $import_context['stable_tag'], $import_context['old_stable_tag'], $import_context['changed_svn_tags'] ) ||
			! is_string( $import_context['stable_tag'] ) ||
			! is_string( $import_context['old_stable_tag'] ) ||
			! is_array( $import_context['changed_svn_tags'] )
		) {
			return false;
		}

		$stable_tag           = $import_context['stable_tag'];
		$old_stable_tag       = $import_context['old_stable_tag'];
		$changed_svn_tags     = $import_context['changed_svn_tags'];
		$release_ref          = trim( $stable_tag ) ? $stable_tag : 'trunk';
		$previous_release_ref = trim( $old_stable_tag ) ? $old_stable_tag : null;
		$changed_svn_tags     = array_map( 'strval', $changed_svn_tags );

		// Trunk-only commits should not rescan a tag-based stable ZIP that was not rebuilt.
		if ( $release_ref === $previous_release_ref && ! in_array( $release_ref, $changed_svn_tags, true ) ) {
			return false;
		}

		// Version is post-import state; without it, the ZIP identity is not reliable.
		$version = get_post_meta( $plugin->ID, 'version', true );
		if ( ! is_string( $version ) || ! trim( $version ) ) {
			return false;
		}

		$previous_version = get_post_meta( $plugin->ID, 'last_version', true );
		$previous_version = is_string( $previous_version ) && trim( $previous_version ) ? $previous_version : null;
		$previous_zip_url = null;

		if ( $previous_release_ref && $previous_release_ref !== $release_ref && 'trunk' !== $previous_release_ref ) {
			$previous_zip_url = Template::download_link( $plugin, $previous_release_ref );

			// If only the stable tag changed, the previous release can have the same plugin version.
			if ( ! $previous_version ) {
				$previous_version = $version;
			}
		}

		return self::dispatch(
			$plugin,
			array(
				'scan_id'              => wp_generate_uuid4(),
				'subject_type'         => 'plugin',
				'slug'                 => $plugin->post_name,
				'version'              => $version,
				'release_ref'          => $release_ref,
				'current_zip_url'      => Template::download_link( $plugin, $release_ref ),
				'previous_version'     => $previous_zip_url ? $previous_version : null,
				'previous_release_ref' => $previous_zip_url ? $previous_release_ref : null,
				'previous_zip_url'     => $previous_zip_url,
				'callback_url'         => rest_url( 'plugins/v1/plugin/' . $plugin->post_name . '/gandalf-scan' ),
				'requested_at'         => time(),
			)
		);
	}

	/**
	 * POST a queued scan request to Gandalf.
	 *
	 * @param \WP_Post $plugin       The plugin post.
	 * @param array    $request_data The Gandalf scan request data.
	 * @return bool Whether the request was accepted.
	 */
	public static function dispatch( $plugin, $request_data ) {
		if ( ! defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) || ! WP_GANDALF_SCAN_SHARED_SECRET ) {
			return false;
		}

		foreach ( array( 'scan_id', 'subject_type', 'slug', 'version', 'release_ref', 'current_zip_url', 'callback_url' ) as $field ) {
			if ( ! isset( $request_data[ $field ] ) || ! is_string( $request_data[ $field ] ) || '' === trim( $request_data[ $field ] ) ) {
				self::record_last_error( $plugin, 'invalid_request_data', "Gandalf scan request missing {$field}." );
				printf( "Failed to dispatch Gandalf scan for %s: invalid request data.\n", esc_html( $plugin->post_name ) );
				return false;
			}
		}

		foreach ( array( 'previous_version', 'previous_release_ref', 'previous_zip_url' ) as $field ) {
			if ( ! array_key_exists( $field, $request_data ) || ( null !== $request_data[ $field ] && ! is_string( $request_data[ $field ] ) ) ) {
				self::record_last_error( $plugin, 'invalid_request_data', "Gandalf scan request {$field} is invalid." );
				printf( "Failed to dispatch Gandalf scan for %s: invalid request data.\n", esc_html( $plugin->post_name ) );
				return false;
			}
		}

		if (
				! wp_is_uuid( $request_data['scan_id'], 4 ) ||
				'plugin' !== $request_data['subject_type'] ||
				$plugin->post_name !== $request_data['slug'] ||
				! wp_http_validate_url( $request_data['current_zip_url'] ) ||
				'https' !== wp_parse_url( $request_data['current_zip_url'], PHP_URL_SCHEME ) ||
				! wp_http_validate_url( $request_data['callback_url'] ) ||
				'https' !== wp_parse_url( $request_data['callback_url'], PHP_URL_SCHEME ) ||
				! isset( $request_data['requested_at'] ) ||
				! is_int( $request_data['requested_at'] ) ||
				$request_data['requested_at'] < 0
		) {
			self::record_last_error( $plugin, 'invalid_request_data', 'Gandalf scan request data is invalid.' );
			printf( "Failed to dispatch Gandalf scan for %s: invalid request data.\n", esc_html( $plugin->post_name ) );
			return false;
		}

		if (
			null !== $request_data['previous_zip_url'] &&
			(
				! wp_http_validate_url( $request_data['previous_zip_url'] ) ||
				'https' !== wp_parse_url( $request_data['previous_zip_url'], PHP_URL_SCHEME )
			)
		) {
			self::record_last_error( $plugin, 'invalid_request_data', 'Gandalf scan previous_zip_url is invalid.' );
			printf( "Failed to dispatch Gandalf scan for %s: invalid request data.\n", esc_html( $plugin->post_name ) );
			return false;
		}

		$pending = get_post_meta( $plugin->ID, self::PENDING_META_KEY, true );
		$pending = is_array( $pending ) ? $pending : array();
		foreach ( $pending as $scan_id => $record ) {
			if ( ! is_array( $record ) || empty( $record['requested_at'] ) || $record['requested_at'] < time() - DAY_IN_SECONDS ) {
				unset( $pending[ $scan_id ] );
			}
		}

		$pending[ $request_data['scan_id'] ] = array(
			'version'      => $request_data['version'],
			'release_ref'  => $request_data['release_ref'],
			'requested_at' => $request_data['requested_at'],
		);
		update_post_meta( $plugin->ID, self::PENDING_META_KEY, $pending );

		$body = wp_json_encode( $request_data );
		if ( ! $body ) {
			return self::dispatch_failed( $plugin, $request_data, 'Failed to encode Gandalf scan request.', 'encode_failed' );
		}

		$response = wp_safe_remote_post(
			self::ENDPOINT,
			array(
				'timeout'    => 15,
				'user-agent' => 'WordPress.org Plugin Directory Gandalf Scan',
				'headers'    => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . WP_GANDALF_SCAN_SHARED_SECRET,
					'Content-Type'  => 'application/json',
				),
				'body'       => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::dispatch_failed( $plugin, $request_data, $response->get_error_message(), 'dispatch_wp_error' );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			return self::dispatch_failed( $plugin, $request_data, sprintf( 'Gandalf returned HTTP %d.', $response_code ), 'dispatch_http_error' );
		}

		$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $response_data ) || ( $response_data['scan_id'] ?? '' ) !== $request_data['scan_id'] ) {
			return self::dispatch_failed( $plugin, $request_data, 'Gandalf accepted the scan with an invalid response body.', 'dispatch_ack_invalid' );
		}

		printf(
			"Dispatched Gandalf scan %s for %s.\n",
			esc_html( $request_data['scan_id'] ),
			esc_html( $plugin->post_name )
		);
		return true;
	}

	/**
	 * Handle a completed or failed scan callback.
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $data   The validated Gandalf callback data.
	 * @return true|\WP_Error True on success, or an error when the scan is unknown.
	 */
	public static function handle_callback( $plugin, $data ) {
		$scan_id = $data['scan_id'];
		$pending = get_post_meta( $plugin->ID, self::PENDING_META_KEY, true );
		$pending = is_array( $pending ) ? $pending : array();

		if ( empty( $pending[ $scan_id ] ) || ! is_array( $pending[ $scan_id ] ) ) {
			$error = new \WP_Error( 'unknown_gandalf_scan', 'Unknown Gandalf scan_id.', array( 'status' => \WP_Http::BAD_REQUEST ) );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		$pending_record = $pending[ $scan_id ];
		if (
			! isset( $pending_record['version'], $pending_record['release_ref'], $pending_record['requested_at'] ) ||
			! is_string( $pending_record['version'] ) ||
			! is_string( $pending_record['release_ref'] )
		) {
			$error = new \WP_Error( 'invalid_gandalf_scan', 'Stored Gandalf scan data is invalid.', array( 'status' => \WP_Http::BAD_REQUEST ) );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		if ( $data['version'] !== $pending_record['version'] || $data['release_ref'] !== $pending_record['release_ref'] ) {
			$error = new \WP_Error( 'invalid_gandalf_scan', 'Gandalf callback does not match the pending scan.', array( 'status' => \WP_Http::BAD_REQUEST ) );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		if ( 'completed' === $data['status'] ) {
			if ( $data['findings_count'] > 0 ) {
				self::notify_slack(
					$plugin,
					array(
						'version'         => $pending_record['version'],
						'release_ref'     => $pending_record['release_ref'],
						'findings_count'  => absint( $data['findings_count'] ),
						'severity_counts' => $data['severity_counts'],
						'verdict_hash'    => sanitize_text_field( $data['verdict_hash'] ),
						'report_url'      => esc_url_raw( $data['report_url'] ),
					)
				);
			}
		} else {
			self::record_last_error( $plugin, $data['error']['kind'], $data['error']['message'], $scan_id );
		}

		unset( $pending[ $scan_id ] );
		update_post_meta( $plugin->ID, self::PENDING_META_KEY, $pending );

		return true;
	}

	/**
	 * Record a valid-secret callback that failed validation.
	 *
	 * @param \WP_Post  $plugin  The plugin post.
	 * @param \WP_Error $error   The validation error.
	 * @param string    $scan_id Optional scan ID.
	 */
	public static function record_invalid_callback( $plugin, $error, $scan_id = '' ) {
		self::record_last_error( $plugin, $error->get_error_code(), $error->get_error_message(), $scan_id );
	}

	/**
	 * Record and report a failed dispatch.
	 *
	 * @param \WP_Post $plugin       The plugin post.
	 * @param array    $request_data The Gandalf scan request data.
	 * @param string   $message      The failure message.
	 * @param string   $kind         The failure kind.
	 * @return false Always false.
	 */
	protected static function dispatch_failed( $plugin, $request_data, $message, $kind ) {
		$scan_id = sanitize_text_field( $request_data['scan_id'] );

		self::record_last_error( $plugin, $kind, $message, $scan_id );

		$pending = get_post_meta( $plugin->ID, self::PENDING_META_KEY, true );
		$pending = is_array( $pending ) ? $pending : array();
		unset( $pending[ $scan_id ] );
		update_post_meta( $plugin->ID, self::PENDING_META_KEY, $pending );

		printf(
			"Failed to dispatch Gandalf scan for %s: %s\n",
			esc_html( $plugin->post_name ),
			esc_html( $message )
		);
		return false;
	}

	/**
	 * Notify Slack about a Gandalf scan with findings.
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $record The completed scan summary.
	 */
	protected static function notify_slack( $plugin, $record ) {
		if ( 'closed' === $plugin->post_status || empty( $record['verdict_hash'] ) ) {
			return;
		}

		$already_notified = get_post_meta( $plugin->ID, self::NOTIFIED_META_KEY, true );
		$already_notified = is_array( $already_notified ) ? $already_notified : array();
		foreach ( $already_notified as $hash => $time ) {
			if ( $time < time() - MONTH_IN_SECONDS ) {
				unset( $already_notified[ $hash ] );
			}
		}

		if ( isset( $already_notified[ $record['verdict_hash'] ] ) ) {
			update_post_meta( $plugin->ID, self::NOTIFIED_META_KEY, $already_notified );
			return;
		}

		$already_notified[ $record['verdict_hash'] ] = time();
		update_post_meta( $plugin->ID, self::NOTIFIED_META_KEY, $already_notified );

		if ( ! defined( 'PLUGIN_REVIEW_ALERT_SLACK_CHANNEL' ) || ! function_exists( 'slack_dm' ) ) {
			return;
		}

		$active_installs = (int) get_post_meta( $plugin->ID, 'active_installs', true );
		$install_line    = sprintf( '%s+ active installs', number_format_i18n( $active_installs ) );
		if ( $active_installs >= 10000 ) {
			$install_line = ":bangbang::bangbang::bangbang: {$install_line} :bangbang::bangbang::bangbang:";
		}

		$body = sprintf(
			"Gandalf scan detected findings in *%s*\n%s\nVersion: %s (%s)\nFindings: %d\n",
			$plugin->post_title,
			$install_line,
			$record['version'],
			$record['release_ref'],
			$record['findings_count']
		);

		if ( ! empty( $record['severity_counts'] ) ) {
			$severity_summary = array();
			foreach ( $record['severity_counts'] as $severity => $count ) {
				if ( $count > 0 ) {
					$severity_summary[] = "{$severity}: {$count}";
				}
			}

			if ( $severity_summary ) {
				$body .= 'Severity: ' . implode( ', ', $severity_summary ) . "\n";
			}
		}

		$body .= sprintf( "Details: https://wordpress.org/plugins/wp-admin/post.php?post=%s&action=edit\n", $plugin->ID );
		$body .= sprintf( "Plugin: https://wordpress.org/plugins/%s/\n", $plugin->post_name );
		$body .= sprintf( "Report: %s\n", $record['report_url'] );

		slack_dm( $body, PLUGIN_REVIEW_ALERT_SLACK_CHANNEL, true );
	}

	/**
	 * Store the last Gandalf integration error on the plugin.
	 *
	 * @param \WP_Post $plugin  The plugin post.
	 * @param string   $kind    The error kind.
	 * @param string   $message The error message.
	 * @param string   $scan_id Optional scan ID.
	 */
	protected static function record_last_error( $plugin, $kind, $message, $scan_id = '' ) {
		update_post_meta(
			$plugin->ID,
			self::LAST_ERROR_META_KEY,
			array(
				'kind'        => sanitize_key( $kind ),
				'message'     => sanitize_text_field( $message ),
				'scan_id'     => sanitize_text_field( $scan_id ),
				'recorded_at' => time(),
			)
		);
	}
}
