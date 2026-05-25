<?php
/**
 * Manager to wrap up all the logic for Cron tasks.
 *
 * @package WordPressdotorg\Theme_Directory\Jobs
 */

namespace WordPressdotorg\Theme_Directory\Jobs;

/**
 * Class Manager
 *
 * @package WordPressdotorg\Theme_Directory\Jobs
 */
class Manager {

	/**
	 * Add all the actions for cron tasks and schedules.
	 */
	public function __construct() {
		// Register all the cron task handlers.
		add_action( 'admin_init', [ $this, 'register_cron_tasks' ] );
		add_filter( 'cron_schedules', [ $this, 'register_schedules' ] );

		// The actual cron hooks.
		add_action( 'theme_directory_trac_sync', [ __NAMESPACE__ . '\Trac_Sync', 'cron_trigger' ] );

		// A cronjob to check cronjobs.
		add_action( 'theme_directory_check_cronjobs', [ $this, 'register_cron_tasks' ] );

		// Import from SVN tasks.
		add_action( 'theme_directory_svn_import_watcher', [ __NAMESPACE__ . '\SVN_Import', 'watcher_trigger' ] );
		add_action( 'theme_directory_svn_import', [ __NAMESPACE__ . '\SVN_Import', 'import_trigger' ] );
	}

	/**
	 * Register any cron schedules needed.
	 *
	 * @see wp_get_schedules() for core-registered schedules.
	 *
	 * @param array $schedules Registered schedule intervals.
	 * @return array
	 */
	public function register_schedules( $schedules ) {
		$schedules['every_15m']  = [
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => 'Every 15 minutes',
		];

		$schedules['every_5m']  = [
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Every 5 minutes',
		];

		return $schedules;
	}

	/**
	 * Queue all of our cron tasks.
	 *
	 * The jobs are queued for 1 minutes time to avoid recurring job failures from repeating too soon.
	 */
	public function register_cron_tasks() {
		if ( ! wp_next_scheduled( 'theme_directory_trac_sync' ) ) {
			wp_schedule_event( time() + 60, 'every_15m', 'theme_directory_trac_sync' );
		}

		if ( ! wp_next_scheduled( 'theme_directory_check_cronjobs' ) ) {
			wp_schedule_event( time() + 60, 'every_15m', 'theme_directory_check_cronjobs' );
		}

		if ( ! wp_next_scheduled( 'theme_directory_svn_import_watcher' ) ) {
			wp_schedule_event( time() + 60, 'every_5m', 'theme_directory_svn_import_watcher' );
		}
	}
}
