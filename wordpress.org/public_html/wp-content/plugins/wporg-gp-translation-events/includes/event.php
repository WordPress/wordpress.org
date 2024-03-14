<?php

namespace Wporg\TranslationEvents;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

class Event {
	private int $id;
	private DateTimeImmutable $start;
	private DateTimeImmutable $end;
	private DateTimeZone $timezone;

	/**
	 * Make an Event from post meta.
	 *
	 * @throws Exception When dates are invalid.
	 */
	public static function from_post_meta( int $id, array $meta ): Event {
		if ( ! isset( $meta['_event_start'][0] ) || ! isset( $meta['_event_end'][0] ) || ! isset( $meta['_event_timezone'][0] ) ) {
			throw new Exception( 'Invalid event meta' );
		}

		return new Event(
			$id,
			DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $meta['_event_start'][0], new DateTimeZone( 'UTC' ) ),
			DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $meta['_event_end'][0], new DateTimeZone( 'UTC' ) ),
			new DateTimeZone( $meta['_event_timezone'][0] ),
		);
	}

	private function __construct( int $id, DateTimeImmutable $start, DateTimeImmutable $end, DateTimeZone $timezone ) {
		$this->id       = $id;
		$this->start    = $start;
		$this->end      = $end;
		$this->timezone = $timezone;
	}

	public function id(): int {
		return $this->id;
	}

	public function start(): DateTimeImmutable {
		return $this->start;
	}

	public function end(): DateTimeImmutable {
		return $this->end;
	}

	public function timezone(): DateTimeZone {
		return $this->timezone;
	}

	/**
	 * Generate text for the end date.
	 *
	 * @param string $event_end The end date.
	 *
	 * @return string The end date text.
	 */
	public static function get_end_date_text( string $event_end ): string {
		$end_date_time     = new DateTimeImmutable( $event_end );
		$current_date_time = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$interval       = $end_date_time->diff( $current_date_time );
		$hours_left     = ( $interval->d * 24 ) + $interval->h;
		$hours_in_a_day = 24;

		if ( 0 === $hours_left ) {
			/* translators: %s: Number of minutes left. */
			return sprintf( _n( 'ends in %s minute', 'ends in %s minutes', $interval->i ), $interval->i );
		} elseif ( $hours_left <= $hours_in_a_day ) {
			/* translators: %s: Number of hours left. */
			return sprintf( _n( 'ends in %s hour', 'ends in %s hours', $hours_left ), $hours_left );
		}
		return sprintf( 'until %s', $end_date_time->format( 'M j, Y' ) );
	}
}
