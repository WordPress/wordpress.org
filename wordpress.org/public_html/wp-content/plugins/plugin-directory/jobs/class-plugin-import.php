<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use Exception;
use WordPressdotorg\Plugin_Directory\CLI;

/**
 * Import plugin changes into WordPress.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Import {

	public static function queue( $plugin_slug, $plugin_data ) {
		$hook       = "import_plugin:{$plugin_slug}";
		$usual_time = time() + 5;

		/*
		 * If the next scheduled run is more than 5 minutes away (e.g. queued by a bulk
		 * batch re-index), pull it forward to the usual import time so a fresh commit
		 * isn't delayed behind the batch. The import re-reads SVN at HEAD, so the new
		 * commit is picked up regardless of which event's args end up firing.
		 */
		$next_scheduled = Manager::get_scheduled_time( $hook, 'next' );
		if (
			$next_scheduled &&
			$next_scheduled > ( time() + 5 * MINUTE_IN_SECONDS ) &&
			Manager::reschedule_event( $hook, $usual_time, $next_scheduled )
		) {
			return;
		}

		// To avoid a situation where two imports run concurrently, if one is already scheduled, run it 1hr later (We'll trigger it after the current one finishes).
		$when_to_run    = $usual_time;
		$last_scheduled = Manager::get_scheduled_time( $hook, 'last' );
		if ( $last_scheduled ) {
			$when_to_run = $last_scheduled + HOUR_IN_SECONDS;
		}

		wp_schedule_single_event(
			$when_to_run,
			$hook,
			array(
				array_merge( array( 'plugin' => $plugin_slug ), $plugin_data ),
			)
		);
	}

	/**
	 * The cron trigger for the import job.
	 */
	public static function cron_trigger( $plugin_data ) {
		$plugin_slug  = $plugin_data['plugin'];

		// Set some default values if not included from the caller.
		$plugin_data['tags_touched']   ??= array( 'trunk' );
		$plugin_data['tags_deleted']   ??= array();
		$plugin_data['revisions']      ??= [ 0 ];
		$plugin_data['readme_touched'] ??= true;
		$plugin_data['code_touched']   ??= true;
		$plugin_data['assets_touched'] ??= true;

		$tags_touched = $plugin_data['tags_touched'];
		$tags_deleted = $plugin_data['tags_deleted'];
		$revision     = max( (array) $plugin_data['revisions'] );

		$importer = new CLI\Import();
		try {
			$importer->import_from_svn( $plugin_slug, $tags_touched, $tags_deleted, $revision );

			// Schedule a job to import any i18n changes from this commit
			Plugin_i18n_Import::queue( $plugin_slug, $plugin_data );
		} catch ( Exception $e ) {
			fwrite( STDERR, "[{$plugin_slug}] Plugin Import Failed: " . $e->getMessage() . "\n" );
		} finally {
			if ( $importer->plugin ) {
				update_post_meta( $importer->plugin->ID, '_last_import', time() );
				update_post_meta( $importer->plugin->ID, '_import_warnings', $importer->warnings );
			}
		}

		// Re-schedule any other jobs for this plugin to NOW()
		$hook = current_filter();
		if ( $next_timestamp = Manager::get_scheduled_time( $hook, 'next' ) ) {
			Manager::reschedule_event(
				$hook,
				time() + 10,
				$next_timestamp
			);
		}
	}

}
