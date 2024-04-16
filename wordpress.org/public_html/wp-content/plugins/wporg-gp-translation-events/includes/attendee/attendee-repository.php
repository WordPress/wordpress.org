<?php

namespace Wporg\TranslationEvents\Attendee;

use Exception;
use WP_User;

class Attendee_Repository {
	/**
	 * @throws Exception
	 */
	public function insert_attendee( Attendee $attendee ): void {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"insert ignore into {$gp_table_prefix}event_attendees (event_id, user_id, is_host) values (%d, %d, %d)",
				array(
					'event_id' => $attendee->event_id(),
					'user_id'  => $attendee->user_id(),
					'is_host'  => $attendee->is_host() ? 1 : 0,
				),
			),
		);
		// phpcs:enable
	}

	/**
	 * Update an attendee.
	 *
	 * @param Attendee $attendee The attendee to update.
	 * @return void
	 */
	public function update_attendee( Attendee $attendee ): void {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			"{$gp_table_prefix}event_attendees",
			array( 'is_host' => $attendee->is_host() ? 1 : 0 ),
			array(
				'event_id' => $attendee->event_id(),
				'user_id'  => $attendee->user_id(),
			)
		);
		// phpcs:enable
	}

	/**
	 * @throws Exception
	 */
	public function remove_attendee( int $event_id, int $user_id ): void {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			"{$gp_table_prefix}event_attendees",
			array(
				'event_id' => $event_id,
				'user_id'  => $user_id,
			),
			array(
				'%d',
				'%d',
			),
		);
		// phpcs:enable
	}

	/**
	 * @throws Exception
	 */
	public function get_attendee( int $event_id, int $user_id ): ?Attendee {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"
				select *
				from {$gp_table_prefix}event_attendees
				where event_id = %d
				  and user_id = %d
			",
				array(
					$event_id,
					$user_id,
				),
			)
		);
		// phpcs:enable

		if ( ! $row ) {
			return null;
		}

		$attendee = new Attendee( $row->event_id, $row->user_id );
		if ( '1' === $row->is_host ) {
			$attendee->mark_as_host();
		}

		return $attendee;
	}

	/**
	 * @return Attendee[] Attendees of the event.
	 */
	public function get_attendees( int $event_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// TODO.
		return array();
	}

	/**
	 * Get the hosts' users for an event.
	 *
	 * @param int $event_id The id of the event.
	 *
	 * @return Attendee[] The hosts of the event.
	 * @throws Exception
	 */
	public function get_hosts( int $event_id ): array {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				select event_id, user_id
				from {$gp_table_prefix}event_attendees
				where event_id = %d and is_host = 1
			",
				array(
					$event_id,
				)
			)
		);
		// phpcs:enable

		$hosts = array();
		foreach ( $rows as $row ) {
			$host = new Attendee( $row->event_id, $row->user_id );
			$host->mark_as_host();
			$hosts[] = $host;
		}
		return $hosts;
	}

	/**
	 * @deprecated
	 * TODO: This method should be moved out of this class because it's not about attendance,
	 *       it returns events that match a condition (have a user as attendee), so it belongs in an event repository.
	 *       However, since we don't have an event repository yet, the method is placed here for now.
	 *       When the method is moved to an event repository, it should return Event instances instead of event ids.
	 *
	 * @return int[] Event ids.
	 */
	public function get_events_for_user( int $user_id ): array {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				select event_id
				from {$gp_table_prefix}event_attendees
				where user_id = %d
			",
				array(
					$user_id,
				)
			)
		);
		// phpcs:enable

		return array_map(
			function ( object $row ) {
				return intval( $row->event_id );
			},
			$rows
		);
	}
}
