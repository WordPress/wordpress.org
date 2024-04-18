<?php

namespace Wporg\TranslationEvents\Stats;

use Exception;
use WP_Post;
use WP_User;
use GP;
use GP_Locale;
use GP_Locales;
use DateTimeImmutable;
use DateTimeZone;

class Stats_Row {
	public int $created;
	public int $reviewed;
	public int $users;
	public ?GP_Locale $language = null;

	public function __construct( $created, $reviewed, $users, ?GP_Locale $language = null ) {
		$this->created  = $created;
		$this->reviewed = $reviewed;
		$this->users    = $users;
		$this->language = $language;
	}
}

class Event_Stats {
	/**
	 * Associative array of rows, with the locale as key.
	 *
	 * @var Stats_Row[]
	 */
	private array $rows = array();

	private Stats_Row $totals;

	/**
	 * Add a stats row.
	 *
	 * @throws Exception When incorrect locale is passed.
	 */
	public function add_row( string $locale, Stats_Row $row ) {
		if ( ! $locale ) {
			throw new Exception( 'locale must not be empty' );
		}
		$this->rows[ $locale ] = $row;
	}

	public function set_totals( Stats_Row $totals ) {
		$this->totals = $totals;
	}

	/**
	 * Get an associative array of rows, with the locale as key.
	 *
	 * @return Stats_Row[]
	 */
	public function rows(): array {
		uasort(
			$this->rows,
			function ( $a, $b ) {
				if ( ! $a->language && ! $b->language ) {
					return 0;
				}
				if ( ! $a->language ) {
					return -1;
				}
				if ( ! $b->language ) {
					return 1;
				}

				return strcasecmp( $a->language->english_name, $b->language->english_name );
			}
		);

		return $this->rows;
	}

	public function totals(): Stats_Row {
		return $this->totals;
	}
}

class Stats_Calculator {
	/**
	 * Get stats for an event.
	 *
	 * @throws Exception When stats calculation failed.
	 */
	public function for_event( int $event_id ): Event_Stats {
		$stats = new Event_Stats();
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs thinks we're doing a schema change but we aren't.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				select locale,
					sum(action = 'create') as created,
					count(*) as total,
					count(distinct user_id) as users
				from {$gp_table_prefix}event_actions
				where event_id = %d
				group by locale with rollup
			",
				array(
					$event_id,
				)
			)
		);
		// phpcs:enable

		foreach ( $rows as $index => $row ) {
			$is_totals = null === $row->locale;
			if ( $is_totals && array_key_last( $rows ) !== $index ) {
				// If this is not the last row, something is wrong in the data in the database table
				// or there's a bug in the query above.
				throw new Exception(
					'Only the last row should have no locale but we found a non-last row with no locale.'
				);
			}

			$lang = GP_Locales::by_slug( $row->locale );
			if ( ! $lang ) {
				$lang = null;
			}

			$stats_row = new Stats_Row(
				$row->created,
				$row->total - $row->created,
				$row->users,
				$lang
			);

			if ( ! $is_totals ) {
				$stats->add_row( $row->locale, $stats_row );
			} else {
				$stats->set_totals( $stats_row );
			}
		}

		return $stats;
	}

	/**
	 * Get contributors for an event.
	 */
	public function get_contributors( int $event_id ): array {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs thinks we're doing a schema change but we aren't.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				select user_id, group_concat( distinct locale ) as locales
				from {$gp_table_prefix}event_actions
				where event_id = %d
				group by user_id
			",
				array(
					$event_id,
				)
			)
		);
		// phpcs:enable

		$users = array();
		foreach ( $rows as $row ) {
			$user          = new WP_User( $row->user_id );
			$user->locales = explode( ',', $row->locales );
			$users[]       = $user;
		}

		uasort(
			$users,
			function ( $a, $b ) {
				return strcasecmp( $a->display_name, $b->display_name );
			}
		);

		return $users;
	}

	/**
	 * Get attendees without contributions for an event.
	 */
	public function get_attendees_not_contributing( int $event_id ): array {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_attendees_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				select distinct user_id
				from {$gp_table_prefix}event_attendees
				where event_id = %d
			",
				array(
					$event_id,
				)
			),
		);

		$contributing_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				select distinct user_id
				from {$gp_table_prefix}event_actions
				where event_id = %d
			",
				array(
					$event_id,
				)
			)
		);

		$attendees_not_contributing_ids = array_diff( $all_attendees_ids, $contributing_ids );

		$attendees_not_contributing = array();
		foreach ( $attendees_not_contributing_ids as $user_id ) {
			$attendees_not_contributing[] = new WP_User( $user_id );
		}

		return $attendees_not_contributing;
	}

	/**
	 * Get projects for an event.
	 */
	public function get_projects( int $event_id ): array {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs thinks we're doing a schema change but we aren't.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				select
					o.project_id as project,
					group_concat( distinct e.locale ) as locales,
					sum(action = 'create') as created,
					count(*) as total,
					count(distinct user_id) as users
				from {$gp_table_prefix}event_actions e, {$gp_table_prefix}originals o
				where e.event_id = %d and e.original_id = o.id
				group by o.project_id
			",
				array(
					$event_id,
				)
			)
		);
		// phpcs:enable

		$projects = array();
		foreach ( $rows as $row ) {
			$row->project      = GP::$project->get( $row->project );
			$project_name      = $row->project->name;
			$parent_project_id = $row->project->parent_project_id;
			while ( $parent_project_id ) {
				$parent_project    = GP::$project->get( $parent_project_id );
				$parent_project_id = $parent_project->parent_project_id;
				$project_name      = substr( htmlspecialchars_decode( $parent_project->name ), 0, 35 ) . ' - ' . $project_name;
			}
			$projects[ $project_name ] = $row;
		}

		ksort( $projects );

		return $projects;
	}

	/**
	 * Check if an event has stats.
	 *
	 * @param int $event_id The id of the event to check.
	 *
	 * @return bool True if the event has stats, false otherwise.
	 */
	public function event_has_stats( int $event_id ): bool {
		try {
			$stats = $this->for_event( $event_id );
		} catch ( Exception $e ) {
			return false;
		}

		return ! empty( $stats->rows() );
	}

	/**
	 * Check if a user is a new translation contributor. A new contributor is a user who has made 10 or fewer translations before event start time.
	 *
	 * @param Event_Start_Date $event_start The event start date.
	 * @param int              $user_id      The user ID.
	 *
	 * @return bool True if the user is a new translation contributor, false otherwise.
	 */
	public function is_new_translation_contributor( $event_start, $user_id ) {
		global $wpdb, $gp_table_prefix;
		$new_contributor_max_translation_count = 10;
		$event_start_date_time                 = $event_start->__toString();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$user_translations_count = $wpdb->get_var(
			$wpdb->prepare(
				"
			select count(*) from {$gp_table_prefix}translations where user_id = %d and date_added < %s
		",
				array(
					$user_id,
					$event_start_date_time,
				)
			)
		);

		if ( get_userdata( $user_id ) && ! $user_translations_count ) {
			return true;
		}
		return $user_translations_count <= $new_contributor_max_translation_count;
	}
}
