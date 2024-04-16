<?php

namespace Wporg\TranslationEvents;

use Exception;
use WP_Query;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Stats\Stats_Calculator;

class Upgrade {
	private const VERSION        = 2;
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
		if ( $previous_version < 2 && ! $is_running_tests ) {
			try {
				self::v2_import_legacy_attendees();
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
			PRIMARY KEY (`translate_event_attendees_id`),
			UNIQUE KEY `event_per_user` (`event_id`,`user_id`),
			INDEX `user` (`user_id`)
			) COMMENT='Attendees of events';
		";
	}

	/**
	 * Previously, event attendance was tracked through user_meta.
	 * This function imports this legacy attendance information into the attendees table.
	 *
	 * Instead of looping through all users, we consider only users who have contributed to an event.
	 *
	 * @throws Exception
	 */
	private static function v2_import_legacy_attendees(): void {
		$query = new WP_Query(
			array(
				'post_type'   => Translation_Events::CPT,
				'post_status' => 'publish',
			)
		);

		$events              = $query->get_posts();
		$stats_calculator    = new Stats_Calculator();
		$attendee_repository = Translation_Events::get_attendee_repository();
		foreach ( $events as $event ) {
			$host_attendee = new Attendee( $event->ID, intval( $event->post_author ) );
			$host_attendee->mark_as_host();
			$attendee_repository->insert_attendee( $host_attendee );

			foreach ( $stats_calculator->get_contributors( $event->ID ) as $user ) {
				$attendee = $attendee_repository->get_attendee( $event->ID, $user->id );
				if ( ! $attendee ) {
					$attendee = new Attendee( $event->ID, $user->ID );
					$attendee_repository->insert_attendee( $attendee );
				}
			}
		}
	}
}
