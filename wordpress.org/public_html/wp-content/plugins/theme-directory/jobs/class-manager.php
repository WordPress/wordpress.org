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
	 * Colon-based hook names mapped to their handlers. The slug is encoded into the
	 * hook name so wp_clear_scheduled_hook() can target a single theme's pending
	 * event without args lookup. See `register_colon_based_hook_handlers()`.
	 *
	 * @var array
	 */
	public static $wildcard_cron_tasks = array(
		'wporg_themes_release_to_live' => 'wporg_themes_cron_release_to_live',
	);

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

		// Register the colon-based cron handlers (wporg_themes_release_to_live:{slug}, etc).
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			// This must run after plugins_loaded so Cavalcade has had a chance to hook in.
			add_action( 'init', [ $this, 'register_colon_based_hook_handlers' ] );
		}
	}

	/**
	 * The WordPress Cron implementation can't easily check whether a job is already
	 * enqueued by args, so we encode the theme slug into the hook name (matching the
	 * plugin directory's pattern). Hooks like `wporg_themes_release_to_live:my-theme`
	 * don't auto-resolve to a handler — this method scans pending cron entries and
	 * attaches the matching handler so the event runs when fired.
	 */
	public function register_colon_based_hook_handlers() {
		$add_callback = static function ( $hook ) {
			if ( ! str_contains( $hook, ':' ) ) {
				return;
			}

			$partial_hook = explode( ':', $hook )[0];
			$callback     = self::$wildcard_cron_tasks[ $partial_hook ] ?? false;

			if ( ! $callback ) {
				return;
			}

			if ( ! has_action( $hook, $callback ) ) {
				add_action( $hook, $callback, 10, PHP_INT_MAX );
			}
		};

		// Flush the Cavalcade jobs cache so we see fresh entries from the database.
		wp_cache_delete( 'jobs', 'cavalcade-jobs' );

		foreach ( _get_cron_array() as $timestamp => $handlers ) {
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}

			foreach ( $handlers as $hook => $jobs ) {
				$add_callback( $hook );
			}
		}

		/*
		 * When jobs are run manually or after-the-fact, we also need to find the current
		 * job by id, since it may not be in the pending cron array yet.
		 */
		if (
			class_exists( '\HM\Cavalcade\Plugin\Job' ) &&
			( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) )
		) {
			$job_id = $GLOBALS['job_id'] ?? false;

			if ( ! $job_id && in_array( 'run', $GLOBALS['argv'] ?? [], true ) ) {
				$job_id = $GLOBALS['argv'][ array_search( 'run', $GLOBALS['argv'] ) + 1 ] ?? false;
			}

			if ( $job_id && is_numeric( $job_id ) ) {
				$job = \HM\Cavalcade\Plugin\Job::get( $job_id );
				if ( $job ) {
					$add_callback( $job->hook );
				}
			}
		}
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
