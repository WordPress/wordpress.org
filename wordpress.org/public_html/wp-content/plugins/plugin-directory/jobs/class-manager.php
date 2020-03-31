<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * Manager to wrap up all the logic for Cron tasks.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Manager {

	/**
	 * Add all the actions for cron tasks and schedules.
	 */
	public function __construct() {
		// Register all the cron task handlers.
		add_action( 'admin_init', array( $this, 'register_cron_tasks' ) );
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );

		// The actual cron hooks.
		add_action( 'plugin_directory_meta_sync', array( __NAMESPACE__ . '\Meta_Sync', 'cron_trigger' ) );
		add_action( 'plugin_directory_plugin_support_resolved', array( __NAMESPACE__ . '\Plugin_Support_Resolved', 'cron_trigger' ) );
		add_action( 'plugin_directory_svn_sync', array( __NAMESPACE__ . '\SVN_Watcher', 'cron_trigger' ) );
		add_action( 'plugin_directory_update_api_check', array( __NAMESPACE__ . '\API_Update_Updater', 'cron_trigger' ) );
		add_action( 'plugin_directory_translation_sync', array( __NAMESPACE__ . '\Translation_Sync', 'cron_trigger' ) );

		// A cronjob to check cronjobs
		add_action( 'plugin_directory_check_cronjobs', array( $this, 'register_cron_tasks' ) );

		// Register the wildcard cron hook tasks.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			add_action( 'pre_option_cron', array( $this, 'register_colon_based_hook_handlers' ), 100 );
		}
	}

	/**
	 * Register any cron schedules needed.
	 */
	public function register_schedules( $schedules ) {
		$schedules['every_30s']   = array(
			'interval' => 30,
			'display'  => 'Every 30 seconds',
		);
		$schedules['every_120s']  = array(
			'interval' => 120,
			'display'  => 'Every 120 seconds',
		);
		$schedules['half_hourly'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => 'Half Hourly',
		);

		return $schedules;
	}

	/**
	 * Returns the latest time that the scheduled hook will run.
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
					// Cavalcade should present this field, if not, bail.
					if ( empty( $cron_item['_job'] ) ) {
						continue;
					}

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
	 * Returns the current scheduled events of a hook.
	 *
	 * @param string   $hook           The hook to look for.
	 * @param int|bool $next_timestamp Optional. Returns events for a specific timestamp.
	 * @return array Scheduled events.
	 */
	public static function get_scheduled_events( $hook, $next_timestamp = false ) {

		// Flush the Cavalcade jobs cache, we need fresh data from the database.
		wp_cache_delete( 'jobs', 'cavalcade-jobs' );

		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return [];
		}

		$events = [];

		foreach ( $crons as $timestamp => $cron ) {
			if ( isset( $cron[ $hook ] ) ) {
				foreach ( $cron[ $hook ] as $key => $cron_item ) {
					// Cavalcade should present this field, if not, bail.
					if ( empty( $cron_item['_job'] ) ) {
						continue;
					}

					if ( 'waiting' !== $cron_item['_job']->status ) {
						continue;
					}

					if ( ! $next_timestamp || $next_timestamp === $timestamp ) {
						$events[] = [
							'hook'    => $cron_item['_job']->hook,
							'args'    => $cron_item['_job']->args,
							'nextrun' => $timestamp,
						];
					}
				}
			}
		}

		return $events;
	}

	/**
	 * Updates a cavalcade job.
	 *
	 * This requires the usage of Cavalcade, and will fail without it.
	 *
	 * @param string $hook           The hook to update.
	 * @param int    $next_timestamp The time of the schedule to update.
	 * @param array  $data           The data to update.
	 * @return bool True on success, false on error.
	 */
	public static function update_scheduled_event( $hook, $next_timestamp, $data ) {
		// Flush the Cavalcade jobs cache, we need fresh data from the database
		wp_cache_delete( 'jobs', 'cavalcade-jobs' );

		$crons = _get_cron_array();
		foreach ( $crons as $timestamp => $cron ) {
			if ( $next_timestamp !== $timestamp ) {
				continue;
			}

			if ( isset( $cron[ $hook ] ) ) {
				foreach ( $cron[ $hook ] as $key => $event ) {
					// Cavalcade should present this field, if not, bail.
					if ( empty( $event['_job'] ) ) {
						return false;
					}

					if ( 'waiting' !== $event['_job']->status ) {
						return false;
					}

					$event['_job']->args    = $data['args'];
					$event['_job']->nextrun = $data['nextrun'];
					$event['_job']->save();

					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Reschedules a cavalcade job.
	 * This requires the usage of Cavalcade, and will fail without it.
	 *
	 * @param string   $hook          The hook to reschedule.
	 * @param int|bool $new_timestamp The time to reschedule it to.
	 * @param int|bool $old_timestamp The specific job to schedule. Optional, will affect first job otherwise.
	 * @return bool True on success, false on error.
	 */
	public static function reschedule_event( $hook, $new_timestamp = false, $old_timestamp = false ) {
		$new_timestamp = $new_timestamp ?: time();

		// Flush the Cavalcade jobs cache, we need fresh data from the database
		wp_cache_delete( 'jobs', 'cavalcade-jobs' );

		$crons = _get_cron_array();
		foreach ( $crons as $timestamp => $cron ) {
			if ( $old_timestamp && $old_timestamp != $timestamp ) {
				continue;
			}

			if ( isset( $cron[ $hook ] ) ) {
				foreach ( $cron[ $hook ] as $key => $event ) {
					// Cavalcade should present this field, if not, bail.
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
	 *
	 * The jobs are queued for 1 minutes time to avoid recurring job failures from repeating too soon.
	 *
	 * This method is called on wp-admin pageviews and on a two-minutely cron task.
	 */
	public function register_cron_tasks() {
		if ( ! wp_next_scheduled( 'plugin_directory_meta_sync' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'plugin_directory_meta_sync' );
		}
		if ( ! wp_next_scheduled( 'plugin_directory_plugin_support_resolved' ) ) {
			wp_schedule_event( time() + 60, 'half_hourly', 'plugin_directory_plugin_support_resolved' );
		}
		if ( ! wp_next_scheduled( 'plugin_directory_svn_sync' ) ) {
			wp_schedule_event( time() + 60, 'every_30s', 'plugin_directory_svn_sync' );
		}
		if ( ! wp_next_scheduled( 'plugin_directory_update_api_check' ) ) {
			wp_schedule_event( time() + 60, 'hourly', 'plugin_directory_update_api_check' );
		}
		if ( ! wp_next_scheduled( 'plugin_directory_check_cronjobs' ) ) {
			// This function
			wp_schedule_event( time() + 60, 'every_120s', 'plugin_directory_check_cronjobs' );
		}
		if ( ! wp_next_scheduled ( 'plugin_directory_translation_sync' ) ) {
			wp_schedule_event( time() + 60, 'daily', 'plugin_directory_translation_sync' );
		}

		// Check to see if `WP_CORE_LATEST_RELEASE` has changed since we last ran.
		if ( defined( 'WP_CORE_LATEST_RELEASE' ) && get_option( 'plugins_last_core_release_seen' ) !== WP_CORE_LATEST_RELEASE ) {
			update_option( 'plugins_last_core_release_seen', WP_CORE_LATEST_RELEASE );

			// If the next "Meta Sync" is more than 5 minutes away, perform one ASAP.
			if ( wp_next_scheduled( 'plugin_directory_meta_sync' ) > ( time() + 5 * MINUTE_IN_SECONDS ) ) {
				wp_schedule_single_event( time() + 10, 'plugin_directory_meta_sync' );
			}
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
	public function register_colon_based_hook_handlers( $cron_array ) {
		$wildcard_cron_tasks = array(
			'import_plugin'      => array( __NAMESPACE__ . '\Plugin_Import', 'cron_trigger' ),
			'import_plugin_i18n' => array( __NAMESPACE__ . '\Plugin_i18n_Import', 'cron_trigger' ),
			'tide_sync'          => array( __NAMESPACE__ . '\Tide_Sync', 'cron_trigger' ),
		);

		if ( is_array( $cron_array ) ) {
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
		}

		return $cron_array;
	}

	/**
	 * Clear caches for memory management.
	 *
	 * @static
	 * @see Tools::clear_memory_heavy_variables();
	 */
	public static function clear_memory_heavy_variables() {
		Tools::clear_memory_heavy_variables();
	}

}

