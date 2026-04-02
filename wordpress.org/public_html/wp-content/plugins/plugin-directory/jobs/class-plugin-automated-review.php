<?php
/**
 * Automated plugin review job.
 *
 * Runs a review loop: triage → batch file reviews → synthesis.
 * Small plugins naturally produce a single batch; larger ones get more.
 * Guidelines are fetched live from DevHub; security checklists and review
 * rules are bundled.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;

/**
 * Handles automated plugin reviews using the WordPress AI Client API.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Automated_Review {

	/**
	 * Target size in bytes for each review batch.
	 */
	const BATCH_SIZE_TARGET = 512 * KB_IN_BYTES;

	/*
	 * -------------------------------------------------------------------------
	 * Entry Points
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Queue an automated review job for a plugin.
	 *
	 * @param array    $plugin_data The plugin data array.
	 * @param \WP_Post $plugin_post The plugin post object.
	 */
	public static function queue( array $plugin_data, \WP_Post $plugin_post ): void {
		if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			return;
		}

		$hook = 'automated_review:' . $plugin_post->post_name;

		if ( ! wp_next_scheduled( $hook, array( $plugin_post->post_name ) ) ) {
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, $hook, array( $plugin_post->post_name ) );
		}
	}

	/**
	 * Cron callback to run the automated review.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return array|false The parsed review results, or false on failure.
	 */
	public static function cron_trigger( string $plugin_slug ): array|false {
		if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			return false;
		}

		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $plugin ) {
			return false;
		}

		$attachments = get_attached_media( 'application/zip', $plugin );
		if ( ! $attachments ) {
			Tools::audit_log( 'Automated review failed: No ZIP attachment found.', $plugin );
			return false;
		}

		$attachment = $attachments[ max( array_keys( $attachments ) ) ];
		$plugin_dir = Filesystem::unzip( get_attached_file( $attachment->ID ) );
		if ( ! $plugin_dir ) {
			Tools::audit_log( 'Automated review failed: Could not extract plugin files.', $plugin );
			return false;
		}

		$files       = self::collect_files( $plugin_dir );
		$pcp_results = self::get_pcp_results( $attachment );

		$outcome = self::run_review( $plugin, $plugin_dir, $files['source'], $files['all'], $pcp_results );

		if ( ! $outcome || ! isset( $outcome['review']['verdict'] ) ) {
			Tools::audit_log( 'Automated review failed: No valid result produced.', $plugin );
			return false;
		}

		update_post_meta( $plugin->ID, '_automated_review_results', $outcome['review'] );
		update_post_meta( $plugin->ID, '_automated_review_timestamp', time() );

		if ( ! empty( $outcome['token_usage'] ) ) {
			update_post_meta( $plugin->ID, '_automated_review_token_usage', $outcome['token_usage'] );
		}

		Tools::audit_log( self::format_as_note( $outcome['review'] ), $plugin );

		return $outcome['review'];
	}

	/**
	 * AJAX handler for on-demand automated review.
	 */
	public static function ajax_run_review(): void {
		$plugin_slug = sanitize_text_field( wp_unslash( $_REQUEST['slug'] ?? '' ) );
		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( 'Missing plugin slug.' );
		}

		check_ajax_referer( 'wporg_plugins_automated_review-' . $plugin_slug );

		if ( ! current_user_can( 'plugin_review' ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $plugin ) {
			wp_send_json_error( 'Plugin not found.' );
		}

		// Allow synchronous execution for testing: append &sync=1 to the request.
		if ( ! empty( $_REQUEST['sync'] ) ) {
			$result = self::cron_trigger( $plugin_slug );

			if ( false === $result ) {
				wp_send_json_error( 'Automated review failed. Check internal notes for details.' );
			}

			wp_send_json_success( $result );
		}

		if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			wp_send_json_error( 'AI support is not available.' );
		}

		$hook = 'automated_review:' . $plugin_slug;

		if ( wp_next_scheduled( $hook, array( $plugin_slug ) ) ) {
			wp_send_json_success( 'Automated review is already queued.' );
		}

		wp_schedule_single_event( time() + 5, $hook, array( $plugin_slug ) );

		wp_send_json_success( 'Automated review queued. Results will appear in Internal Notes.' );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Review Loop: Triage → Batch Reviews → Synthesis
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Run the review loop.
	 *
	 * @param \WP_Post $plugin       The plugin post.
	 * @param string   $plugin_dir   Path to extracted plugin.
	 * @param array    $source_files Collected source files with path/size/ext.
	 * @param string[] $all_files    All file paths.
	 * @param array    $pcp_results  PCP results from attachment.
	 * @return array|false Array with 'review' and 'token_usage', or false.
	 */
	protected static function run_review( \WP_Post $plugin, string $plugin_dir, array $source_files, array $all_files, array $pcp_results ): array|false {
		$start_time  = microtime( true );
		$token_usage = array(
			'prompt'     => 0,
			'completion' => 0,
			'total'      => 0,
		);

		$total_size       = array_sum( array_column( $source_files, 'size' ) );
		$expected_batches = max( 1, (int) ceil( $total_size / self::BATCH_SIZE_TARGET ) );
		$total_timeout    = min( 30 * MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS + $expected_batches * 3 * MINUTE_IN_SECONDS );

		$readme_content = self::find_readme_content( $plugin_dir, $source_files );
		$boundary       = wp_generate_password( 8, false );

		// Phase 1: Triage.
		$triage_result = self::run_triage( $source_files, $all_files, self::summarize_pcp_results( $pcp_results ), $readme_content, $boundary );

		if ( ! is_wp_error( $triage_result ) && isset( $triage_result['data'] ) ) {
			self::accumulate_tokens( $token_usage, $triage_result );
			$triage = $triage_result['data'];

			$triage['file_priorities'] = self::normalize_file_priorities( $triage['file_priorities'] ?? array() );
		} else {
			$error_msg = is_wp_error( $triage_result ) ? $triage_result->get_error_message() : 'Invalid response';
			Tools::audit_log( sprintf( 'Automated review: Triage failed: %s — using heuristic fallback.', $error_msg ), $plugin );
			$triage = null;
		}

		if ( ! $triage || empty( $triage['file_priorities'] ) ) {
			$triage = self::build_default_triage( $source_files, $pcp_results );
		}

		// Phase 2: Batch reviews.
		$batches = self::build_batches( $source_files, $triage );

		if ( empty( $batches ) ) {
			Tools::audit_log( 'Automated review failed: No reviewable source files found.', $plugin );
			return false;
		}

		$batch_system_prompt = self::get_batch_system_prompt( $boundary );
		$batch_results       = array();
		$completed_count     = 0;

		foreach ( $batches as $batch ) {
			$elapsed = microtime( true ) - $start_time;
			if ( $elapsed > $total_timeout * 0.8 ) {
				Tools::audit_log(
					sprintf( 'Automated review: Time limit approaching, skipping remaining %d batch(es).', count( $batches ) - count( $batch_results ) ),
					$plugin
				);

				foreach ( array_slice( $batches, count( $batch_results ) ) as $skipped ) {
					$batch_results[] = array(
						'batch_id' => $skipped['batch_id'],
						'files'    => array_column( $skipped['files'], 'path' ),
						'findings' => array(),
						'error'    => 'Skipped due to time limit.',
					);
				}
				break;
			}

			$batch_result = self::run_batch( $batch, $plugin_dir, $plugin, $triage, $pcp_results, $batch_system_prompt, $boundary );

			if ( is_wp_error( $batch_result ) ) {
				Tools::audit_log( sprintf( 'Automated review: Batch %d failed: %s', $batch['batch_id'], $batch_result->get_error_message() ), $plugin );
				$batch_results[] = array(
					'batch_id' => $batch['batch_id'],
					'files'    => array_column( $batch['files'], 'path' ),
					'findings' => array(),
					'error'    => $batch_result->get_error_message(),
				);
			} else {
				++$completed_count;
				self::accumulate_tokens( $token_usage, $batch_result );
				$batch_results[] = array(
					'batch_id' => $batch['batch_id'],
					'files'    => array_column( $batch['files'], 'path' ),
					'findings' => $batch_result['data']['findings'] ?? array(),
				);
			}
		}

		if ( 0 === $completed_count ) {
			Tools::audit_log( 'Automated review failed: No review batches completed successfully.', $plugin );
			return false;
		}

		// Phase 3: Synthesis.
		$synthesis_result = self::run_synthesis( $plugin, $triage, $batch_results );

		if ( ! is_wp_error( $synthesis_result ) && isset( $synthesis_result['data']['verdict'] ) ) {
			self::accumulate_tokens( $token_usage, $synthesis_result );
			$review = $synthesis_result['data'];
		} else {
			$error_msg = is_wp_error( $synthesis_result ) ? $synthesis_result->get_error_message() : 'Missing verdict in response';
			Tools::audit_log( sprintf( 'Automated review: Synthesis failed: %s — using aggregated fallback.', $error_msg ), $plugin );
			$review = self::build_fallback_result( $batch_results );
		}

		// Enforce verdict consistency — blockers always mean reject.
		if ( ! empty( $review['blockers'] ) && 'reject' !== $review['verdict'] ) {
			$review['verdict'] = 'reject';
		}

		if ( ! isset( $review['verdict'] ) ) {
			return false;
		}

		return array(
			'review'      => $review,
			'token_usage' => $token_usage,
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * File Collection
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Collect all files and source files from the extracted plugin directory.
	 *
	 * @param string $plugin_dir Path to the extracted plugin.
	 * @return array { @type string[] $all, @type array[] $source }
	 */
	protected static function collect_files( string $plugin_dir ): array {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $plugin_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		$skip_dirs       = array( 'vendor', 'node_modules' );
		$text_extensions = array(
			'php',
			'js',
			'mjs',
			'jsx',
			'ts',
			'tsx',
			'css',
			'scss',
			'html',
			'svg',
			'xml',
			'txt',
			'md',
			'json',
			'pot',
			'po',
		);

		$all_files    = array();
		$source_files = array();

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$relative_path = str_replace( $plugin_dir . '/', '', $file->getPathname() );

			foreach ( $skip_dirs as $skip ) {
				if ( str_starts_with( $relative_path, $skip . '/' ) ) {
					continue 2;
				}
			}

			$all_files[] = $relative_path;

			$ext = strtolower( $file->getExtension() );
			if ( in_array( $ext, $text_extensions, true ) ) {
				$source_files[] = array(
					'path' => $relative_path,
					'size' => $file->getSize(),
					'ext'  => $ext,
				);
			}
		}

		return array(
			'all'    => $all_files,
			'source' => $source_files,
		);
	}

	/**
	 * Locate and return the readme file content from the plugin directory.
	 *
	 * @param string $plugin_dir   Path to extracted plugin.
	 * @param array  $source_files Collected source files.
	 * @return string Readme content, or empty string.
	 */
	protected static function find_readme_content( string $plugin_dir, array $source_files ): string {
		foreach ( $source_files as $entry ) {
			if ( ! preg_match( '/(?:^|[\/])readme\.(txt|md)$/i', $entry['path'] ) ) {
				continue;
			}

			$full_path = $plugin_dir . '/' . $entry['path'];
			if ( file_exists( $full_path ) ) {
				return (string) file_get_contents( $full_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		return '';
	}

	/*
	 * -------------------------------------------------------------------------
	 * PCP (Plugin Check) Integration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Retrieve Plugin Check results for the given attachment.
	 *
	 * @param \WP_Post $attachment The ZIP attachment post.
	 * @return array
	 */
	protected static function get_pcp_results( \WP_Post $attachment ): array {
		$results = get_post_meta( $attachment->ID, 'pc_results', true );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Format Plugin Check findings for a specific file.
	 *
	 * @param string $file_path   Relative file path.
	 * @param array  $pcp_results Full PCP results array.
	 * @return string Formatted PCP findings, or empty string.
	 */
	protected static function format_pcp_for_file( string $file_path, array $pcp_results ): string {
		$file_findings = array();

		foreach ( $pcp_results as $result ) {
			if ( empty( $result['file'] ) ) {
				continue;
			}
			if ( str_ends_with( $result['file'], $file_path ) || str_ends_with( $file_path, $result['file'] ) ) {
				$file_findings[] = $result;
			}
		}

		if ( empty( $file_findings ) ) {
			return '';
		}

		$output = sprintf( "--- PCP findings for %s ---\n", $file_path );
		foreach ( $file_findings as $finding ) {
			$output .= sprintf( "LINE %d %s %s: %s\n", $finding['line'] ?? 0, $finding['type'] ?? 'WARNING', $finding['code'] ?? '', $finding['message'] ?? '' );
		}

		return $output;
	}

	/**
	 * Summarize Plugin Check results into an overview of errors and warnings.
	 *
	 * @param array $pcp_results Full PCP results array.
	 * @return array Summary with errors, warnings, error_files, and formatted string.
	 */
	protected static function summarize_pcp_results( array $pcp_results ): array {
		if ( empty( $pcp_results ) ) {
			return array(
				'errors'      => 0,
				'warnings'    => 0,
				'error_files' => array(),
				'formatted'   => '',
			);
		}

		$errors      = 0;
		$warnings    = 0;
		$files_count = array();

		foreach ( $pcp_results as $result ) {
			$type = $result['type'] ?? '';
			$file = $result['file'] ?? 'unknown';

			if ( str_starts_with( $type, 'ERROR' ) ) {
				++$errors;
				$files_count[ $file ] = ( $files_count[ $file ] ?? 0 ) + 1;
			} else {
				++$warnings;
			}
		}

		$formatted = sprintf( "=== Plugin Check (PCP) Summary ===\nTotal: %d errors, %d warnings across %d files\n", $errors, $warnings, count( $files_count ) );

		if ( ! empty( $files_count ) ) {
			arsort( $files_count );
			$file_list = array();
			foreach ( array_slice( $files_count, 0, 10, true ) as $file => $count ) {
				$file_list[] = sprintf( '%s (%d errors)', basename( $file ), $count );
			}
			$formatted .= 'Files with errors: ' . implode( ', ', $file_list ) . "\n";
		}

		return array(
			'errors'      => $errors,
			'warnings'    => $warnings,
			'error_files' => array_keys( $files_count ),
			'formatted'   => $formatted,
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * AI Call Wrapper
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Send a prompt to the AI client and return the parsed response.
	 *
	 * @param string $user_prompt   The user/context prompt.
	 * @param string $system_prompt The system instruction.
	 * @param array  $schema        JSON Schema for the response.
	 * @param int    $max_tokens    Maximum output tokens.
	 * @return array|\WP_Error Array with 'data' and 'token_usage', or WP_Error.
	 */
	protected static function call_ai( string $user_prompt, string $system_prompt, array $schema, int $max_tokens = 8192 ): array|\WP_Error {
		$set_timeout = static function (): int {
			return 3 * MINUTE_IN_SECONDS;
		};
		add_filter( 'wp_ai_client_default_request_timeout', $set_timeout );

		try {
			$result = wp_ai_client_prompt( $user_prompt )
				->using_system_instruction( $system_prompt )
				->as_json_response( $schema )
				->using_max_tokens( $max_tokens )
				->generate_text_result();
		} finally {
			remove_filter( 'wp_ai_client_default_request_timeout', $set_timeout );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$usage       = $result->getTokenUsage();
		$token_usage = array(
			'prompt'     => $usage->getPromptTokens(),
			'completion' => $usage->getCompletionTokens(),
			'total'      => $usage->getTotalTokens(),
		);

		$parsed = json_decode( $result->toText(), true );

		if ( ! is_array( $parsed ) ) {
			return new \WP_Error( 'invalid_json', 'AI response was not valid JSON.' );
		}

		return array(
			'data'        => $parsed,
			'token_usage' => $token_usage,
		);
	}

	/**
	 * Add token counts from a result into the running totals.
	 *
	 * @param array $total  Running totals, modified by reference.
	 * @param array $result Array containing a 'token_usage' key.
	 */
	protected static function accumulate_tokens( array &$total, array $result ): void {
		if ( empty( $result['token_usage'] ) ) {
			return;
		}

		$total['prompt']     += $result['token_usage']['prompt'] ?? 0;
		$total['completion'] += $result['token_usage']['completion'] ?? 0;
		$total['total']      += $result['token_usage']['total'] ?? 0;
	}

	/*
	 * -------------------------------------------------------------------------
	 * Phase 1: Triage
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Run the triage phase to classify files and prioritize the review.
	 *
	 * @param array    $source_files Collected source files with path/size/ext.
	 * @param string[] $all_files    All file paths in the plugin.
	 * @param array    $pcp_summary  Summarized PCP results.
	 * @param string   $readme       Readme file content.
	 * @param string   $boundary     Random boundary token for XML tags.
	 * @return array|\WP_Error Result from call_ai, or WP_Error.
	 */
	protected static function run_triage( array $source_files, array $all_files, array $pcp_summary, string $readme, string $boundary ): array|\WP_Error {
		$prompt = '';

		if ( $readme ) {
			$prompt .= "<plugin-readme-{$boundary}>\n" . $readme . "\n</plugin-readme-{$boundary}>\n\n";
		}

		$prompt .= "=== Source Files ===\n";
		foreach ( $source_files as $entry ) {
			$prompt .= sprintf( "%s (%s)\n", $entry['path'], size_format( $entry['size'] ) );
		}
		$prompt .= "\n=== All Files ===\n" . implode( "\n", $all_files ) . "\n\n";

		if ( ! empty( $pcp_summary['formatted'] ) ) {
			$prompt .= $pcp_summary['formatted'] . "\n";
		}

		$system_prompt = (string) file_get_contents( __DIR__ . '/automated-review/triage-prompt.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return self::call_ai( $prompt, $system_prompt, self::get_triage_result_schema(), 4096 );
	}

	/**
	 * Get the JSON schema for triage results.
	 *
	 * @return array
	 */
	protected static function get_triage_result_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'plugin_summary'    => array( 'type' => 'string' ),
				'expected_prefix'   => array( 'type' => 'string' ),
				'file_priorities'   => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'path'     => array( 'type' => 'string' ),
							'priority' => array(
								'type' => 'string',
								'enum' => array( 'critical', 'normal', 'low', 'skip' ),
							),
						),
						'required'             => array( 'path', 'priority' ),
						'additionalProperties' => false,
					),
				),
				'related_files'     => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'path'    => array( 'type' => 'string' ),
							'related' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
						),
						'required'             => array( 'path', 'related' ),
						'additionalProperties' => false,
					),
				),
				'cross_file_notes'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'custom_sanitizers' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'             => array( 'plugin_summary', 'expected_prefix', 'file_priorities', 'related_files', 'cross_file_notes', 'custom_sanitizers' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Normalize file priorities from the AI array-of-objects format to a path => priority map.
	 *
	 * @param array $file_priorities Raw file priorities from AI or default triage.
	 * @return array Map of path => priority.
	 */
	protected static function normalize_file_priorities( array $file_priorities ): array {
		if ( empty( $file_priorities ) ) {
			return array();
		}

		// Already in map form (string keys).
		if ( ! isset( $file_priorities[0] ) ) {
			return $file_priorities;
		}

		// Array-of-objects forms from the AI schema.
		$map = array();
		foreach ( $file_priorities as $entry ) {
			if ( isset( $entry['path'], $entry['priority'] ) ) {
				$map[ $entry['path'] ] = $entry['priority'];
			}
		}

		return $map;
	}

	/**
	 * Build heuristic triage when the AI triage phase fails.
	 *
	 * @param array $source_files Collected source files with path/size/ext.
	 * @param array $pcp_results  Full PCP results array.
	 * @return array
	 */
	protected static function build_default_triage( array $source_files, array $pcp_results ): array {
		$priorities  = array();
		$error_files = array();

		foreach ( $pcp_results as $result ) {
			if ( str_starts_with( $result['type'] ?? '', 'ERROR' ) && ! empty( $result['file'] ) ) {
				$error_files[] = $result['file'];
			}
		}

		foreach ( $source_files as $entry ) {
			$path = $entry['path'];
			$ext  = $entry['ext'];

			$has_pcp_error = false;
			foreach ( $error_files as $error_file ) {
				if ( str_ends_with( $error_file, $path ) || str_ends_with( $path, $error_file ) ) {
					$has_pcp_error = true;
					break;
				}
			}

			if ( $has_pcp_error || 'php' === $ext ) {
				$priorities[ $path ] = 'critical';
			} elseif ( in_array( $ext, array( 'js', 'mjs', 'jsx', 'ts', 'tsx' ), true ) ) {
				$priorities[ $path ] = 'normal';
			} elseif ( in_array( $ext, array( 'pot', 'po' ), true ) ) {
				$priorities[ $path ] = 'skip';
			} else {
				$priorities[ $path ] = 'low';
			}
		}

		return array(
			'plugin_summary'    => '',
			'expected_prefix'   => '',
			'file_priorities'   => $priorities,
			'related_files'     => array(),
			'cross_file_notes'  => array(),
			'custom_sanitizers' => array(),
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * Phase 2: Batch File Reviews
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Divide source files into review batches based on size and priority.
	 *
	 * @param array $source_files Collected source files with path/size/ext.
	 * @param array $triage       Triage data including file priorities.
	 * @return array[]
	 */
	protected static function build_batches( array $source_files, array $triage ): array {
		$file_priorities = $triage['file_priorities'] ?? array();

		$reviewable = array();
		$oversized  = array();
		foreach ( $source_files as $entry ) {
			$priority = $file_priorities[ $entry['path'] ] ?? 'normal';
			if ( 'skip' === $priority ) {
				continue;
			}

			$file = array_merge( $entry, array( 'priority' => $priority ) );
			if ( $entry['size'] > self::BATCH_SIZE_TARGET ) {
				$oversized[] = $file;
			} else {
				$reviewable[] = $file;
			}
		}

		if ( empty( $reviewable ) && empty( $oversized ) ) {
			return array();
		}

		$total_size   = array_sum( array_column( $reviewable, 'size' ) );
		$target_count = max( 1, (int) ceil( $total_size / self::BATCH_SIZE_TARGET ) );

		usort(
			$reviewable,
			function ( $a, $b ) {
				$order = array(
					'critical' => 0,
					'normal'   => 1,
					'low'      => 2,
				);
				return ( $order[ $a['priority'] ] ?? 1 ) - ( $order[ $b['priority'] ] ?? 1 );
			}
		);

		$batches       = array();
		$current_files = array();
		$current_size  = 0;
		$batch_id      = 1;

		foreach ( $reviewable as $entry ) {
			if ( $current_size > 0 && $current_size + $entry['size'] > self::BATCH_SIZE_TARGET && count( $batches ) < $target_count - 1 ) {
				$batches[]     = array(
					'batch_id' => $batch_id++,
					'files'    => $current_files,
				);
				$current_files = array();
				$current_size  = 0;
			}
			$current_files[] = $entry;
			$current_size   += $entry['size'];
		}

		if ( ! empty( $current_files ) ) {
			$batches[] = array(
				'batch_id' => $batch_id++,
				'files'    => $current_files,
			);
		}

		foreach ( $oversized as $file ) {
			$batches[] = array(
				'batch_id' => $batch_id++,
				'files'    => array( $file ),
			);
		}

		return $batches;
	}

	/**
	 * Run a single batch review against the AI.
	 *
	 * @param array    $batch         Batch data with batch_id and files.
	 * @param string   $plugin_dir    Path to extracted plugin.
	 * @param \WP_Post $plugin        The plugin post.
	 * @param array    $triage        Triage data from the first phase.
	 * @param array    $pcp_results   Full PCP results array.
	 * @param string   $system_prompt The batch system prompt.
	 * @param string   $boundary      Random boundary token for XML tags.
	 * @return array|\WP_Error
	 */
	protected static function run_batch( array $batch, string $plugin_dir, \WP_Post $plugin, array $triage, array $pcp_results, string $system_prompt, string $boundary ): array|\WP_Error {
		$prompt  = "=== Review Context ===\n";
		$prompt .= sprintf( "Plugin: %s (%s)\n", $plugin->post_name, $plugin->post_title );

		if ( ! empty( $triage['expected_prefix'] ) ) {
			$prompt .= sprintf( "Expected prefix: %s\n", $triage['expected_prefix'] );
		}
		if ( ! empty( $triage['plugin_summary'] ) ) {
			$prompt .= sprintf( "Plugin summary: %s\n", $triage['plugin_summary'] );
		}
		if ( ! empty( $triage['cross_file_notes'] ) ) {
			$prompt .= "\nCross-file notes:\n";
			foreach ( $triage['cross_file_notes'] as $note ) {
				$prompt .= '- ' . $note . "\n";
			}
		}
		if ( ! empty( $triage['custom_sanitizers'] ) ) {
			$prompt .= "\nCustom sanitization functions to verify: " . implode( ', ', $triage['custom_sanitizers'] ) . "\n";
		}

		$prompt .= "\n=== Files to Review ===\n\n";

		foreach ( $batch['files'] as $entry ) {
			$full_path = $plugin_dir . '/' . $entry['path'];
			if ( ! file_exists( $full_path ) ) {
				continue;
			}

			$contents = file_get_contents( $full_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $contents ) {
				continue;
			}
			$prompt .= sprintf( "<plugin-source-%1\$s path=\"%2\$s\">\n%3\$s\n</plugin-source-%1\$s>\n\n", $boundary, $entry['path'], $contents );

			$pcp_for_file = self::format_pcp_for_file( $entry['path'], $pcp_results );
			if ( $pcp_for_file ) {
				$prompt .= $pcp_for_file . "\n";
			}
		}

		return self::call_ai( $prompt, $system_prompt, self::get_batch_result_schema() );
	}

	/**
	 * Build the batch system prompt with live guidelines from DevHub.
	 *
	 * @param string $boundary Random boundary token for XML tags.
	 * @return string
	 */
	protected static function get_batch_system_prompt( string $boundary ): string {
		$ref_dir = __DIR__ . '/automated-review';

		$prompt = file_get_contents( $ref_dir . '/batch-prompt.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$prompt = str_replace(
			array( '<plugin-source>', '<plugin-readme>' ),
			array( "<plugin-source-{$boundary}>", "<plugin-readme-{$boundary}>" ),
			$prompt
		);

		// Live guidelines from the Plugin Guidelines handbook page on developer.wordpress.org.
		$guidelines = self::get_devhub_content( 15264 );
		if ( $guidelines ) {
			$prompt .= "\n\n" . $guidelines;
		} else {
			// Fall back to bundled reference when DevHub is unreachable.
			$prompt .= "\n\n" . file_get_contents( $ref_dir . '/guidelines.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		return $prompt;
	}

	/**
	 * Get the JSON schema for batch review results.
	 *
	 * @return array
	 */
	protected static function get_batch_result_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'findings' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'severity'    => array(
								'type' => 'string',
								'enum' => array( 'blocker', 'warning', 'info' ),
							),
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'locations'   => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'category'    => array(
								'type' => 'string',
								'enum' => array( 'security', 'guidelines', 'code_quality', 'structure', 'prefix' ),
							),
						),
						'required'             => array( 'severity', 'title', 'description', 'locations', 'category' ),
						'additionalProperties' => false,
					),
				),
			),
			'required'             => array( 'findings' ),
			'additionalProperties' => false,
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * Phase 3: Synthesis
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Run the synthesis phase to produce the final review verdict.
	 *
	 * @param \WP_Post $plugin        The plugin post.
	 * @param array    $triage        Triage data from the first phase.
	 * @param array    $batch_results Results from all batch reviews.
	 * @return array|\WP_Error
	 */
	protected static function run_synthesis( \WP_Post $plugin, array $triage, array $batch_results ): array|\WP_Error {
		$prompt  = "=== Plugin Overview ===\n";
		$prompt .= sprintf( "Slug: %s\nName: %s\n", $plugin->post_name, $plugin->post_title );

		if ( ! empty( $triage['plugin_summary'] ) ) {
			$prompt .= sprintf( "Summary: %s\n", $triage['plugin_summary'] );
		}
		if ( ! empty( $triage['expected_prefix'] ) ) {
			$prompt .= sprintf( "Expected prefix: %s\n", $triage['expected_prefix'] );
		}
		if ( ! empty( $triage['cross_file_notes'] ) ) {
			$prompt .= "\n=== Cross-File Notes ===\n";
			foreach ( $triage['cross_file_notes'] as $note ) {
				$prompt .= '- ' . $note . "\n";
			}
		}

		foreach ( $batch_results as $batch ) {
			$files   = implode( ', ', $batch['files'] ?? array() );
			$prompt .= sprintf( "\n=== Batch %s Findings (%s) ===\n", $batch['batch_id'] ?? '?', $files );

			if ( ! empty( $batch['error'] ) ) {
				$prompt .= sprintf( "This batch failed: %s\n", $batch['error'] );
				continue;
			}

			$findings = $batch['findings'] ?? array();
			if ( empty( $findings ) ) {
				$prompt .= "No issues found.\n";
				continue;
			}

			foreach ( $findings as $finding ) {
				$prompt .= sprintf(
					"- %s: %s\n  %s\n  %s\n",
					strtoupper( $finding['severity'] ?? 'info' ),
					$finding['title'] ?? '',
					$finding['description'] ?? '',
					! empty( $finding['locations'] ) ? implode( ', ', $finding['locations'] ) : ''
				);
			}
		}

		$system_prompt = (string) file_get_contents( __DIR__ . '/automated-review/synthesis-prompt.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return self::call_ai( $prompt, $system_prompt, self::get_result_schema(), 16384 );
	}

	/**
	 * Build a fallback result by aggregating batch findings without AI synthesis.
	 *
	 * @param array $batch_results Results from all batch reviews.
	 * @return array
	 */
	protected static function build_fallback_result( array $batch_results ): array {
		$blockers = array();
		$warnings = array();
		$info     = array();

		foreach ( $batch_results as $batch ) {
			foreach ( $batch['findings'] ?? array() as $finding ) {
				$entry = array(
					'title'       => $finding['title'] ?? 'Untitled',
					'description' => $finding['description'] ?? '',
					'locations'   => $finding['locations'] ?? array(),
				);

				$severity = $finding['severity'] ?? 'info';
				if ( 'blocker' === $severity ) {
					$blockers[] = $entry;
				} elseif ( 'warning' === $severity ) {
					$warnings[] = $entry;
				} else {
					$info[] = $entry;
				}
			}
		}

		// Never approve if any batches were skipped or failed — coverage is incomplete.
		$has_incomplete = false;
		foreach ( $batch_results as $batch ) {
			if ( ! empty( $batch['error'] ) ) {
				$has_incomplete = true;
				break;
			}
		}

		$verdict = ! empty( $blockers ) ? 'reject' : ( ! empty( $warnings ) || $has_incomplete ? 'needs_changes' : 'approve' );

		$summary = sprintf( 'Automated review found %d blocker(s), %d warning(s), and %d info item(s). Synthesis was unavailable; results aggregated without deduplication.', count( $blockers ), count( $warnings ), count( $info ) );
		if ( $has_incomplete ) {
			$summary .= ' Some batches were skipped or failed — review coverage is incomplete.';
		}

		return array(
			'verdict'  => $verdict,
			'summary'  => $summary,
			'blockers' => $blockers,
			'warnings' => $warnings,
			'info'     => $info,
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * System Prompt Helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Fetch content from a DevHub post, converted to Markdown.
	 *
	 * @param int $post_id DevHub post ID.
	 * @return string Markdown content, or empty string on failure.
	 */
	protected static function get_devhub_content( int $post_id ): string {
		switch_to_blog( 33 ); // developer.wordpress.org.

		try {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return '';
			}

			setup_postdata( $post );
			$html = apply_filters( 'the_content', $post->post_content );
			wp_reset_postdata();
		} finally {
			restore_current_blog();
		}

		if ( function_exists( 'wp_html_to_markdown' ) ) {
			return wp_html_to_markdown( $html );
		}

		return wp_strip_all_tags( $html );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Result Handling
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the JSON schema for the final review result.
	 *
	 * @return array
	 */
	public static function get_result_schema(): array {
		$finding = array(
			'type'                 => 'object',
			'properties'           => array(
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'locations'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'             => array( 'title', 'description', 'locations' ),
			'additionalProperties' => false,
		);

		return array(
			'type'                 => 'object',
			'properties'           => array(
				'verdict'  => array(
					'type' => 'string',
					'enum' => array( 'approve', 'reject', 'needs_changes' ),
				),
				'summary'  => array( 'type' => 'string' ),
				'blockers' => array(
					'type'  => 'array',
					'items' => $finding,
				),
				'warnings' => array(
					'type'  => 'array',
					'items' => $finding,
				),
				'info'     => array(
					'type'  => 'array',
					'items' => $finding,
				),
			),
			'required'             => array( 'verdict', 'summary', 'blockers', 'warnings', 'info' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Format review results as an HTML note for the audit log.
	 *
	 * @param array $result Parsed review results with verdict, summary, and findings.
	 * @return string
	 */
	public static function format_as_note( array $result ): string {
		$result = wp_parse_args(
			$result,
			array(
				'verdict'  => 'unknown',
				'summary'  => '',
				'blockers' => array(),
				'warnings' => array(),
				'info'     => array(),
			)
		);

		$verdict_map = array(
			'approve'       => '✅ APPROVE',
			'reject'        => '❌ REJECT',
			'needs_changes' => '⚠️ NEEDS CHANGES',
		);
		$verdict     = $verdict_map[ $result['verdict'] ] ?? strtoupper( $result['verdict'] );

		$note  = sprintf( "<strong>Automated Plugin Review — %s</strong>\n\n", esc_html( $verdict ) );
		$note .= esc_html( $result['summary'] ) . "\n\n";
		$note .= sprintf( "<strong>Blockers:</strong> %d | <strong>Warnings:</strong> %d | <strong>Info:</strong> %d\n", count( $result['blockers'] ), count( $result['warnings'] ), count( $result['info'] ) );

		$sections = array(
			'blockers' => '❌ Blockers',
			'warnings' => '⚠️ Warnings',
			'info'     => 'ℹ️ Info',
		);

		foreach ( $sections as $key => $label ) {
			if ( ! empty( $result[ $key ] ) ) {
				$note .= sprintf( "\n<strong>%s</strong>\n", $label );
				foreach ( $result[ $key ] as $f ) {
					$note .= self::format_finding( $f );
				}
			}
		}

		return $note;
	}

	/**
	 * Format a single finding as HTML for display in notes.
	 *
	 * @param array $finding A finding with title, description, and locations.
	 * @return string
	 */
	protected static function format_finding( array $finding ): string {
		$output  = sprintf( "\n<strong>%s</strong>\n", esc_html( $finding['title'] ) );
		$output .= esc_html( $finding['description'] ) . "\n";

		if ( ! empty( $finding['locations'] ) ) {
			$output .= implode(
				', ',
				array_map(
					function ( $loc ) {
						return '<code>' . esc_html( $loc ) . '</code>';
					},
					$finding['locations']
				)
			) . "\n";
		}

		return $output;
	}
}
