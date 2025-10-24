<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Shortcodes\Upload_Handler;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Tools\{ Filesystem, SVN };
use WordPressdotorg\Plugin_Directory\Email\Generic_To_Committers as Email_To_Committers;

/**
 * Handles the plugin updates PCP runs.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Updates_PCP {

	/**
	 * Watch for plugin imports and queue a PCP scan if needed.
	 *
	 * @param string $plugin            The plugin slug.
	 * @param string $stable_tag        The new stable tag.
	 * @param string $old_stable_tag    The old stable tag.
	 * @param array  $changed_svn_tags  The SVN tags that were changed.
	 * @param int    $svn_revision      The SVN revision number.
	 */
	public static function wporg_plugins_imported( $plugin, $stable_tag, $old_stable_tag, $changed_svn_tags, $svn_revision ) {
		$to_scan = [];
		foreach ( (array) $changed_svn_tags as $tag ) {
			if (
				// Always scan trunk if it was touched
				'trunk' === $tag ||
				// Only scan tags that are > current stable, avoids scanning old tags when deleted.
				version_compare( $tag, $stable_tag, '>=' )
			) {
				$to_scan[] = $tag;
			}
		}

		// If only old tags were affected, we don't need to scan anything.
		if ( ! $to_scan ) {
			return;
		}

		// always scan the current stable release
		$to_scan[] = $stable_tag;

		$to_scan = array_unique( $to_scan );

		self::queue( $plugin->post_name, $to_scan );
	}

	/**
	 * Actually queue a scan job.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param array  $args        The data to pass to the job.
	 */
	public static function queue( $plugin_slug, ...$args ) {
		// To avoid a situation where two imports run concurrently, if one is already scheduled, run it 1hr later (We'll trigger it after the current one finishes).
		$when_to_run = time() + 5;
		if ( $next_scheduled = Manager::get_scheduled_time( "scan_plugin:{$plugin_slug}", 'last' ) ) {
			$when_to_run = $next_scheduled + HOUR_IN_SECONDS;
		}

		wp_schedule_single_event(
			$when_to_run,
			"scan_plugin:{$plugin_slug}",
			array_merge( array( $plugin_slug ), $args ),
		);
	}

	/**
	 * Cron callback to scan a plugin with PCP.
	 *
	 * @param int   $plugin_slug The plugin ID.
	 * @param array $to_scan     The tags to scan.
	 */
	public static function cron_trigger( $plugin_slug, $to_scan ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );

		$already_notified     = get_post_meta( $plugin->ID, '_scan_notified', true ) ?: [];
		$hashes_seen_this_run = [];

		// Clean out any old scan notifications.
		// Re-send the scan results after a month if a change is made.
		foreach ( $already_notified as $key => $time ) {
			if ( $time < time() - MONTH_IN_SECONDS ) {
				unset( $already_notified[ $key ] );
			}
		}

		foreach ( $to_scan as $tag ) {
			// Fetch the plugin to scan.
			$local_path = self::export_plugin_locally( $plugin->post_name, $tag );
			if ( ! $local_path ) {
				echo "Failed to export plugin {$plugin->post_name} tag {$tag} for scanning.\n";
				continue;
			}

			$plugin_check_result = self::run_plugin_check( $plugin_slug, $local_path, $tag, 'update' );
			if ( ! $plugin_check_result ) {
				continue;
			}
			$hash = $plugin_check_result['hash'];

			// Check to see if the plugin authors have been notified about this result yet.
			if ( isset( $already_notified[ $hash ] ) ) {
				continue;
			}

			// Record it as the author being notified.
			$already_notified[ $hash ] = time();

			// Don't notify for two different tags with the same result.
			if ( isset( $hashes_seen_this_run[ $hash ] ) ) {
				continue;
			}
			$hashes_seen_this_run[ $hash ] = true;

			// Only notify when there's errors.
			if ( $plugin_check_result['totals']['errors'] > 0 ) {
			//	self::notify_plugin_authors( $plugin, $plugin_check_result, $tag ); // TODO: Enable, disabled until email content is finalised.
				self::notify_slack_channel( $plugin, $plugin_check_result, $tag );
			}
		}

		update_post_meta( $plugin->ID, '_scan_notified', $already_notified );
	}

	/**
	 * Notifies the plugin authors about the PCP results.
	 *
	 * @param object $plugin  The plugin post object.
	 * @param array  $results The results from PCP.
	 * @param string $tag     The tag that was scanned.
	 */
	public static function notify_plugin_authors( $plugin, $results, $tag ) {
		ob_start();

		printf(
			"Found %d errors in %d files.\n\n",
			$results[ 'totals' ][ 'errors' ],
			count( $results[ 'files' ] )
		);

		$last_file = false;
		foreach ( $results[ 'files' ] as $filename => $file ) {
			foreach ( $file as $message ) {
				// Skip warnings for now
				if ( 'WARNING' === $message['type'] ) {
					continue;
				}

				if ( $last_file !== $filename ) {
					printf(
						"File: %s (https://plugins.trac.wordpress.org/browser/%s/%s/%s)\n",
						$filename,
						$plugin->post_name,
						( 'trunk' === $tag ? 'trunk' : 'tags/' . $tag ),
						$filename
					);
					$last_file = $filename;
				}

				// The error/warning
				printf(
					"Line %d - %s %s\n%s\n",
					$message['line'],
					$message['type'],
					$message['code'],
					$message['message']
				);

				if ( ! empty( $message['context'] ) ) {
					foreach ( $message['context'] as $line_no => $context_line ) {
						echo $line_no . "\t" . $context_line . "\n";
					}
					echo "\n";
				}
			}
		}

		$body = ob_get_clean();

		if ( ! $body ) {
			return;
		}

		if ( wp_doing_cron() ) {
			// During cron, output the body to the log.
			echo "\n==== Plugin Check Results for {$plugin->post_name} EMAIL ====\n";
			echo $body;
		}

		$email = new Email_To_Committers(
			$plugin,
			array(
				'subject' => 'Automated scanning has detected errors in ###PLUGIN###',
				'body'    => $body,
			)
		);

		$email->send();
	}

	/**
	 * Notifies a Slack channel about the PCP results.
	 *
	 * @param object $plugin  The plugin post object.
	 * @param array  $results The results from PCP.
	 * @param string $tag     The tag that was scanned.
	 */
	public static function notify_slack_channel( $plugin, $results, $tag ) {

		// Don't alert the channel for plugins that have already been closed
		if ( 'closed' === $plugin->post_status ) {
			return;
		}

		$totals = sprintf(
			"Found %d errors in %s %s.\n\n",
			$results[ 'totals' ][ 'errors' ],
			$plugin->post_name,
			$tag
		);

		$summary = [];
		foreach ( $results[ 'files' ] as $filename => $file ) {
			foreach ( $file as $message ) {
				// Skip warnings for now
				if ( 'WARNING' === $message['type'] ) {
					continue;
				}

				// Count the instances of each error per filename
				$summary[ $message['code'] ][ $filename ][ $message['line'] ] = true;
			}
		}

		if ( empty( $summary ) ) {
			return;
		}

		$active_installs = get_post_meta( $plugin->ID, 'active_installs', true );

		$body = sprintf( "Detected errors in *%s*\n", $plugin->post_title );
		if ( $active_installs >= 10000 ) {
			$body .= sprintf( ":bangbang::bangbang::bangbang: %s+ active installs :bangbang::bangbang::bangbang:\n", number_format_i18n( $active_installs ) );
		} else {
			$body .= sprintf( "%s+ active installs\n", number_format_i18n( $active_installs ) );
		}

		$body .= $totals . "\n";
		$body .= sprintf( "Details: https://wordpress.org/plugins/wp-admin/post.php?post=%s&action=edit\n", $plugin->ID );
		$body .= sprintf( "Source: https://plugins.trac.wordpress.org/browser/%s/%s/\n",
			$plugin->post_name,
			( 'trunk' === $tag ? 'trunk' : 'tags/' . $tag )
		);
		$body .= sprintf( "Plugin: https://wordpress.org/plugins/%s/\n", $plugin->post_name );

		$body .= "\n\n```\n";
		$body .= sprintf( "%-80s %8s %8s\n", 'Type', 'Errors', 'Files' );
		$body .= sprintf( "%-80s %8s %8s\n", '----', '------', '-----' );
		foreach ( $summary as $error_code => $file_errors ) {
			$file_count  = count( $file_errors );
			$error_count = count( $file_errors, COUNT_RECURSIVE ) - $file_count;

			$body .= sprintf(
				"%-80s %8d %8d\n",
				$error_code,
				$error_count,
				$file_count
			);
		}
		$body .= '```';

		if ( wp_doing_cron() ) {
			// During cron, output the body to the log.
			echo "\n==== Plugin Check Results for {$plugin->post_name} {$tag} SLACK LOG ====\n";
			echo $body;
		}

		if ( defined( 'PLUGIN_REVIEW_ALERT_SLACK_CHANNEL' ) && function_exists( 'slack_dm' ) ) {
			slack_dm(
				$body,
				PLUGIN_REVIEW_ALERT_SLACK_CHANNEL,
				true
			);
		}
	}

	/**
	 * Exports a plugin from SVN to a local directory for scanning.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param string $tag         The tag to export. 'trunk' or a tag name.
	 * @return string|false The local path to the exported plugin, or false on failure
	 */
	protected static function export_plugin_locally( $plugin_slug, $tag = 'trunk' ) {
		$local_path = Filesystem::temp_directory( "scan-{$plugin_slug}-{$tag}" );
		$svn_url    = Import::PLUGIN_SVN_BASE . '/' . $plugin_slug . ( 'trunk' === $tag ? '/trunk' : '/tags/' . $tag );

		// Create a checkout of the ZIP SVN
		$res = SVN::export( $svn_url, $local_path, [ '--force' /* Overwrite the folder contents */ ] );

		if ( ! $res['result'] ) {
			return false;
		}

		return $local_path;
	}

	/**
	 * Sends a plugin through Plugin Check.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param string $path        The local path to the plugin.
	 * @param string $tag         The tag being scanned.
	 * @param string $mode        The mode to run plugin-check in. 'new' or 'update'.
	 * @return array The results of the plugin check.
	 */
	public static function run_plugin_check( $plugin_slug, $path, $tag = '', $mode = 'new' ) {
		// Run the checks.
		if (
			! defined( 'WPCLI' ) ||
			! defined( 'WP_CLI_CONFIG_PATH' ) ||
			// The plugin must be activated in order to have plugin-check run.
			! defined( 'WP_PLUGIN_CHECK_VERSION' )
		) {
			return false;
		}

		// Run plugin check via CLI
		$start_time = microtime(1);
		$env_vars   = [
			'PATH'               => $_ENV['PATH'] ?? '/usr/local/bin:/usr/bin:/bin',
			'WP_CLI_CONFIG_PATH' => WP_CLI_CONFIG_PATH,
		];
		$command    = WPCLI . ' --url=https://wordpress.org/plugins ' .
					'plugin check ' .
					'--error-severity=7 --warning-severity=6 --include-low-severity-errors ' .
					'--categories=plugin_repo --format=json ' .
					'--mode=' . escapeshellarg( $mode ) . ' ' .
					'--slug=' . escapeshellarg( $plugin_slug ) . ' ' .
					escapeshellarg( $path );

		$plugin_check_process = proc_open(
			$command,
			[
				1 => [ 'pipe', 'w' ], // STDOUT
				2 => [ 'pipe', 'w' ], // STDERR
			],
			$pipes,
			null,
			$env_vars
		);
		if ( ! $plugin_check_process ) {
			// If we can't run plugin-check, we'll just return a pass.
			return [
				'verdict' => true,
				'results' => [],
				'html'    => '',
			];
		}
		do {
			usleep( 100000 ); // 0.1s

			$total_time = round( microtime(1) - $start_time, 1 );

			$proc_status = proc_get_status( $plugin_check_process );
			$return_code = $proc_status['exitcode'] ?? 1;

			if ( $total_time >= 45 && $proc_status['running'] ) {
				// Terminate it.
				proc_terminate( $plugin_check_process );
			}
		} while ( $proc_status['running'] && $total_time <= 60 ); // 60s max, just in case.

		$output = stream_get_contents( $pipes[1] );
		$stderr = rtrim( stream_get_contents( $pipes[2] ), "\n" );

		// Remove ABSPATH from the output if present.
		$output = str_replace( ABSPATH, '/', $output );
		$output = str_replace( str_replace( '/', '\/', ABSPATH ), '\/', $output ); // JSON encoded
		$stderr = str_replace( ABSPATH, '/', $stderr );

		if ( wp_doing_cron() ) {
			// During cron, output the body to the log.
			echo "\n==== Plugin Check Results for {$plugin_slug} ====\n";
			echo "Total Time: {$total_time}s\nReturn Code:{$return_code}.\n";
			if ( $stderr ) echo "STDERR: {$stderr}\n";
			if ( $output ) echo "OUTPUT: {$output}\n";
		}

		// Close the process.
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $plugin_check_process );

		/**
		 * Anything that plugin-check outputs that we want to discard completely.
		 */
		$is_ignored_code = static function( $code ) use ( $tag ) {
			$ignored_codes = [
			];

			// Very much expected when scanning trunk.
			if ( 'trunk' === $tag ) {
				$ignored_codes[] = 'stable_tag_mismatch';
			}

			return (
				in_array( $code, $ignored_codes, true ) ||
				// All the Readme parser warnings are duplicated, we'll exclude those.
				str_starts_with( $code, 'readme_parser_warnings_' )
			);
		};

		/*
		 * Convert the output into an array.
		 * Format:
		 * FILE: example.extension
		 * [{.....}]
		 *
		 * FILE: example2.extension
		 * [{.....}]
		 */
		$verdict         = true;
		$results         = [];
		$results_by_type = [];
		$files           = [];

		// Some errors can be output before Plugin Check output starts, ignore those, it causes the array_chunk() below to fail.
		if ( ! str_starts_with( $output, 'FILE:' ) ) {
			$start = strpos( $output, 'FILE:' );
			if ( false !== $start ) {
				$output = substr( $output, $start );
			}
		}
		$output = explode( "\n", $output );

		foreach ( array_chunk( $output, 3 ) as $file_result ) {
			if ( ! str_starts_with( $file_result[0], 'FILE:' ) ) {
				continue;
			}

			$filename = trim( explode( ':' , $file_result[0], 2 )[1] );
			$json     = json_decode( $file_result[1], true );

			foreach ( $json as $record ) {
				$record['file'] = $filename;

				if ( $is_ignored_code( $record['code'] ) ) {
					continue;
				}

				$results[] = $record;

				$results_by_type[ $record['type'] ] ??= [];
				$results_by_type[ $record['type'] ][] = $record;

				$files[ $filename ] ??= [];
				$files[ $filename ][] = $record;

				// Record submission stats.
				if ( function_exists( 'bump_stats_extra' ) && 'production' === wp_get_environment_type() ) {
					if ( 'new' === $mode ) {
						bump_stats_extra( 'plugin-check-' . $record['type'], $record['code'] );
					} else {
						bump_stats_extra( 'existing-plugins-' . $record['type'], $record['code'] );
					}
				}

				// Determine if it failed the checks.
				if ( $verdict && 'ERROR' === $record['type'] ) {
					$verdict = false;
				}
			}
		}

		$totals = [
			'errors'   => count( $results_by_type['ERROR'] ?? [] ),
			'warnings' => count( $results_by_type['WARNING'] ?? [] ),
		];

		$hash = sha1( serialize( $results ) );

		return compact( 'verdict', 'results', 'results_by_type', 'files', 'totals', 'return_code', 'output', 'stderr', 'total_time', 'hash' );
	}
}
