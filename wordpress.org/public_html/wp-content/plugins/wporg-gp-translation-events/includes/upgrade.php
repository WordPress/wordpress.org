<?php

namespace Wporg\TranslationEvents;

use Exception;
use WP_Query;

class Upgrade {
	private const VERSION        = 3;
	private const VERSION_OPTION = 'wporg_gp_translations_events_version';

	public static function upgrade_if_needed(): void {
		$previous_version = get_option( self::VERSION_OPTION );

		// If previous version is not set yet, set it to version 1.
		if ( false === $previous_version ) {
			$previous_version = 1;
		}

		if ( self::VERSION === $previous_version ) {
			// Nothing to do, we're already at the latest version.
			return;
		}

		// Upgrade database schema.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::get_database_schema_sql() );

		// Run version-specific upgrades.
		$is_running_tests = 'yes' === getenv( 'WPORG_TRANSLATION_EVENTS_TESTS' );
		if ( $previous_version < 3 && ! $is_running_tests ) {
			try {
				self::v3_set_is_new_contributor();
			} catch ( Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( $e );
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	private static function get_database_schema_sql(): string {
		global $gp_table_prefix;

		return "
			CREATE TABLE `{$gp_table_prefix}event_actions` (
				`translate_event_actions_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`event_id` int(10) NOT NULL COMMENT 'Post_ID of the translation_event post in the wp_posts table',
				`original_id` int(10) NOT NULL COMMENT 'ID of the translation',
				`user_id` int(10) NOT NULL COMMENT 'ID of the user who made the action',
				`action` enum('approve','create','reject','request_changes') NOT NULL COMMENT 'The action that the user made (create, reject, etc)',
				`locale` varchar(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL COMMENT 'Locale of the translation',
				`happened_at` datetime NOT NULL COMMENT 'When the action happened, in UTC',
			PRIMARY KEY (`translate_event_actions_id`),
			UNIQUE KEY `event_per_translated_original_per_user` (`event_id`,`locale`,`original_id`,`user_id`)
			) COMMENT='Tracks translation actions that happened during a translation event';

			CREATE TABLE `{$gp_table_prefix}event_attendees` (
				`translate_event_attendees_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`event_id` int(10) NOT NULL COMMENT 'Post_ID of the translation_event post in the wp_posts table',
				`user_id` int(10) NOT NULL COMMENT 'ID of the user who is attending the event',
				`is_host` tinyint(1) default 0 not null comment 'Whether the user is a host of the event',
				`is_new_contributor` tinyint(1) default 0 not null comment 'Whether the user is a new translation contributor',
			PRIMARY KEY (`translate_event_attendees_id`),
			UNIQUE KEY `event_per_user` (`event_id`,`user_id`),
			INDEX `user` (`user_id`)
			) COMMENT='Attendees of events';
		";
	}

	/**
	 * Set is_new_contributor in attendees table for all events.
	 */
	private static function v3_set_is_new_contributor(): void {
		global $wpdb, $gp_table_prefix;

		$query = new WP_Query(
			array(
				'post_type'   => Translation_Events::CPT,
				'post_status' => 'publish',
			)
		);

		$events              = $query->get_posts();
		$event_repository    = Translation_Events::get_event_repository();
		$attendee_repository = Translation_Events::get_attendee_repository();

		foreach ( $events as $post ) {
			$event = $event_repository->get_event( $post->ID );
			if ( ! $event ) {
				continue;
			}

			$attendees = $attendee_repository->get_attendees( $event->id() );

			foreach ( $attendees as $attendee ) {
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
							$attendee->user_id(),
							$event->start()->format( 'Y-m-d H:i:s' ),
						),
					)
				);

				if ( $translation_count > 10 ) {
					// Not a new contributor.
					continue;
				}

				$wpdb->update(
					"{$gp_table_prefix}event_attendees",
					array( 'is_new_contributor' => 1 ),
					array(
						'event_id' => $attendee->event_id(),
						'user_id'  => $attendee->user_id(),
					)
				);
				// phpcs:enable
			}
		}
	}
}
