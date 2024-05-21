<?php

namespace Wporg\TranslationEvents\Attendee;

use Exception;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Stats\Stats_Listener;

class Attendee_Adder {
	private Attendee_Repository $attendee_repository;

	public function __construct( Attendee_Repository $attendee_repository ) {
		$this->attendee_repository = $attendee_repository;
	}

	/**
	 * Add an attendee to an event.
	 *
	 * @param Event    $event    Event to which to add the attendee.
	 * @param Attendee $attendee Attendee to add to the event.
	 *
	 * @throws Exception
	 */
	public function add_to_event( Event $event, Attendee $attendee ): void {
		if ( $this->check_is_new_contributor( $event, $attendee->user_id() ) ) {
			$attendee->mark_as_new_contributor();
		}

		$this->attendee_repository->insert_attendee( $attendee );

		// If the event is active right now,
		// import stats for translations the user created since the event started.
		if ( $event->is_active() ) {
			$this->import_stats( $event, $attendee );
		}
	}

	private function import_stats( Event $event, Attendee $attendee ): void {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"
				insert ignore into {$gp_table_prefix}event_actions
				    (event_id, user_id, original_id, locale, action, happened_at)
				select %d, t.user_id, t.original_id, ts.locale, %s, t.date_added
				from {$gp_table_prefix}translations t,
				     {$gp_table_prefix}translation_sets ts
				where t.user_id = %d
				  and t.translation_set_id = ts.id
				  and t.status in ( 'current', 'waiting', 'changesrequested', 'fuzzy' )
				  and date_added between %s and %s
				",
				array(
					'event_id'          => $event->id(),
					'action'            => Stats_Listener::ACTION_CREATE,
					'user_id'           => $attendee->user_id(),
					'date_added_after'  => $event->start()->utc()->format( 'Y-m-d H:i:s' ),
					'date_added_before' => $event->end()->utc()->format( 'Y-m-d H:i:s' ),
				),
			),
		);
		// phpcs:enable
	}

	private function check_is_new_contributor( Event $event, int $user_id ): bool {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$translation_count = $wpdb->get_var(
			$wpdb->prepare(
				"
					select count(*) as cnt
					from {$gp_table_prefix}translations
					where user_id = %d
					  and date_added < %s
				",
				array(
					$user_id,
					$event->start()->format( 'Y-m-d H:i:s' ),
				),
			)
		);
		// phpcs:enable

		return intval( $translation_count ) <= 10;
	}
}
