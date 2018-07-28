<?php

namespace WordCamp\Reports\Utility;
defined( 'WPINC' ) || die();

use DateTimeInterface, DateInterval;

/**
 * Class Date_Range
 * @package WordCamp\Reports\Utility
 */
class Date_Range {
	/**
	 * The start date of the range.
	 *
	 * @var DateTimeInterface|null
	 */
	public $start = null;

	/**
	 * The end date of the range.
	 *
	 * @var DateTimeInterface|null
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
		$this->start    = $start;
		$this->end      = $end;
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
}
