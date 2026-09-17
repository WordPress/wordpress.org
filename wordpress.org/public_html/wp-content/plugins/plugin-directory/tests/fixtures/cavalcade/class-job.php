<?php
/**
 * A stand-in for Cavalcade's job model, which isn't present in the test install.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

namespace HM\Cavalcade\Plugin;

/**
 * Serves a canned set of jobs to Manager::get_plugin_cron_jobs().
 *
 * Only the two static methods that call site uses are implemented. Manager gates every
 * Cavalcade call on `class_exists()`, so defining this makes those paths reachable —
 * no other suite in this plugin exercises them.
 */
class Job {

	/**
	 * The jobs to return, keyed by nothing; filtered on the queried hook.
	 *
	 * @var array
	 */
	public static array $jobs = array();

	/**
	 * Return the queued jobs matching the requested hook.
	 *
	 * @param array $args The query arguments. Only `hook` is honoured.
	 * @return array The matching jobs.
	 */
	public static function get_jobs_by_query( $args ): array {
		return array_values(
			array_filter(
				self::$jobs,
				static function ( $job ) use ( $args ) {
					return empty( $args['hook'] ) || $job->hook === $args['hook'];
				}
			)
		);
	}

	/**
	 * The jobs table name, which the caller rewrites to find the log table.
	 *
	 * @return string The table name.
	 */
	public static function get_table(): string {
		return $GLOBALS['wpdb']->prefix . 'cavalcade_jobs';
	}
}
