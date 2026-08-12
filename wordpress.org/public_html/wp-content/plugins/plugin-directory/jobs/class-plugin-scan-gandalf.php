<?php
/**
 * Gandalf scan integration for plugin updates.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */

namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Email\Security_Scan_Findings;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WP_Error;
use WP_Http;

/**
 * Sends plugin updates to Gandalf for security scans and acts on the results.
 *
 * Completed scans whose maximum risk score reaches the block threshold hold
 * the scanned release out of the update API — the previously served version
 * keeps being served.
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

	/** Consumed callbacks keyed by scan_id, to acknowledge retries without repeating effects. */
	const CONSUMED_META_KEY = '_gandalf_scan_consumed';

	/** Verdict hashes already emailed to the plugin committers, to avoid duplicate emails. */
	const EMAILED_META_KEY = '_gandalf_scan_emailed';

	/** Completed scans with a max risk score at or above this have their release blocked. */
	const BLOCK_RISK_SCORE = PHP_FLOAT_MAX;

	/** Completed scans with a max risk score at or above this have their committers emailed. */
	const NOTIFY_RISK_SCORE = self::BLOCK_RISK_SCORE;

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

		// A blocked or cooling-down release never reaches `update_source`, so diffing against the served release keeps a re-tagged payload from becoming its own baseline.
		$served = API_Update_Updater::get_served_release( $plugin->post_name );
		if ( $served && substr( $version, 0, 128 ) !== $served->version ) {
			$previous_release_ref = $served->stable_tag ?: null;
			$previous_version     = $served->version ?: null;
		}

		// A blocked release must not be the baseline either way — dropping it makes the scanner run a full scan.
		if ( $previous_release_ref && API_Update_Updater::is_release_blocked( Plugin_Directory::get_release( $plugin, $previous_release_ref ) ) ) {
			$previous_release_ref = null;
			$previous_version     = null;
		}

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
	 * @param array    $data   The security scan callback data, validated by the route.
	 * @return true|WP_Error True on success, or an error when the callback is invalid.
	 */
	public static function handle_callback( $plugin, $data ) {
		// Serialize per plugin: callbacks read-modify-write shared per-plugin meta.
		if ( ! wp_cache_add( 'gandalf-scan-callback-' . $plugin->ID, 1, 'plugin-scans', 5 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'security_scan_locked', 'A security scan callback for this plugin is already being processed.', [ 'status' => WP_Http::CONFLICT ] );
		}

		try {
			return self::consume_callback( $plugin, $data );
		} finally {
			wp_cache_delete( 'gandalf-scan-callback-' . $plugin->ID, 'plugin-scans' );
		}
	}

	/**
	 * Consume a validated callback exactly once.
	 *
	 * Runs under the per-plugin lock taken by handle_callback().
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $data   The validated security scan callback data.
	 * @return true|WP_Error True on success, or an error when the callback is invalid.
	 */
	protected static function consume_callback( $plugin, $data ) {
		$scan_id  = $data['scan_id'];
		$digest   = self::callback_digest( $data );
		$consumed = get_post_meta( $plugin->ID, self::CONSUMED_META_KEY, true ) ?: [];
		foreach ( $consumed as $consumed_scan_id => $consumed_record ) {
			if ( ! is_array( $consumed_record ) || ( $consumed_record['time'] ?? 0 ) < time() - WEEK_IN_SECONDS ) {
				unset( $consumed[ $consumed_scan_id ] );
			}
		}

		if ( isset( $consumed[ $scan_id ] ) ) {
			// Identical retry of a consumed callback: acknowledge without repeating effects.
			if ( hash_equals( $consumed[ $scan_id ]['digest'], $digest ) ) {
				return true;
			}

			// Only a completed verdict may supersede a consumed failure report for the same scan.
			if ( 'completed' !== $data['status'] || 'failed' !== ( $consumed[ $scan_id ]['status'] ?? '' ) ) {
				$error = new WP_Error( 'security_scan_conflict', 'A different security scan callback was already consumed for this scan.', [ 'status' => WP_Http::CONFLICT ] );
				self::record_invalid_callback( $plugin, $error, $scan_id );
				return $error;
			}
		}

		$pending = get_post_meta( $plugin->ID, self::PENDING_META_KEY, true ) ?: [];

		if ( empty( $pending[ $scan_id ] ) ) {
			$error = new WP_Error( 'unknown_gandalf_scan', 'Unknown security scan.', [ 'status' => WP_Http::BAD_REQUEST ] );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		$pending_record = $pending[ $scan_id ];

		if ( $data['version'] !== $pending_record['version'] || $data['release_ref'] !== $pending_record['release_ref'] ) {
			$error = new WP_Error( 'invalid_gandalf_scan', 'Security scan callback does not match the pending scan.', [ 'status' => WP_Http::BAD_REQUEST ] );
			self::record_invalid_callback( $plugin, $error, $scan_id );
			return $error;
		}

		if ( 'completed' === $data['status'] ) {
			$record = [
				'scan_id'         => $scan_id,
				'version'         => $pending_record['version'],
				'release_ref'     => $pending_record['release_ref'],
				'completed_at'    => $data['completed_at'] ?? time(),
				'verdict_hash'    => $data['verdict_hash'],
				'findings_count'  => $data['findings_count'],
				'severity_counts' => $data['severity_counts'],
				'max_risk_score'  => (float) $data['max_risk_score'],
				'report_url'      => $data['report_url'],
				'action'          => 'advisory',
				'findings'        => $data['findings'],
			];

			/**
			 * Filters the risk score at which a completed security scan blocks the release.
			 *
			 * @param float    $threshold The block threshold, from 0 to 10.
			 * @param \WP_Post $plugin    The plugin post.
			 */
			$threshold = (float) apply_filters( 'wporg_plugins_security_scan_block_risk_score', self::BLOCK_RISK_SCORE, $plugin );

			if ( $record['max_risk_score'] >= $threshold && self::block_release( $plugin, $record ) ) {
				$record['action'] = 'blocked';

				self::record_review_note( $plugin, $record );
			}

			if ( $record['findings_count'] > 0 || 'advisory' !== $record['action'] ) {
				self::notify_slack( $plugin, $record );
			}

			self::notify_committers( $plugin, $record, $data['findings'] );
		} else {
			self::record_last_error( $plugin, $data['error']['kind'], $data['error']['message'], $scan_id );
		}

		// Recorded after the effects, so a crashed delivery is retried, not acknowledged.
		$consumed[ $scan_id ] = [
			'digest' => $digest,
			'status' => $data['status'],
			'time'   => time(),
		];
		update_post_meta( $plugin->ID, self::CONSUMED_META_KEY, $consumed );

		// A failed report keeps the pending entry for a completed verdict that may still arrive.
		if ( 'completed' === $data['status'] ) {
			unset( $pending[ $scan_id ] );
			update_post_meta( $plugin->ID, self::PENDING_META_KEY, $pending );
		}

		return true;
	}

	/**
	 * Return a canonical digest of a callback body.
	 *
	 * Keys are sorted recursively so a scanner retry that re-marshals the same
	 * data with a different key order still matches its consumed record.
	 *
	 * @param array $data The validated security scan callback data.
	 * @return string A sha256 hex digest.
	 */
	protected static function callback_digest( $data ) {
		self::ksort_deep( $data );
		return hash( 'sha256', wp_json_encode( $data ) );
	}

	/**
	 * Recursively sort an array by key. Sequential lists are unaffected.
	 *
	 * @param array $data The array to sort, by reference.
	 */
	protected static function ksort_deep( &$data ) {
		ksort( $data );
		foreach ( $data as &$value ) {
			if ( is_array( $value ) ) {
				self::ksort_deep( $value );
			}
		}
		unset( $value );
	}

	/**
	 * Block the scanned release, once the verdict is known to still apply to it.
	 *
	 * A verdict for a release that is no longer the plugin's current one, or
	 * that is already being served, can't un-ship anything — blocking is
	 * refused and the result stays advisory.
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $record The completed scan record.
	 * @return bool Whether the release was blocked.
	 */
	protected static function block_release( $plugin, $record ) {
		// Whether the verdict still applies turns on the scanned tag, never the version header an author can rename inside it.
		$current     = API_Update_Updater::get_current_release( $plugin );
		$scanned_tag = 'trunk' === $record['release_ref'] ? 'trunk@' . $record['version'] : $record['release_ref'];

		if ( ! $current || (string) ( $current['tag'] ?? '' ) !== (string) $scanned_tag ) {
			return false;
		}

		return API_Update_Updater::block_release(
			$plugin->post_name,
			[
				'scan_id'    => $record['scan_id'],
				'risk_score' => $record['max_risk_score'],
			]
		);
	}

	/**
	 * Leave an internal note with the scan findings for the plugin review team.
	 *
	 * @param \WP_Post $plugin The plugin post.
	 * @param array    $record The completed scan record.
	 */
	protected static function record_review_note( $plugin, $record ) {
		$note = sprintf(
			'Automatically blocked version %s (%s) from being served: security scan %s reported a maximum risk score of %s. Force-release to serve it.',
			esc_html( $record['version'] ),
			esc_html( $record['release_ref'] ),
			esc_html( $record['scan_id'] ),
			esc_html( number_format( (float) $record['max_risk_score'], 1 ) )
		);

		$note .= '<br><br>Findings:';
		foreach ( self::top_findings( $record['findings'], 10 ) as $finding ) {
			$note .= sprintf(
				'<br>&#8226; <strong>%s</strong> &mdash; %s',
				esc_html( number_format( (float) $finding['risk_score'], 1 ) ),
				esc_html( self::excerpt( $finding['title'] ?? '', 200 ) )
			);

			if ( ! empty( $finding['file_path'] ) ) {
				$note .= sprintf(
					'<br>&nbsp;&nbsp;%s%s',
					esc_html( $finding['file_path'] ),
					empty( $finding['line'] ) ? '' : ':' . (int) $finding['line']
				);
			}

			// The contract only requires a finding's risk_score; read the rest defensively.
			$investigation = $finding['investigation'] ?? [];
			if ( 'completed' === ( $investigation['status'] ?? '' ) && in_array( $investigation['result'] ?? '', [ 'reproduced', 'conditional' ], true ) ) {
				$note .= sprintf(
					'<br>&nbsp;&nbsp;Investigation (%s): %s',
					esc_html( $investigation['result'] ),
					esc_html( self::excerpt( $investigation['summary'] ?? '', 200 ) )
				);
			}
		}

		$note .= '<br><br>Report: ' . esc_url( $record['report_url'] );

		$wordpressdotorg = get_user_by( 'slug', 'wordpressdotorg' );

		// wp_insert_comment() unslashes; slash so backslashes in finding strings survive.
		Tools::audit_log( wp_slash( $note ), $plugin, $wordpressdotorg ? $wordpressdotorg : false );
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
	 * @param array    $record The completed scan record.
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

		// Release blocks always alert; only advisory results deduplicate.
		if ( 'advisory' === $record['action'] && isset( $already_notified[ $record['verdict_hash'] ] ) ) {
			update_post_meta( $plugin->ID, self::NOTIFIED_META_KEY, $already_notified );
			return;
		}

		$already_notified[ $record['verdict_hash'] ] = time();
		update_post_meta( $plugin->ID, self::NOTIFIED_META_KEY, $already_notified );

		if ( ! defined( 'PLUGIN_REVIEW_ALERT_SLACK_CHANNEL' ) || ! function_exists( 'slack_dm' ) ) {
			return;
		}

		$active_installs = (int) get_post_meta( $plugin->ID, 'active_installs', true );
		$install_text    = sprintf( '%s+ active installs', number_format_i18n( $active_installs ) );
		if ( $active_installs >= 10000 ) {
			$install_text = "*{$install_text}*";
		}

		// Post titles are stored entity-encoded; the header block is plain text, so only decode.
		$title = html_entity_decode( $plugin->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( 'closed' === $plugin->post_status ) {
			$title .= ' (closed)';
		}

		$findings_count = (int) $record['findings_count'];
		$top_findings   = self::top_findings( $record['findings'], 5 );

		$meta_links = sprintf(
			'<https://wordpress.org/plugins/wp-admin/post.php?post=%d&action=edit|wp-admin> · <https://wordpress.org/plugins/%s/|Plugin page>',
			$plugin->ID,
			$plugin->post_name
		);
		if ( $record['release_ref'] !== $record['version'] ) {
			// Escaping &, <, and > neutralizes Slack control sequences like <!channel> in untrusted strings.
			$meta_links .= ' · ' . htmlspecialchars( $record['release_ref'], ENT_NOQUOTES );
		}

		$summary_text = sprintf( '%s · %s', 1 === $findings_count ? '*1 finding*' : "*{$findings_count} findings*", $install_text );
		if ( isset( $record['max_risk_score'] ) ) {
			$summary_text .= sprintf( ' · max risk %s', number_format( (float) $record['max_risk_score'], 1 ) );
		}

		$summary = [
			'type' => 'section',
			'text' => [
				'type' => 'mrkdwn',
				'text' => $summary_text,
			],
		];

		$report_url = esc_url_raw( $record['report_url'] ?? '' );
		if ( $report_url ) {
			$summary['accessory'] = [
				'type'  => 'button',
				'text'  => [
					'type' => 'plain_text',
					'text' => 'View report',
				],
				'url'   => $report_url,
				'style' => 'primary',
			];
		}

		$blocks = [
			[
				'type' => 'header',
				'text' => [
					'type' => 'plain_text',
					'text' => self::excerpt( trim( $title . ' ' . $record['version'] ), 150 ),
				],
			],
		];

		if ( 'blocked' === $record['action'] ) {
			$blocks[] = [
				'type' => 'section',
				'text' => [
					'type' => 'mrkdwn',
					'text' => ':rotating_light: *Automatically blocked from release.*',
				],
			];
		}

		$blocks[] = $summary;
		$blocks[] = [
			'type'     => 'context',
			'elements' => [
				[
					'type' => 'mrkdwn',
					'text' => $meta_links,
				],
			],
		];

		$attachments = [];
		foreach ( $top_findings as $finding ) {
			$risk_score = (float) ( $finding['risk_score'] ?? 0 );

			$attachment = [
				'color'  => self::risk_color( $risk_score ),
				'blocks' => [
					[
						'type' => 'section',
						'text' => [
							'type' => 'mrkdwn',
							'text' => sprintf(
								'*%s* — %s',
								number_format( $risk_score, 1 ),
								htmlspecialchars( self::excerpt( $finding['title'] ?? '', 150 ), ENT_NOQUOTES )
							),
						],
					],
				],
			];

			if ( ! empty( $finding['file_path'] ) ) {
				$attachment['blocks'][] = [
					'type'     => 'context',
					'elements' => [
						[
							'type' => 'mrkdwn',
							'text' => 'File: ' . self::file_link( $plugin, $record['release_ref'], $finding['file_path'], (int) ( $finding['line'] ?? 0 ) ),
						],
					],
				];
			}

			$attachments[] = $attachment;
		}

		if ( 'blocked' === $record['action'] ) {
			$fallback = sprintf(
				'Security scan automatically blocked %s %s from release',
				htmlspecialchars( $title, ENT_NOQUOTES ),
				htmlspecialchars( $record['version'], ENT_NOQUOTES )
			);
		} else {
			$fallback = sprintf(
				'Security scan found %s in %s %s',
				1 === $findings_count ? '1 finding' : "{$findings_count} findings",
				htmlspecialchars( $title, ENT_NOQUOTES ),
				htmlspecialchars( $record['version'], ENT_NOQUOTES )
			);
		}
		if ( isset( $record['max_risk_score'] ) ) {
			$fallback .= sprintf( ' (max risk %s)', number_format( (float) $record['max_risk_score'], 1 ) );
		}

		slack_dm(
			[
				'text'        => $fallback,
				'username'    => 'Gandalf',
				'blocks'      => $blocks,
				'attachments' => $attachments,
			],
			PLUGIN_REVIEW_ALERT_SLACK_CHANNEL,
			true
		);
	}

	/**
	 * Email the plugin committers about a completed scan's findings.
	 *
	 * @param \WP_Post $plugin   The plugin post.
	 * @param array    $record   The completed scan record.
	 * @param array    $findings The reported findings, with code snippets and explanations intact.
	 */
	protected static function notify_committers( $plugin, $record, $findings ) {
		if ( empty( $record['verdict_hash'] ) ) {
			return;
		}

		/**
		 * Filters the risk score at which a completed security scan emails the plugin committers.
		 *
		 * @param float    $threshold The notification threshold, from 0 to 10. Above 10 disables the emails.
		 * @param \WP_Post $plugin    The plugin post.
		 */
		$threshold = (float) apply_filters( 'wporg_plugins_security_scan_notify_risk_score', self::NOTIFY_RISK_SCORE, $plugin );

		if ( $record['max_risk_score'] < $threshold ) {
			return;
		}

		$already_emailed = get_post_meta( $plugin->ID, self::EMAILED_META_KEY, true ) ?: [];
		foreach ( $already_emailed as $hash => $time ) {
			if ( $time < time() - MONTH_IN_SECONDS ) {
				unset( $already_emailed[ $hash ] );
			}
		}

		// Release blocks always email; only advisory results deduplicate.
		if ( 'advisory' === $record['action'] && isset( $already_emailed[ $record['verdict_hash'] ] ) ) {
			update_post_meta( $plugin->ID, self::EMAILED_META_KEY, $already_emailed );
			return;
		}

		$committers = array_diff(
			Tools::get_plugin_committers( $plugin ),
			$GLOBALS['bot_accounts'] ?? [],
			$GLOBALS['nologin_accounts'] ?? []
		);
		if ( ! $committers ) {
			return;
		}

		$already_emailed[ $record['verdict_hash'] ] = time();
		update_post_meta( $plugin->ID, self::EMAILED_META_KEY, $already_emailed );

		// The stored record's findings are stripped for the meta row; the email gets them intact.
		$record['findings'] = self::top_findings( $findings, 10 );

		$email = new Security_Scan_Findings(
			$plugin,
			$committers,
			[
				'record' => $record,
				'who'    => 'WordPress.org',
			]
		);
		$email->send();
	}

	/**
	 * Return the attachment bar color for a risk score.
	 *
	 * @param float $risk_score The finding's risk score, 0-10.
	 * @return string A hex color.
	 */
	protected static function risk_color( $risk_score ) {
		if ( $risk_score >= 9 ) {
			return '#D0342C';
		}

		if ( $risk_score >= 6 ) {
			return '#E8912D';
		}

		if ( $risk_score >= 4 ) {
			return '#ECB22E';
		}

		return '#808080';
	}

	/**
	 * Return a Slack link to the finding's file in the plugins Trac browser.
	 *
	 * @param \WP_Post $plugin      The plugin post.
	 * @param string   $release_ref The scanned release ref.
	 * @param string   $file_path   The file path, relative to the plugin root.
	 * @param int      $line        The line number, or 0 for none.
	 * @return string The Slack-formatted link.
	 */
	protected static function file_link( $plugin, $release_ref, $file_path, $line ) {
		$file_path = ltrim( (string) $file_path, '/' );

		// URL-encoding the untrusted path segments also keeps | and > from breaking the link syntax.
		$url = sprintf(
			'https://plugins.trac.wordpress.org/browser/%s/%s/%s',
			$plugin->post_name,
			'trunk' === $release_ref ? 'trunk' : 'tags/' . rawurlencode( $release_ref ),
			implode( '/', array_map( 'rawurlencode', explode( '/', $file_path ) ) )
		);

		$label = $file_path;
		if ( $line ) {
			$url   .= '#L' . $line;
			$label .= ':' . $line;
		}

		return sprintf( '<%s|%s>', $url, htmlspecialchars( $label, ENT_NOQUOTES ) );
	}

	/**
	 * Return the highest-risk findings first, bounded for display.
	 *
	 * @param array $findings The scan findings.
	 * @param int   $limit    Maximum number of findings to return.
	 * @return array The highest-risk findings.
	 */
	protected static function top_findings( $findings, $limit ) {
		usort(
			$findings,
			static function ( $a, $b ) {
				return $b['risk_score'] <=> $a['risk_score'];
			}
		);

		return array_slice( $findings, 0, $limit );
	}

	/**
	 * Collapse untrusted text onto a single bounded line.
	 *
	 * @param string $text   The text to excerpt.
	 * @param int    $length Maximum length in characters.
	 * @return string The excerpted text.
	 */
	protected static function excerpt( $text, $length ) {
		return mb_strimwidth( preg_replace( '/\s+/u', ' ', trim( (string) $text ) ), 0, $length, '…' );
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
