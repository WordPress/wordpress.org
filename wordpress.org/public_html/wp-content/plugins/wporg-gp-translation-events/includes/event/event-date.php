<?php

namespace Wporg\TranslationEvents\Event;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Wporg\TranslationEvents\Translation_Events;

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

		if ( 'now' === $date ) {
			$utc_date = Translation_Events::now();
		} else {
			try {
				$utc_date = new DateTime( $date, new DateTimeZone( 'UTC' ) );
				$utc_date->setTimezone( $timezone );
			} catch ( Exception $e ) {
				$utc_date = Translation_Events::now();
			}
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
		return $this->utc() < Translation_Events::now();
	}

	public function print_relative_time_html() {
		echo wp_kses(
			'<time
				class="event-utc-time relative' . ( $this->is_in_the_past() ? '' : ' future' ) . '"
				datetime="' . esc_attr( $this ) . '">' . $this->get_relative_time() . '</time>',
			array(
				'span' => array(),
				'time' => array(
					'class'    => array(),
					'datetime' => array(),
				),
			)
		);
	}

	public function get_relative_time() {
		$relative = human_time_diff( $this->format( 'U' ) );
		if ( $this->is_in_the_past() ) {
			if ( '1 ' === substr( $relative, 0, 2 ) ) {
				// translators: %s: A timeframe like week or month.
				return sprintf( __( 'last %s', 'gp-translation-events' ), substr( $relative, 2 ) );
			}
			// translators: %s: A relative time like 3 weeks.
			return sprintf( __( '%s ago', 'gp-translation-events' ), $relative );
		}
		if ( '1 ' === substr( $relative, 0, 2 ) ) {
			// translators: %s: A timeframe like week or month.
			return sprintf( __( 'next %s', 'gp-translation-events' ), substr( $relative, 2 ) );
		}
			// translators: %s: A relative time like 3 weeks.
		return sprintf( __( 'in %s', 'gp-translation-events' ), $relative );
	}

	public function print_absolute_and_relative_time_html( $format = 'D, F j, Y H:i T' ) {
		echo wp_kses(
			'<time
				class="event-utc-time absolute relative' . ( $this->is_in_the_past() ? '' : ' future' ) . '"
				datetime="' . esc_attr( $this ) . '">' . $this->get_prefixed_date( $this->format( $format ) . ' (' . $this->get_relative_time() . ')' ) . '</time>',
			array(
				'span' => array(),
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
				class="event-utc-time absolute full-time"
				data-format="' . esc_attr( $format ) . '"
				datetime="' . esc_attr( $this ) . '">' . $this->format( $format ) . '</time>',
			array(
				'span' => array(),
				'time' => array(
					'class'       => array(),
					'datetime'    => array(),
					'data-format' => array(),
				),
			)
		);
	}

	/**
	 * Generate a date prefixed with a word.
	 *
	 * @param string $date The date to prefix.
	 *
	 * @return string The date text.
	 */
	abstract public function get_prefixed_date( $date ): string;

	/**
	 * Generate variable text depending on when the event starts or ends.
	 *
	 * @return string The date text.
	 */
	abstract public function get_variable_text(): string;
}

class Event_Start_Date extends Event_Date {
	public function get_prefixed_date( $date ): string {
		if ( $this->is_in_the_past() ) {
			// translators: %s: A date.
			return sprintf( __( 'started %s', 'gp-translation-events' ), '<span>' . $date . '</span>' );
		}
		// translators: %s: A date.
		return sprintf( __( 'starts %s', 'gp-translation-events' ), '<span>' . $date . '</span>' );
	}

	public function get_variable_text(): string {
		$interval       = $this->diff( Translation_Events::now() );
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

			return $this->get_prefixed_date( $this->format( 'D, F j, Y H:i T' ) );

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

			return $this->get_prefixed_date( $this->format( 'D, F j, Y H:i T' ) );
	}
}

class Event_End_Date extends Event_Date {
	public function get_prefixed_date( $date ): string {
		if ( $this->is_in_the_past() ) {
			// translators: %s: A date.
			return sprintf( __( 'ended %s', 'gp-translation-events' ), '<span>' . $date . '</span>' );
		}
		// translators: %s: A date.
		return sprintf( __( 'until %s', 'gp-translation-events' ), '<span>' . $date . '</span>' );
	}

	public function get_variable_text(): string {
		if ( $this->is_in_the_past() ) {
			return $this->get_prefixed_date( $this->format( 'D, F j, Y H:i T' ) );
		}

		$interval       = $this->diff( Translation_Events::now() );
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

			return $this->get_prefixed_date( $this->format( 'D, F j, Y H:i T' ) );
	}
}
