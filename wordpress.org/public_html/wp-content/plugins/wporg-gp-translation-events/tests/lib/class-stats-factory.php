<?php
/**
 * Factory for creating and inspecting event action stats in tests.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Tests;

use wpdb;

/**
 * Creates, counts, and reads event action rows for tests.
 */
class Stats_Factory {
	/**
	 * Deletes all rows from the event actions table.
	 */
	public function clean() {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "delete from {$gp_table_prefix}event_actions" );
		// phpcs:enable
	}

	/**
	 * Inserts a single event action row.
	 *
	 * @param int    $event_id    The event the action belongs to.
	 * @param int    $user_id     The user who performed the action.
	 * @param int    $original_id The original string the action relates to.
	 * @param string $action      The action name, such as create or approve.
	 * @param string $locale      The locale the action was performed in.
	 */
	public function create( int $event_id, $user_id, $original_id, $action, $locale = 'aa' ) {
		global $wpdb, $gp_table_prefix;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$gp_table_prefix . 'event_actions',
			array(
				'event_id'    => $event_id,
				'user_id'     => $user_id,
				'original_id' => $original_id,
				'action'      => $action,
				'locale'      => $locale,
			)
		);
	}

	/**
	 * Returns the total number of event action rows.
	 *
	 * @return int The number of stored event actions.
	 */
	public function get_count(): int {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return intval( $wpdb->get_var( "select count(*) from {$gp_table_prefix}event_actions" ) );
		// phpcs:enable
	}

	/**
	 * Returns all event action rows for a given event.
	 *
	 * @param int $event_id The event to fetch actions for.
	 *
	 * @return array The event action rows as associative arrays.
	 */
	public function get_by_event_id( $event_id ): array {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "select * from {$gp_table_prefix}event_actions where event_id = %s", $event_id ), ARRAY_A );
		// phpcs:enable
	}
}
