<?php

namespace Wporg\TranslationEvents\Tests;

use wpdb;

class Stats_Factory {
	public function clean() {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "delete from {$gp_table_prefix}event_actions" );
		// phpcs:enable
	}

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

	public function get_count(): int {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return intval( $wpdb->get_var( "select count(*) from {$gp_table_prefix}event_actions" ) );
		// phpcs:enable
	}

	public function get_by_event_id( $event_id ): array {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "select * from {$gp_table_prefix}event_actions where event_id = %s", $event_id ), ARRAY_A );
		// phpcs:enable
	}
}
