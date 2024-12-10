<?php

namespace WordPressdotorg\Plugin_Directory;

/**
 * Wrapper for running plugin-check.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Check {

	public static function check_plugin( $plugin_slug, $plugin_root ) {
		// Run the checks.
		if (
			! defined( 'WPCLI' ) ||
			! defined( 'WP_CLI_CONFIG_PATH' ) ||
			// The plugin must be activated in order to have plugin-check run.
			! defined( 'WP_PLUGIN_CHECK_VERSION' ) ||
			// WordPress.org only..
			! function_exists( 'notify_slack' )
		) {
			// If we can't run plugin-check, we'll just return a pass.
			return [
				'verdict' => true,
				'results' => [],
				'html'    => '',
			];
		}

		$result = self::run_checks( $plugin_slug, $plugin_root );

		self::log_to_slack( $result ); // FIXME: need to pass required info.

		return $result;
	}

	/**
	 * Sends a plugin through Plugin Check.
	 * @param string $plugin_slug The plugin slug.
	 * @param string $plugin_root The path to the plugin source.
	 *
	 * @return array The results of the plugin check.
	 */
	public static function run_checks( $plugin_slug, $plugin_root ) {

		// Run plugin check via CLI
		$start_time = microtime(1);
		exec(
			'export WP_CLI_CONFIG_PATH=' . escapeshellarg( WP_CLI_CONFIG_PATH ) . '; ' .
			'timeout 45 ' . // Timeout after 45s if plugin-check is not done.
			WPCLI . ' --url=https://wordpress.org/plugins ' .
			'plugin check ' .
			'--error-severity=7 --warning-severity=6 --categories=plugin_repo --format=json ' .
			'--slug=' . escapeshellarg( $plugin_slug ) . ' ' .
			escapeshellarg( $plugin_root ),
			$output,
			$return_code
		);
		$total_time = round( microtime(1) - $start_time, 1 );

		/**
		 * Anything that plugin-check outputs that we want to discard completely.
		 */
		$is_ignored_code = static function( $code ) {
			$ignored_codes = [
			];

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
		$verdict  = true;
		$results  = [];
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

				// Record submission stats.
				if ( function_exists( 'bump_stats_extra' ) && 'production' === wp_get_environment_type() ) {
					bump_stats_extra( 'plugin-check-' . $record['type'], $record['code'] );
				}

				// Determine if it failed the checks.
				if ( $verdict && 'ERROR' === $record['type'] ) {
					$verdict = false;
				}
			}
		}

		// Generage the HTML for the Plugin Check output.
		$html = sprintf(
			'<strong>' . __( 'Results of Automated Plugin Scanning: %s', 'wporg-plugins' ) . '</strong>',
			$verdict ? __( 'Pass', 'wporg-plugins' ) : __( 'Fail', 'wporg-plugins' )
		);
		if ( $results ) {
			$html .= '<ul class="pc-result" style="list-style: disc">';
			// Display errors, and then warnings.
			foreach ( [ wp_list_filter( $results, [ 'type' => 'ERROR' ] ), wp_list_filter( $results, [ 'type' => 'ERROR' ], 'NOT' ) ] as $result_set ) {
				foreach ( $result_set as $result ) {
					$html .= sprintf(
						'<li>%s <a href="%s">%s</a>: %s</li>',
						esc_html( $result['file'] ),
						esc_url( $result['docs'] ?? '' ),
						esc_html( $result['type'] . ' ' . $result['code'] ),
						esc_html( $result['message'] )
					);
				}
			}
			$html .= '</ul>';
		}
		$html .= __( 'Note: While the automated plugin scan is based on the Plugin Review Guidelines, it is not a complete review. A successful result from the scan does not guarantee that the plugin will be approved, only that it is sufficient to be reviewed. All submitted plugins are checked manually to ensure they meet security and guideline standards before approval.', 'wporg-plugins' );

		// Return the results.
		return [
			'verdict' => $verdict,
			'results' => $results,
			'html'    => $html,
			'runtime' => $total_time,
		];
	}

	public function log_to_slack( $fixme ) {

		// Copypasta, fix refs

		// If the upload is blocked; log it to slack.
		if ( ! $verdict ) {
			// Slack dm the logs.
			$zip_name = reset( $_FILES )['name'];
			$failpass = $verdict ? ':white_check_mark: passed' : ':x: failed';
			if ( $return_code > 1 ) { // TODO: Temporary, as we're always hitting this branch.
				$failpass = ' :rotating_light: errored: ' . $return_code;
			}

			$plugin_name_slug = $this->plugin['Name'] . ' (' . $this->plugin_slug . ')';
			// If we have a post object, link to it.
			if ( $this->plugin_post ) {
				$edit_post_link   = admin_url( 'post.php?post=' . $this->plugin_post->ID . '&action=edit' ); // Can't use get_edit_post_link() as the user can't edit the post.
				$plugin_name_slug = "<{$edit_post_link}|{$plugin_name_slug}>";
			}

			$text = "{$failpass} for {$zip_name}: {$plugin_name_slug} took {$total_time}s\n";

			// Include a simplified / merged version of the results for review.
			$group_by_code = [ 'ERROR' => [], 'WARNING' => [] ];
			foreach ( $results as $result ) {
				$group_by_code[ $result['type'] ][ $result['code'] ] ??= [];
				$group_by_code[ $result['type'] ][ $result['code'] ][] = $result;
			}
			foreach ( $group_by_code as $type => $codes ) {
				foreach ( $codes as $code_results ) {
					$text .= "• *{$type}: {$code_results[0]['code']}*";
					if ( 1 === count( $code_results ) ) {
						$text .= ": {$code_results[0]['message']}\n";
					} else {
						$text .= "\n";
						foreach ( array_unique( wp_list_pluck( $code_results, 'message' ) ) as $i => $message ) {
							$multiplier = count( wp_list_filter( $code_results, [ 'message' => $message ] ) );
							$multiplier = $multiplier > 1 ? " {$multiplier}x" : '';

							$text .= " {$i}. {$multiplier} {$message}\n";
						}
					}
				}
			}

			notify_slack( PLUGIN_CHECK_LOGS_SLACK_CHANNEL, $text, wp_get_current_user(), true );
		} elseif ( $return_code ) {
			// Log plugin-check timing out.
			$zip_name   = reset( $_FILES )['name'];
			$text       = ":rotating_light: Error: {$return_code} for {$zip_name}: {$this->plugin['Name']} ({$this->plugin_slug}) took {$total_time}s\n";
			notify_slack( PLUGIN_CHECK_LOGS_SLACK_CHANNEL, $text, wp_get_current_user(), true );
		}

	}
}