<?php

namespace Wporg\TranslationEvents\Event;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Event_Date
 *
 * The event date is in local time, get the UTC time via the utc() method.
 *
 * @package Wporg\TranslationEvents
 */
abstract class Event_Date extends DateTimeImmutable {
	protected $event_timezone;
	public function __construct( string $date, DateTimeZone $timezone = null ) {
		if ( ! $timezone ) {
			$timezone = new DateTimeZone( 'UTC' );
		}

		try {
			$utc_date = new DateTime( $date, new DateTimeZone( 'UTC' ) );
			$utc_date->setTimezone( $timezone );
		} catch ( Exception $e ) {
			$utc_date = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		}

		parent::__construct( $utc_date->format( 'Y-m-d H:i:s' ), $timezone );
		$this->event_timezone = $timezone;
	}

	public function timezone() {
		return $this->event_timezone;
	}

	/**
	 * Get the standard formatted text for the date in UTC.
	 *
	 * @return string The date text.
	 */
	public function __toString(): string {
		return $this->utc()->format( 'Y-m-d H:i:s' );
	}
	/**
	 * Get the local formatted text for the date in UTC.
	 *
	 * @return DateTimeImmutable The date text.
	 */
	public function utc(): DateTimeImmutable {
		return $this->setTimeZone( new DateTimeZone( 'UTC' ) );
	}

	public function is_in_the_past() {
		$current_date_time = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		return $this->utc() < $current_date_time;
	}

	public function print_relative_time_html( $format = false ) {
		echo wp_kses(
			'<time
				class="event-utc-time relative' . ( $this->is_in_the_past() ? '' : ' future' ) . '"
				datetime="' . esc_attr( $this ) . '">' . esc_html( $format ? $this->format( $format ) : $this->get_variable_text() ) . '</time>',
			array(
				'time' => array(
					'class'    => array(),
					'datetime' => array(),
				),
			)
		);
	}

	public function print_time_html( $format = 'D, F j, Y H:i T' ) {
		echo wp_kses(
			'<time
				class="event-utc-time full-time"
				data-format="' . esc_attr( $format ) . '"
				datetime="' . esc_attr( $this ) . '">' . esc_html( $this->format( $format ) ) . '</time>',
			array(
				'time' => array(
					'class'       => array(),
					'datetime'    => array(),
					'data-format' => array(),
				),
			)
		);
	}

	/**
	 * Generate variable text depending on when the event starts or ends.
	 *
	 * @return string The end date text.
	 */
	abstract public function get_variable_text(): string;
}

class Event_Start_Date extends Event_Date {
	public function get_variable_text(): string {
		$interval       = $this->diff( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
		$hours_left     = ( $interval->d * 24 ) + $interval->h;
		$hours_in_a_day = 24;

		if ( $this->is_in_the_past() ) {
			if ( 0 === $hours_left ) {
				/* translators: %s: Number of minutes left. */
				return sprintf( _n( 'started %s minute ago', 'started %s minutes ago', $interval->i ), $interval->i );
			}

			if ( $hours_left >= $hours_in_a_day ) {
				/* translators: %s: Number of hours left. */
				return sprintf( _n( 'started %s hour ago', 'started %s hours ago', $hours_left ), $hours_left );
			}

			// translators: %s: A date.
			return sprintf( __( 'started %s', 'gp-translation-events' ), $this->format( 'D, F j, Y H:i T' ) );
		}

		if ( 0 === $hours_left ) {
			if ( ! $interval->i ) {
				return __( 'starts in less than a minute', 'gp-translation-events' );
			}
			/* translators: %s: Number of minutes left. */
			return sprintf( _n( 'starts in %s minute', 'starts in %s minutes', $interval->i, 'gp-translation-events' ), $interval->i );
		}

		if ( $hours_left <= $hours_in_a_day ) {
			/* translators: %s: Number of hours left. */
			$out = sprintf( _n( 'starts in %s hour', 'starts in %s hours', $hours_left, 'gp-translation-events' ), $hours_left );
			if ( $interval->i ) {
				/* translators: %s: Number of minutes left. */
				$out .= sprintf( _n( ' and %s minute', ' and %s minutes', $interval->i, 'gp-translation-events' ), $interval->i );
			}
			return $out;
		}

		// translators: %s: A date.
		return sprintf( __( 'started %s', 'gp-translation-events' ), $this->format( 'D, F j, Y H:i T' ) );
	}
}

class Event_End_Date extends Event_Date {
	public function get_variable_text(): string {
		if ( $this->is_in_the_past() ) {
			return sprintf( 'ended %s', $this->format( 'l, F j, Y' ) );
		}

		$interval       = $this->diff( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
		$hours_left     = ( $interval->d * 24 ) + $interval->h;
		$hours_in_a_day = 24;

		if ( 0 === $hours_left ) {
			if ( ! $interval->i ) {
				return __( 'ends in less than a minute', 'gp-translation-events' );
			}
			/* translators: %s: Number of minutes left. */
			return sprintf( _n( 'ends in %s minute', 'ends in %s minutes', $interval->i, 'gp-translation-events' ), $interval->i );
		}

		if ( $hours_left <= $hours_in_a_day ) {
			/* translators: %s: Number of hours left. */
			$out = sprintf( _n( 'ends in %s hour', 'ends in %s hours', $hours_left, 'gp-translation-events' ), $hours_left );
			if ( $interval->i ) {
				/* translators: %s: Number of minutes left. */
				$out .= sprintf( _n( ' and %s minute', ' and %s minutes', $interval->i, 'gp-translation-events' ), $interval->i );
			}
			return $out;
		}

		return sprintf( 'until %s', $this->format( 'l, F j, Y H:i' ) );
	}
}
