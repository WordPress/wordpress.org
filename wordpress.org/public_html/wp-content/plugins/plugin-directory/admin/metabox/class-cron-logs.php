<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;
use WordPressdotorg\Plugin_Directory\Jobs\Manager;

/**
 * Displays logs of cron jobs for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Cron_Logs {

	/*
	 * Displays the cron-jobs relevant to the current plugin.
	 */
	public static function display() {
		$post = get_post();

		$jobs = Manager::get_plugin_cron_jobs( $post, [], true );

		if ( empty( $jobs ) ) {
			echo '<p>No cron jobs found.</p>';
			return;
		}

		// Reverse the logs so the most recent is at the top.
		$jobs = array_reverse( $jobs );

		echo '<table class="widefat cron-jobs">';
		echo '<thead>
			<tr>
				<th>When</th>
				<th colspan="2">Task</th>
			</tr>
			</thead>';

		foreach ( $jobs as $job ) {
			$task_name = explode( ':', $job->hook )[0];
			$task_name = ucwords( str_replace( '_', ' ', $task_name ) );

			$task_desc = '';
			// Insert certain job args into the task name.
			foreach (
				[
					'revisions' => 'Revision',
					'tags_touched' => 'Tags'
				] as $field => $name
			) {
				if ( ! empty( $job->args[0][ $field ] ) ) {
					$task_desc .= '<span>' . $name . ': ' . ( is_array( $job->args[0][ $field ] ) ? implode( ', ', $job->args[0][ $field ] ) : $job->args[0][ $field ] ) . '</span>';
				}
			}

			$logs = [];
			if ( ! empty( $job->logs ) ) {
				foreach ( $job->logs as $log ) {
					$content = '';
					if ( $log->content['stderr'] ?? '' ) {
						$content .= '<strong>STDERR:</strong> ' . esc_html( trim( $log->content['stderr'] ) ) . '<br>';
					}
					if ( $log->content['stdout'] ?? '' ) {
						$content .= '<strong>STDOUT:</strong> ' . esc_html( trim( $log->content['stdout'] ) );
					}
					if ( ! $content ) {
						$content = '<em>No output generated.</em>';
					}

					// Markup some logs to incate that it's an expected "error".
					$content = preg_replace(
						'/(End-of-central-directory signature not found|cannot find zipfile directory in one of)/i',
						'<abbr title="This warning is expected. This is not an error.">$1</abbr>',
						$content
					);
					// Some logs might include a path, let's remove it.
					$content = str_ireplace( ABSPATH, '/', $content );

					$logs[] = sprintf(
						'<strong>Timestamp:</strong> %s finished %s after requested time<br>%s',
						esc_html( $log->timestamp ),
						esc_html( human_time_diff( strtotime( $log->timestamp ), $job->start ) ),
						$content
					);
				}
			}
			$logs = implode( '<br><br>', $logs );

			printf(
				'<tr id="job-%d" class="job">
					<td title="%s">%s</td>
					<td>%s<span>%s</span></td>
					<td>%s</td>
				</tr>
				<tr class="log hidden">
					<td colspan="3">
						<pre>%s<br><strong>Job Args:</strong> %s</pre>
					</td>
				</tr>',
				esc_attr( $job->id ),
				esc_attr( human_time_diff( $job->nextrun ?: $job->start ) . ' ago' ),
				date( 'Y-m-d H:i:s', $job->nextrun ?: $job->start ),
				$task_name,
				esc_html( $job->status ),
				$task_desc,
				$logs,
				esc_html( json_encode( $job->args[0], JSON_PRETTY_PRINT ) )
			);
		}

		echo '</table>';
		echo '
			<style>
				table.cron-jobs tr.job {
					cursor: pointer;
				}
				table.cron-jobs tr.job:hover,
				table.cron-jobs tr.job:hover + tr.log {
					background-color: #f9f9f9;
				}
				table.cron-jobs tr.job span {
					display: block;
					font-size: 0.8em;
					margin-top: 5px;
				}
				table.cron-jobs tr.log pre {
					white-space: pre-wrap;
				}
			</style>
			<script>
			jQuery( document ).ready( function() {
				jQuery( "table.cron-jobs tr.job" ).click( function() {
					jQuery( this ).next().toggleClass( "hidden" );
				} );
			} );
		</script>';

	}
}
