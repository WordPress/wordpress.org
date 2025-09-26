<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

/**
 * Handles the plugin updates PCP.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Updates_PCP {

	public function check_single_plugin( $plugin, $stable_tag, $old_stable_tag, $changed_svn_tags, $svn_revision ) {
		$to_scan = [];
		foreach ( (array) $changed_svn_tags as $tag ) {
			if (
				// Always scan trunk if it was touched
				'trunk' === $tag ||
				// Only scan tags that are > current stable, avoids scanning old tags when deleted.
				(
					'trunk' != $stable_tag &&
					version_compare( $tag, $stable_tag, '>=' )
				)
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
			$plugin_check_result = $this->check_plugin( 'update' );
			if ( ! $result ) {
				continue;
			}
			$hash   = $result['hash'];

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
				self::notify_plugin_authors( $plugin, $plugin_check_result, $tag );
				self::notify_slack_channel( $plugin, $plugin_check_result, $tag );
			}
		}

		update_post_meta( $plugin->ID, '_scan_notified', $already_notified );
	}

	public static function notify_plugin_authors( $plugin, $results, $tag ) {
		ob_start();

		printf(
			"Found %d errors in %d files.\n\n",
			$results[ 'totals' ][ 'errors' ],
			count( $results[ 'files' ] )
		);

		$last_file = false;
		foreach ( $results[ 'files' ] as $pathname => $file ) {
			list( $slug, $filename ) = explode( '/', $pathname, 2 );
			foreach ( $file[ 'messages' ] as $message ) {
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
					$message['source'],
					$message['message']
				);

				if ( $message['context'] ) {
					foreach ( $message['context'] as $line_no => $context_line ) {
						echo $line_no . "\t" . $context_line . "\n";
					}
					echo "\n";
				}
			}
		}

		$body = ob_get_clean();

		$email = new Email_To_Committers(
			$plugin,
			array(
				'subject' => 'Automated scanning has detected errors in ###PLUGIN###',
				'body'    => $body,
			)
		);

		$email->send();
	}

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
		foreach ( $results[ 'files' ] as $pathname => $file ) {
			list( $slug, $filename ) = explode( '/', $pathname, 2 );
			foreach ( $file[ 'messages' ] as $message ) {
				// Skip warnings for now
				if ( 'WARNING' === $message['type'] ) {
					continue;
				}

				// Count the instances of each error per filename
				$summary[ $message['source'] ][ $filename ][ $message['line'] ] = true;
			}
		}

		if ( empty( $summary ) ) {
			return;
		}

		$active_installs = get_post_meta( $plugin->ID, 'active_installs', true );

		$body = sprintf( "Detected errors in *%s*\n", $plugin->post_title );
		if ( $active_installs >= 10000 ) {
			$body .= sprintf( ":bangbang::bangbang::bangbang: %d+ active installs :bangbang::bangbang::bangbang:\n", $active_installs );
		} else {
			$body .= sprintf( "%d+ active installs\n", $active_installs );
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
		foreach ( $summary as $source => $file_errors ) {
			$body .= sprintf( "%-80s %8d %8d\n", $source, count( $file_errors, COUNT_RECURSIVE ), count ( $file_errors ) );
		}
		$body .= '```';

		if ( defined( 'PLUGIN_REVIEW_ALERT_SLACK_CHANNEL' ) && function_exists( 'slack_dm' ) ) {
			\slack_dm(
				$body,
				PLUGIN_REVIEW_ALERT_SLACK_CHANNEL,
				true
			);
		}
	}
}
