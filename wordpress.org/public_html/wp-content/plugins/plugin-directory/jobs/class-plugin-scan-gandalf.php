<?php
/**
 * Advisory Gandalf scan integration for plugin updates.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */

namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Template;
use WP_Error;
use WP_Http;

/**
 * Sends plugin updates to Gandalf for advisory security scans.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Scan_Gandalf {

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

		if (
			! isset( $import_context['stable_tag'], $import_context['old_stable_tag'], $import_context['changed_svn_tags'] ) ||
			! is_string( $import_context['stable_tag'] ) ||
			! is_string( $import_context['old_stable_tag'] ) ||
			! is_array( $import_context['changed_svn_tags'] )
		) {
			return false;
		}

		$stable_tag       = $import_context['stable_tag'];
		$old_stable_tag   = $import_context['old_stable_tag'];
		$changed_svn_tags = array_map( 'strval', $import_context['changed_svn_tags'] );
		$release_ref      = trim( $stable_tag ) ?: 'trunk';

		// Trunk-only commits should not rescan a tag-based stable ZIP that was not rebuilt.
		if ( $stable_tag === $old_stable_tag && ! in_array( $release_ref, $changed_svn_tags, true ) ) {
			return false;
		}

		// Version is post-import state; without it, the ZIP identity is not reliable.
		$version = get_post_meta( $plugin->ID, 'version', true );
		if ( ! $version ) {
			return false;
		}

		$previous_release_ref = get_post_meta( $plugin->ID, 'last_stable_tag', true ) ?: null;
		$previous_version     = get_post_meta( $plugin->ID, 'last_version', true ) ?: null;
		$previous_zip_url     = null;

		if ( $previous_release_ref && $previous_release_ref !== $release_ref && 'trunk' !== $previous_release_ref ) {
			$previous_zip_url = Template::download_link( $plugin, $previous_release_ref );
		}

		return self::dispatch(
			$plugin,
			[
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
			]
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

		$pending = get_post_meta( $plugin->ID, self::PENDING_META_KEY, true ) ?: [];
		foreach ( $pending as $scan_id => $record ) {
			if ( ! is_array( $record ) || ( $record['requested_at'] ?? 0 ) < time() - DAY_IN_SECONDS ) {
				unset( $pending[ $scan_id ] );
			}
		}

		$pending[ $request_data['scan_id'] ] = [
			'version'      => $request_data['version'],
			'release_ref'  => $request_data['release_ref'],
			'requested_at' => $request_data['requested_at'],
		];
		update_post_meta( $plugin->ID, self::PENDING_META_KEY, $pending );

		$response = wp_safe_remote_post(
			self::ENDPOINT,
			[
				'timeout'    => 15,
				'user-agent' => 'WordPress.org Plugin Directory Gandalf Scan',
				'headers'    => [
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . WP_GANDALF_SCAN_SHARED_SECRET,
					'Content-Type'  => 'application/json',
				],
				'body'       => wp_json_encode( $request_data ),
			]
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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Routed to the error log via E_USER_NOTICE; raw is fine.
		trigger_error( sprintf( 'Dispatched Gandalf scan %s for %s.', $request_data['scan_id'], $plugin->post_name ), E_USER_NOTICE );
		return true;
	}

	/**
	 * Handle a completed or failed scan callback.
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $data   The Gandalf callback data.
	 * @return true|WP_Error True on success, or an error when the scan is unknown.
	 */
	public static function handle_callback( $plugin, $data ) {
		$scan_id = $data['scan_id'] ?? '';
		$pending = get_post_meta( $plugin->ID, self::PENDING_META_KEY, true ) ?: [];

		if ( empty( $pending[ $scan_id ] ) ) {
			$error = new WP_Error( 'unknown_gandalf_scan', 'Unknown Gandalf scan_id.', [ 'status' => WP_Http::BAD_REQUEST ] );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		$pending_record = $pending[ $scan_id ];

		if ( ( $data['version'] ?? null ) !== $pending_record['version'] || ( $data['release_ref'] ?? null ) !== $pending_record['release_ref'] ) {
			$error = new WP_Error( 'invalid_gandalf_scan', 'Gandalf callback does not match the pending scan.', [ 'status' => WP_Http::BAD_REQUEST ] );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		if ( 'completed' === ( $data['status'] ?? '' ) ) {
			if ( ( $data['findings_count'] ?? 0 ) > 0 ) {
				self::notify_slack(
					$plugin,
					[
						'version'         => $pending_record['version'],
						'release_ref'     => $pending_record['release_ref'],
						'findings_count'  => $data['findings_count'],
						'severity_counts' => $data['severity_counts'] ?? [],
						'verdict_hash'    => $data['verdict_hash'] ?? '',
						'report_url'      => $data['report_url'] ?? '',
					]
				);
			}
		} else {
			self::record_last_error( $plugin, $data['error']['kind'] ?? 'unknown', $data['error']['message'] ?? '', $scan_id );
		}

		unset( $pending[ $scan_id ] );
		update_post_meta( $plugin->ID, self::PENDING_META_KEY, $pending );

		return true;
	}

	/**
	 * Record a valid-secret callback that failed validation.
	 *
	 * @param \WP_Post $plugin  The plugin post.
	 * @param WP_Error $error   The validation error.
	 * @param string   $scan_id Optional scan ID.
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

		$pending = get_post_meta( $plugin->ID, self::PENDING_META_KEY, true ) ?: [];
		unset( $pending[ $scan_id ] );
		update_post_meta( $plugin->ID, self::PENDING_META_KEY, $pending );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Routed to the error log via E_USER_NOTICE; raw is fine.
		trigger_error( sprintf( 'Failed to dispatch Gandalf scan for %s: %s', $plugin->post_name, $message ), E_USER_NOTICE );
		return false;
	}

	/**
	 * Notify Slack about a Gandalf scan with findings.
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $record The completed scan summary.
	 */
	protected static function notify_slack( $plugin, $record ) {
		if ( empty( $record['verdict_hash'] ) ) {
			return;
		}

		$already_notified = get_post_meta( $plugin->ID, self::NOTIFIED_META_KEY, true ) ?: [];
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

		$title = $plugin->post_title;
		if ( 'closed' === $plugin->post_status ) {
			$title .= ' (closed)';
		}

		$body = sprintf(
			"Gandalf scan detected findings in *%s*\n%s\nVersion: %s (%s)\nFindings: %d\n",
			$title,
			$install_line,
			$record['version'],
			$record['release_ref'],
			$record['findings_count']
		);

		if ( ! empty( $record['severity_counts'] ) ) {
			$severity_summary = [];
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
			[
				'kind'        => sanitize_key( $kind ),
				'message'     => sanitize_text_field( $message ),
				'scan_id'     => sanitize_text_field( $scan_id ),
				'recorded_at' => time(),
			]
		);
	}
}
