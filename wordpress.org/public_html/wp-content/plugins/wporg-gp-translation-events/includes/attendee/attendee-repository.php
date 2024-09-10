<?php

namespace Wporg\TranslationEvents\Attendee;

use Exception;

class Attendee_Repository {

	private array $cached_current_user_attendee = array();

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
				"insert ignore into {$gp_table_prefix}event_attendees (event_id, user_id, is_host, is_new_contributor, is_remote) values (%d, %d, %d, %d, %d)",
				array(
					'event_id'           => $attendee->event_id(),
					'user_id'            => $attendee->user_id(),
					'is_host'            => $attendee->is_host() ? 1 : 0,
					'is_new_contributor' => $attendee->is_new_contributor() ? 1 : 0,
					'is_remote'          => $attendee->is_remote() ? 1 : 0,
				),
			),
		);
		// phpcs:enable

		wp_cache_delete( 'events_for_user_' . $attendee->user_id() );
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
			array(
				'is_host'   => $attendee->is_host() ? 1 : 0,
				'is_remote' => $attendee->is_remote() ? 1 : 0,
			),
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

		$wpdb->delete(
			"{$gp_table_prefix}event_actions",
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
		wp_cache_delete( 'events_for_user_' . $user_id );
	}

	/**
	 * @throws Exception
	 */
	public function get_attendee_for_event_for_user( int $event_id, int $user_id ): ?Attendee {
		$attendees = $this->get_attendees_for_user_for_events( $user_id, array( $event_id ), );
		if ( 1 !== count( $attendees ) ) {
			return null;
		}
		return $attendees[ $event_id ];
	}

	public function is_user_attending( int $event_id, int $user_id ): ?Attendee {
		if ( ! isset( $this->cached_current_user_attendee[ $user_id ] ) ) {
			$this->cached_current_user_attendee[ $user_id ] = $this->get_attendees_for_user_for_events( $user_id );
		}
		$is_attending = $this->cached_current_user_attendee[ $user_id ][ $event_id ] ?? null;

		return $is_attending;
	}

	/**
	 * @var int $user_id
	 * @return object
	 * @throws Exception
	 */
	public function get_user_attended_events( int $user_id ): array {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$event_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
					select
						event_id
					from {$gp_table_prefix}event_attendees attendees
					where user_id = %d
				",
				$user_id
			)
		);
		// phpcs:enable
		return $event_ids;
	}

	/**
	 * @var int[] $event_ids
	 * @return Attendee[] Associative array with event id as key.
	 * @throws Exception
	 */
	public function get_attendees_for_user_for_events( int $user_id, array $event_ids = array() ): array {
		// Prevent SQL injection.
		foreach ( $event_ids as $event_id ) {
			if ( is_numeric( $event_id ) ) {
				$event_id = intval( $event_id );
			}
			if ( ! is_int( $event_id ) || $event_id <= 0 ) {
				return array();
			}
		}

		global $wpdb, $gp_table_prefix;
		$and_event_ids   = '';
		$event_id_params = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
		if ( ! empty( $event_ids ) ) {
			$and_event_ids = 'and event_id in (' . $event_id_params . ')';

		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
					select
						event_id,
						user_id,
						is_host,
						is_new_contributor,
						is_remote,
						(
							select group_concat( distinct locale )
							from {$gp_table_prefix}event_actions
							where event_id = attendees.event_id
							  and user_id = attendees.user_id
						) as locales
					from {$gp_table_prefix}event_attendees attendees
					where user_id = %d {$and_event_ids}
				",
				array_merge(
					array( $user_id ),
					$event_ids
				)
			),
			OBJECT_K
		);
		// phpcs:enable
		return array_map(
			function ( $row ) {
				return new Attendee(
					$row->event_id,
					$row->user_id,
					'1' === $row->is_host,
					'1' === $row->is_new_contributor,
					null === $row->locales ? array() : explode( ',', $row->locales ),
					'1' === $row->is_remote,
				);
			},
			$rows,
		);
	}

	/**
	 * Retrieve all the attendees of an event.
	 *
	 * @return Attendee[] Attendees of the event. Associative array with user_id as key.
	 * @throws Exception
	 */
	public function get_attendees( int $event_id ): array {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				select
					user_id,
					is_host,
					is_new_contributor,
					is_remote,
					(
						select group_concat( distinct locale )
						from {$gp_table_prefix}event_actions
						where event_id = attendees.event_id
						  and user_id = attendees.user_id
					) as locales
				from {$gp_table_prefix}event_attendees attendees
				where event_id = %d
			",
				array(
					$event_id,
				)
			),
			OBJECT_K,
		);
		// phpcs:enable

		return array_map(
			function ( $row ) use ( $event_id ) {
				return new Attendee(
					$event_id,
					$row->user_id,
					'1' === $row->is_host,
					'1' === $row->is_new_contributor,
					null === $row->locales ? array() : explode( ',', $row->locales ),
					'1' === $row->is_remote,
				);
			},
			$rows,
		);
	}

	/**
	 * Get attendees without contributions for an event.
	 *
	 * @param int $event_id The id of the event.
	 *
	 * @return Attendee[] Associative array with user_id as key.
	 * @throws Exception
	 */
	public function get_attendees_not_contributing( int $event_id ): array {
		return array_filter(
			$this->get_attendees( $event_id ),
			function ( Attendee $attendee ) {
				return ! $attendee->is_contributor();
			}
		);
	}

	/**
	 * Get the hosts' users for an event.
	 *
	 * @param int $event_id The id of the event.
	 *
	 * @return Attendee[] The hosts of the event. Associative array with user_id as key.
	 * @throws Exception
	 */
	public function get_hosts( int $event_id ): array {
		return array_filter(
			$this->get_attendees( $event_id ),
			function ( Attendee $attendee ) {
				return $attendee->is_host();
			}
		);
	}
}
