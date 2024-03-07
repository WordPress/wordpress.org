<?php

namespace Wporg\TranslationEvents;

use Exception;
use WP_Post;
use WP_User;
use GP_Locale;
use GP_Locales;

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
	public function for_event( WP_Post $event ): Event_Stats {
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
					$event->ID,
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
	public function get_contributors( WP_Post $event ): array {
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
					$event->ID,
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
	 * Check if an event has stats.
	 *
	 * @param WP_Post $event The event to check.
	 *
	 * @return bool True if the event has stats, false otherwise.
	 */
	public function event_has_stats( WP_Post $event ): bool {
		try {
			$stats = $this->for_event( $event );
		} catch ( Exception $e ) {
			return false;
		}

		return ! empty( $stats->rows() );
	}
}
