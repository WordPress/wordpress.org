<?php

namespace WordCamp\Reports\Utility;
defined( 'WPINC' ) || die();

use Exception;
use DateTimeInterface, DateTime, DateTimeImmutable, DateInterval;

/**
 * Class Date_Range
 * @package WordCamp\Reports\Utility
 */
class Date_Range {
	/**
	 * The start date of the range.
	 *
	 * @var DateTimeImmutable|null
	 */
	public $start = null;

	/**
	 * The end date of the range.
	 *
	 * @var DateTimeImmutable|null
	 */
	public $end = null;

	/**
	 * The interval between the start and end dates.
	 *
	 * @var DateInterval|null
	 */
	public $interval = null;

	/**
	 * Date_Range constructor.
	 *
	 * @param DateTimeInterface $start
	 * @param DateTimeInterface $end
	 */
	public function __construct( DateTimeInterface $start, DateTimeInterface $end ) {
		if ( $start instanceof DateTime ) {
			$start = DateTimeImmutable::createFromMutable( $start );
		}
		$this->start = $start;

		if ( $end instanceof DateTime ) {
			$end = DateTimeImmutable::createFromMutable( $end );
		}
		$this->end = $end;

		$this->interval = $end->diff( $start );
	}

	/**
	 * Test if a date is within the range.
	 *
	 * @param DateTimeInterface $date The date to test.
	 *
	 * @return bool
	 */
	public function is_within( DateTimeInterface $date ) {
		return $date >= $this->start && $date <= $this->end;
	}

	/**
	 * Generate a standardized string representation of the date range for use in a cache key.
	 *
	 * @return string
	 */
	public function generate_cache_key_segment() {
		return $this->start->getTimestamp() . '-' . $this->end->getTimestamp();
	}

	/**
	 * Modify a cache key duration based on the date range compared to the current time.
	 *
	 * @param int $duration Duration of cache key in seconds.
	 *
	 * @return int
	 */
	public function generate_cache_duration( $duration ) {
		try {
			$now = new DateTimeImmutable( 'now' );
		} catch ( Exception $e ) {
			return $duration;
		}

		$now->setTime( 0, 0, 0 ); // Beginning of the current day.

		if ( $this->is_within( $now ) ) {
			// Expire the cache sooner if the data includes the current day.
			$duration = HOUR_IN_SECONDS;
		} elseif ( $this->end->diff( $now )->y > 0 ) {
			// Keep the cache longer if the end of the date range is over a year ago.
			$duration = MONTH_IN_SECONDS;
		}

		return $duration;
	}
}
