<?php

namespace Wporg\TranslationEvents\Stats;

use Wporg\TranslationEvents\Event\Event;

class Stats_Importer {
	/**
	 * Imports the contributions a user made while a given event was active.
	 */
	public function import_for_user_and_event( int $user_id, Event $event ): void {
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
					'user_id'           => $user_id,
					'date_added_after'  => $event->start()->utc()->format( 'Y-m-d H:i:s' ),
					'date_added_before' => $event->end()->utc()->format( 'Y-m-d H:i:s' ),
				),
			),
		);
		// phpcs:enable
	}
}
