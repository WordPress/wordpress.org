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
		$hook        = "import_plugin:{$plugin_slug}";
		$usual_time  = time() + 5;
		$new_args    = array_merge( array( 'plugin' => $plugin_slug ), $plugin_data );

		$next_scheduled = Manager::get_scheduled_time( $hook, 'next' );

		/*
		 * If the next scheduled run is more than 5 minutes away (e.g. queued by a bulk
		 * batch re-index) and no job for this plugin is currently running, merge the
		 * pending event with this request and pull it forward to the usual time —
		 * otherwise the plugin's commit-driven import would be delayed behind the batch.
		 */
		if (
			$next_scheduled &&
			$next_scheduled > ( time() + 5 * MINUTE_IN_SECONDS ) &&
			! Manager::is_event_running( $hook )
		) {
			$existing      = Manager::get_scheduled_events( $hook, $next_scheduled );
			$existing_args = $existing[0]['args'][0] ?? array();
			$merged_args   = self::merge_plugin_data( $existing_args, $new_args );

			$updated = Manager::update_scheduled_event(
				$hook,
				$next_scheduled,
				array(
					'nextrun' => $usual_time,
					'args'    => array( $merged_args ),
				)
			);

			if ( $updated ) {
				return;
			}
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
			array( $new_args )
		);
	}

	/**
	 * Merge two plugin_data payloads into a single import-job payload.
	 *
	 * Used when collapsing an already-scheduled future event into a newer
	 * request so neither set of changes is lost.
	 *
	 * @param array $existing The args from the currently-scheduled event.
	 * @param array $new      The args from the new request.
	 * @return array Merged args ready to pass to the import job.
	 */
	protected static function merge_plugin_data( array $existing, array $new ) {
		$merged = array_merge( $existing, $new );

		foreach ( array( 'tags_touched', 'tags_deleted', 'revisions' ) as $key ) {
			$merged[ $key ] = array_values( array_unique( array_merge(
				(array) ( $existing[ $key ] ?? array() ),
				(array) ( $new[ $key ] ?? array() )
			) ) );
		}

		foreach ( array( 'readme_touched', 'code_touched', 'assets_touched' ) as $key ) {
			$merged[ $key ] = ! empty( $existing[ $key ] ) || ! empty( $new[ $key ] );
		}

		return $merged;
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
