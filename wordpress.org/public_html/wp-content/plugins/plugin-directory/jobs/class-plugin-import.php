<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\CLI;
use Exception;

/**
 * Import plugin changes into WordPress.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Import {

	public static function queue( $plugin_slug, $plugin_data ) {
		// To avoid a situation where two imports run concurrently, if one is already scheduled, run it 1hr later (We'll trigger it after the current one finishes).
		$when_to_run = time();
		if ( $next_scheuled = Manager::get_scheduled_time( "import_plugin:{$plugin_slug}", 'last' ) ) {
			$when_to_run = $next_scheuled + HOUR_IN_SECONDS;
		}

		wp_schedule_single_event(
			$when_to_run,
			"import_plugin:{$plugin_slug}",
			array(
				array_merge( array( 'plugin' => $plugin_slug ), $plugin_data )
			)
		);
	}

	/**
	 * The cron trigger for the import job.
	 */
	public static function cron_trigger( $plugin_data ) {
		$plugin_slug  = $plugin_data['plugin'];
		$changed_tags = $plugin_data['tags_touched'];

		try {
			$importer = new CLI\Import;
			$importer->import_from_svn( $plugin_slug, $changed_tags );
		} catch( Exception $e ) {
			fwrite( STDERR, "[{$plugin_slug}] Plugin Import Failed: " . $e->getMessage() . "\n" );
		}

		// Schedule a job to import any i18n changes from this commit
		Plugin_i18n_Import::queue( $plugin_slug, $plugin_data );

		// Re-schedule any other jobs for this plugin to NOW()
		$hook = current_filter();
		if ( $next_timestamp = Manager::get_scheduled_time( $hook, 'next' ) ) {
			Manager::reschedule_event(
				$hook,
				time() + HOUR_IN_SECONDS,
				$next_timestamp
			);
		}
	}

}
