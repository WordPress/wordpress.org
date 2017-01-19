<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Manager to wrap up all the logic for Cron tasks.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Manager {

	/**
	 * A static method for the cron trigger to fire.
	 */
	public function __construct() {
		// Register all the cron task handlers.
		add_action( 'admin_init', array( $this, 'register_cron_tasks' ) );
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );

		// The actual cron hooks.
		add_action( 'plugin_directory_meta_sync',        array( __NAMESPACE__ . '\Meta_Sync', 'cron_trigger' ) );
		add_action( 'plugin_directory_svn_sync',         array( __NAMESPACE__ . '\SVN_Watcher', 'cron_trigger' ) );
		add_action( 'plugin_directory_update_api_check', array( __NAMESPACE__ . '\API_Update_Updater', 'cron_trigger' ) );

		// Register the wildcard cron hook tasks.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			add_action( 'pre_option_cron', array( $this, 'register_colon_based_hook_handlers' ), 100 );
		}

	}

	/**
	 * Register any cron schedules needed.
	 */
	public function register_schedules( $schedules ) {
		$schedules['every_30s'] = array( 'interval' => 30, 'display' => 'Every 30 seconds' );

		return $schedules;
	}

	/**
 	 * Returns the latest time that the scheduled hook will run
 	 *
 	 * @param string $hook The hook to look for.
 	 * @param string $when 'last' or 'next' for when the hook runs.
 	 * @return bool|int False on failure, The timestamp on success.
 	 */
	public static function get_scheduled_time( $hook, $when = 'last' ) {

		// Flush the Cavalcade jobs cache, we need fresh data from the database
		wp_cache_delete( 'jobs', 'cavalcade-jobs' );

		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return false;
		}

		$timestamps = array();

		foreach ( $crons as $timestamp => $cron ) {
			if ( isset( $cron[ $hook ] ) ) {
				foreach ( $cron[ $hook ] as $key => $cron_item ) {
					if ( 'waiting' === $cron_item['_job']->status ) {
						$timestamps[] = $timestamp;
						break;
					}
				}
			}
		}

		if ( empty( $timestamps ) ) {
			return false;
		}

		if ( 'last' == $when ) {
			return max( $timestamps );
		} else {
			return min( $timestamps );
		}
	}
	
	/**
 	 * Reschedules a cavalcade job.
 	 * This requires the usage of Cavalcade, and will fail without it.
 	 *
 	 * @param string $hook The Hook to reschedule.
 	 * @param int $new_timestamp The time to reschedule it to.
 	 * @param int $old_timestamp The specific job to schedule. Optional, will affect first job otherwise.
 	 */
	public static function reschedule_event( $hook, $new_timestamp = false, $old_timestamp = false ) {
		$new_timestatmp = $new_timestamp ?: time();

		// Flush the Cavalcade jobs cache, we need fresh data from the database
		wp_cache_delete( 'jobs', 'cavalcade-jobs' );

		$crons = _get_cron_array();
		foreach ( $crons as $timestamp => $cron ) {
			if ( $old_timestamp && $old_timestamp != $timestamp ) {
				continue;
			}

			if ( isset( $cron[ $hook ] ) ) {
				foreach ( $cron[ $hook ] as $key => $event ) {
					// Cavalcade should present this field,if not, bail.
					if ( empty( $event['_job'] ) ) {
						return false;
					}

					if ( 'waiting' !== $event['_job']->status ) {
						return false;
					}

					$event['_job']->nextrun = $new_timestamp;
					$event['_job']->save();

					return true;
				}
			}
		}
	
		return false;
	}
	


	/**
	 * Queue all of our cron tasks.
	 */
	function register_cron_tasks() {
		if ( ! wp_next_scheduled ( 'plugin_directory_meta_sync' ) ) {
			wp_schedule_event( time(), 'hourly', 'plugin_directory_meta_sync' );
		}
		if ( ! wp_next_scheduled ( 'plugin_directory_svn_sync' ) ) {
			wp_schedule_event( time(), 'every_30s', 'plugin_directory_svn_sync' );
		}
		if ( ! wp_next_scheduled ( 'plugin_directory_update_api_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'plugin_directory_update_api_check' );
		}
	}

	/**
	 * The WordPress Cron implementation isn't great at determining if a job is already enqueued,
	 * as a result, we use a "fake" hook to encode the plugin slug into the job name to allow us to
	 * detect if a cron task for that plugin has already been registered.
	 *
	 * These cron tasks are in the form of 'import_plugin:$slug', this maps them to their expected handlers.
	 *
	 * @param array $cron_array The Cron array.
	 * @return array The Cron array passed, unchanged.
	 */
	function register_colon_based_hook_handlers( $cron_array ) {
		$wildcard_cron_tasks = array(
			'import_plugin'      => array( __NAMESPACE__ . '\Plugin_Import', 'cron_trigger' ),
			'import_plugin_i18n' => array( __NAMESPACE__ . '\Plugin_i18n_Import', 'cron_trigger' ),
		);

		foreach ( $cron_array as $timestamp => $handlers ) {
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}
			foreach ( $handlers as $hook => $jobs ) {
				$pos = strpos( $hook, ':' );
				if ( ! $pos ) {
					continue;
				}

				$partial_hook = substr( $hook, 0, $pos );

				if ( isset( $wildcard_cron_tasks[ $partial_hook ] ) ) {
					add_action( $hook, $wildcard_cron_tasks[ $partial_hook ], 10, PHP_INT_MAX );
				}
			}
		}

		return $cron_array;
	}


}

