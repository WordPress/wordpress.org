<?php
/**
 * Advisory Gandalf scan integration for plugin updates.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */

namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
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

	/** Risk score at or above which a completed scan blocks the release from being served. */
	const RISK_SCORE_BLOCK_THRESHOLD = 8;

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
		$scan_id = $data['scan_id'];
		$pending = get_post_meta( $plugin->ID, self::PENDING_META_KEY, true ) ?: [];

		if ( empty( $pending[ $scan_id ] ) ) {
			$error = new WP_Error( 'unknown_gandalf_scan', 'Unknown Gandalf scan_id.', [ 'status' => WP_Http::BAD_REQUEST ] );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		$pending_record = $pending[ $scan_id ];

		if ( $data['version'] !== $pending_record['version'] || $data['release_ref'] !== $pending_record['release_ref'] ) {
			$error = new WP_Error( 'invalid_gandalf_scan', 'Gandalf callback does not match the pending scan.', [ 'status' => WP_Http::BAD_REQUEST ] );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		if ( 'completed' === $data['status'] ) {
			/*
			 * Precedence: a high enough risk score blocks the release outright; otherwise findings
			 * raise the usual advisory alert. `risk_score` is read defensively because the callback
			 * route doesn't validate it, and because Gandalf doesn't send it yet.
			 */
			$risk_score = ( isset( $data['risk_score'] ) && is_numeric( $data['risk_score'] ) ) ? (float) $data['risk_score'] : null;

			if ( null !== $risk_score && $risk_score >= self::RISK_SCORE_BLOCK_THRESHOLD ) {
				$held = self::block_release( $plugin, $pending_record, $scan_id, $risk_score );
				self::notify_slack_blocked(
					$plugin,
					[
						'version'     => $pending_record['version'],
						'release_ref' => $pending_record['release_ref'],
						'risk_score'  => $risk_score,
						'held'        => $held,
						'report_url'  => $data['report_url'] ?? '',
					]
				);
			} elseif ( $data['findings_count'] > 0 ) {
				self::notify_slack_findings(
					$plugin,
					[
						'version'         => $pending_record['version'],
						'release_ref'     => $pending_record['release_ref'],
						'findings_count'  => $data['findings_count'],
						'severity_counts' => $data['severity_counts'],
						'verdict_hash'    => $data['verdict_hash'],
						'report_url'      => $data['report_url'],
					]
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
	 * Record a block on the scanned release and re-run the update to hold the version back.
	 *
	 * @param \WP_Post $plugin         The plugin post.
	 * @param array    $pending_record The pending scan record (version, release_ref).
	 * @param string   $scan_id        The Gandalf scan ID.
	 * @param float    $risk_score     The reported risk score.
	 * @return bool True when the release was held, false when there was nothing to hold.
	 */
	protected static function block_release( $plugin, $pending_record, $scan_id, $risk_score ) {
		$version = (string) $pending_record['version'];

		// A newer release landed since this scan was dispatched; the scanned version is moot.
		if ( (string) get_post_meta( $plugin->ID, 'version', true ) !== $version ) {
			return false;
		}

		$release = Plugin_Directory::get_release( $plugin, $version );
		if ( ! $release ) {
			return false;
		}

		// No cooldown was captured at release creation, so the version was served at import.
		if ( empty( $release['release_delay'] ) ) {
			return false;
		}

		// Already live: the cooldown elapsed before this verdict arrived.
		if ( API_Update_Updater::get_served_version( $plugin->post_name ) === $version ) {
			return false;
		}

		Plugin_Directory::add_release(
			$plugin,
			[
				'tag'           => $release['tag'],
				'release_block' => [
					'scan_id'    => $scan_id,
					'risk_score' => $risk_score,
					'blocked_at' => time(),
				],
			]
		);

		// Re-run so a version scheduled to serve at cooldown-end is held now instead.
		API_Update_Updater::update_single_plugin( $plugin->post_name );

		Tools::audit_log(
			sprintf(
				'A security scan blocked version %1$s from being served (risk score %2$s).',
				$version,
				$risk_score
			),
			$plugin
		);

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
	 * Alert Slack about a scan that reported findings.
	 *
	 * Dedupes on the verdict hash so an unchanged result isn't reported twice, and is skipped
	 * when no hash is present, since there's nothing to dedupe on.
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $record 'version', 'release_ref', 'findings_count', 'severity_counts',
	 *                         'verdict_hash', 'report_url'.
	 */
	protected static function notify_slack_findings( $plugin, $record ) {
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

		$detail = [ sprintf( 'Findings: %d', $record['findings_count'] ) ];

		$severity_summary = [];
		foreach ( (array) ( $record['severity_counts'] ?? [] ) as $severity => $count ) {
			if ( $count > 0 ) {
				$severity_summary[] = "{$severity}: {$count}";
			}
		}
		if ( $severity_summary ) {
			$detail[] = 'Severity: ' . implode( ', ', $severity_summary );
		}

		self::send_slack_alert(
			$plugin,
			'A security scan detected findings in *%s*',
			$record['version'],
			$record['release_ref'],
			$detail,
			$record['report_url']
		);
	}

	/**
	 * Alert Slack about a high-risk verdict. Always sends: a block needs a human either way.
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $record 'version', 'release_ref', 'risk_score', 'report_url', and 'held'
	 *                         (whether the release was held, or was already live).
	 */
	protected static function notify_slack_blocked( $plugin, $record ) {
		if ( ! empty( $record['held'] ) ) {
			$headline = 'A security scan *blocked* a release of *%s*';
			$status   = 'Held out of the update API until a reviewer force-releases it.';
		} else {
			$headline = 'A security scan flagged an *already-served* release of *%s*';
			$status   = 'The release delay had already elapsed, so this version is live. Manual action (close or roll back) may be required.';
		}

		self::send_slack_alert(
			$plugin,
			$headline,
			$record['version'],
			$record['release_ref'],
			[
				sprintf( 'Risk score: %s (blocks at %s)', $record['risk_score'], self::RISK_SCORE_BLOCK_THRESHOLD ),
				$status,
			],
			$record['report_url']
		);
	}

	/**
	 * Send a plugin-review Slack alert: the shared envelope for the scan notifications — the
	 * plugin title, active-install count, version line, and links.
	 *
	 * @param \WP_Post $plugin      The plugin post.
	 * @param string   $headline    A sprintf format with a single %s for the plugin title.
	 * @param string   $version     The scanned version.
	 * @param string   $release_ref The scanned release ref.
	 * @param string[] $detail      Lines describing the result, placed after the version line.
	 * @param string   $report_url  Link to the scan report, or '' when there isn't one.
	 */
	private static function send_slack_alert( $plugin, $headline, $version, $release_ref, $detail, $report_url ) {
		if ( ! defined( 'PLUGIN_REVIEW_ALERT_SLACK_CHANNEL' ) || ! function_exists( 'slack_dm' ) ) {
			return;
		}

		$title = $plugin->post_title;
		if ( 'closed' === $plugin->post_status ) {
			$title .= ' (closed)';
		}

		$active_installs = (int) get_post_meta( $plugin->ID, 'active_installs', true );
		$install_line    = sprintf( '%s+ active installs', number_format_i18n( $active_installs ) );
		if ( $active_installs >= 10000 ) {
			$install_line = ":warning: {$install_line}";
		}

		$body  = sprintf( $headline, $title ) . "\n";
		$body .= $install_line . "\n";
		$body .= sprintf( "Version: %s (%s)\n", $version, $release_ref );
		$body .= implode( "\n", $detail ) . "\n";
		$body .= sprintf( "Details: https://wordpress.org/plugins/wp-admin/post.php?post=%s&action=edit\n", $plugin->ID );
		$body .= sprintf( "Plugin: https://wordpress.org/plugins/%s/\n", $plugin->post_name );
		if ( ! empty( $report_url ) ) {
			$body .= sprintf( "Report: %s\n", $report_url );
		}

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
