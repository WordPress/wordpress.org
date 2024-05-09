<?php

namespace Wporg\TranslationEvents\Event;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use WP_Error;

class Event_Repository_Cached extends Event_Repository {
	private const CACHE_DURATION    = DAY_IN_SECONDS;
	private const ACTIVE_EVENTS_KEY = 'translation-events-active-events';

	public function insert_event( Event $event ) {
		$event_id_or_error = parent::insert_event( $event );
		if ( $event_id_or_error instanceof WP_Error ) {
			return $event_id_or_error;
		}

		$this->invalidate_cache();
		return $event_id_or_error;
	}

	public function update_event( Event $event ) {
		$event_id_or_error = parent::update_event( $event );
		if ( $event_id_or_error instanceof WP_Error ) {
			return $event_id_or_error;
		}

		$this->invalidate_cache();
		return $event_id_or_error;
	}

	public function trash_event( Event $event ) {
		parent::trash_event( $event );
		$this->invalidate_cache();
	}

	public function delete_event( Event $event ) {
		parent::delete_event( $event );
		$this->invalidate_cache();
	}

	public function get_current_events( int $page = -1, int $page_size = -1 ): Events_Query_Result {
		$this->assert_pagination_arguments( $page, $page_size );

		$cache_duration = self::CACHE_DURATION;
		$now            = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$boundary_start = $now;
		$boundary_end   = $now->modify( "+$cache_duration seconds" );

		$events = wp_cache_get( self::ACTIVE_EVENTS_KEY, '', false, $found );
		if ( ! $found ) {
			$events = $this->get_events_active_between( $boundary_start, $boundary_end )->events;
			wp_cache_set( self::ACTIVE_EVENTS_KEY, $events, '', self::CACHE_DURATION );
		} elseif ( ! is_array( $events ) ) {
			throw new Exception( 'Cached events is not an array, something is wrong' );
		}

		// Filter out events that aren't actually active at $at.
		$events = array_values(
			array_filter(
				$events,
				function ( $event ) use ( $now ) {
					return $event->start() <= $now && $now <= $event->end();
				}
			)
		);

		if ( empty( $events ) ) {
			return new Events_Query_Result( $events, $page, 0 );
		}

		// Split the list of all current events into pages.
		// If no pagination parameters were supplied, we return the full list of events as a single page.

		if ( $page >= 1 ) {
			// Pagination parameters were supplied.
			// Convert from 1-indexed to 0-indexed.
			--$page;
		} else {
			// No pagination parameters were supplied.
			$page      = 0;
			$page_size = count( $events );
		}

		$pages = array_chunk( $events, $page_size );
		if ( ! empty( $pages ) && isset( $pages[ $page ] ) ) {
			$events = $pages[ $page ];
		} else {
			$events = array();
		}

		return new Events_Query_Result( $events, $page, count( $pages ) );
	}

	private function invalidate_cache(): void {
		wp_cache_delete( self::ACTIVE_EVENTS_KEY );
	}
}
