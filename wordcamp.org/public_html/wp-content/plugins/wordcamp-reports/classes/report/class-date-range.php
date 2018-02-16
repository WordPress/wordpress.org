<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

/**
 * Class Date_Range
 *
 * A base report class designed to generate a data set based on a specified date range. See `Base` for some developer notes.
 *
 * @package WordCamp\Reports\Report
 */
abstract class Date_Range extends Base {
	/**
	 * The start of the date range for the report.
	 *
	 * @var \DateTime|null
	 */
	public $start_date = null;

	/**
	 * The end of the date range for the report.
	 *
	 * @var \DateTime|null
	 */
	public $end_date = null;

	/**
	 * Date_Range constructor.
	 *
	 * @param string $start_date The start of the date range for the report.
	 * @param string $end_date   The end of the date range for the report.
	 * @param array  $options    {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct for additional parameters.
	 *
	 *     @type \DateTime     $earliest_start The earliest date that can be used for the start of the date range.
	 *     @type \DateInterval $max_interval   The max interval of time between the start and end dates.
	 * }
	 */
	public function __construct( $start_date, $end_date, array $options = array() ) {
		// Date Range specific options.
		$options = wp_parse_args( $options, array(
			'earliest_start' => null,
			'max_interval'   => null,
		) );

		parent::__construct( $options );

		if ( $this->validate_date_range_inputs( $start_date, $end_date ) ) {
			$this->start_date = new \DateTime( $start_date );
			$this->end_date   = new \DateTime( $end_date );
			$now              = new \DateTimeImmutable( 'now' );

			// If the end date is more than a month in the future, limit it to the end of the current year.
			// This allows for a date range spanning Dec/Jan, but not an arbitrary date far in the future.
			if ( $this->end_date > $now &&
			     $now->diff( $this->end_date, true )->days > 31 &&
			     $this->end_date->format( 'Y' ) !== $now->format( 'Y' ) ) {
				$this->end_date->setDate( intval( $now->format( 'Y' ) ), 12, 31 );
			}

			// If the end date doesn't have a specific time, make sure
			// the entire day is included.
			if ( '00:00:00' === $this->end_date->format( 'H:i:s' ) ) {
				$this->end_date->setTime( 23, 59, 59 );
			}
		}
	}

	/**
	 * Validate the given strings for the start and end dates.
	 *
	 * @param string $start_date The start of the date range for the report.
	 * @param string $end_date   The end of the date range for the report.
	 *
	 * @return bool True if the parameters are valid. Otherwise false.
	 */
	protected function validate_date_range_inputs( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			$this->error->add( 'invalid_date', 'Please enter a valid start and end date.' );

			return false;
		}

		try {
			$start_date = new \DateTimeImmutable( $start_date ); // Immutable so methods don't modify the original object.
		} catch ( \Exception $e ) {
			$this->error->add( 'invalid_date', 'Please enter a valid start date.' );

			return false;
		}

		// No future start dates.
		if ( $start_date > date_create( 'now' ) ) {
			$this->error->add( 'future_start_date', 'Please enter a start date that is the same as or before today\'s date.' );
		}

		// Check for start date boundary.
		if ( $this->options['earliest_start'] instanceof \DateTime && $start_date < $this->options['earliest_start'] ) {
			$this->error->add( 'start_date_too_old', sprintf(
				'Please enter a start date of %s or later.',
				$this->options['earliest_start']->format( 'Y-m-d' )
			) );
		}

		try {
			$end_date = new \DateTimeImmutable( $end_date ); // Immutable so methods don't modify the original object.
		} catch ( \Exception $e ) {
			$this->error->add( 'invalid_date', 'Please enter a valid end date.' );

			return false;
		}

		// No negative date intervals.
		if ( $start_date > $end_date ) {
			$this->error->add( 'negative_date_interval', 'Please enter an end date that is the same as or after the start date.' );
		}

		// Check for date interval boundary.
		if ( $this->options['max_interval'] instanceof \DateInterval ) {
			$max_end_date = $start_date->add( $this->options['max_interval'] );

			if ( $end_date > $max_end_date ) {
				$this->error->add( 'exceeds_max_date_interval', sprintf(
					'Please enter an end date that is no more than %s days after the start date.',
					$start_date->diff( $max_end_date )->format( '%a' )
				) );
			}
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate a cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key = parent::get_cache_key() . '_' . $this->start_date->getTimestamp() . '-' . $this->end_date->getTimestamp();

		return $cache_key;
	}

	/**
	 * Generate a cache expiration interval.
	 *
	 * @return int A time interval in seconds.
	 */
	protected function get_cache_expiration() {
		$expiration = parent::get_cache_expiration();

		$now = new \DateTimeImmutable( 'now' );
		$now->setTime( 0, 0, 0 ); // Beginning of the current day.

		if ( $this->end_date >= $now ) {
			// Expire the cache sooner if the data includes the current day.
			$expiration = HOUR_IN_SECONDS;
		} elseif ( $this->end_date->diff( $now )->y > 0 ) {
			// Keep the cache longer if the end of the date range is over a year ago.
			$expiration = MONTH_IN_SECONDS;
		}

		return $expiration;
	}

	/**
	 * Generate a simple array of years.
	 *
	 * @param int $start_year The first year in the array.
	 * @param int $end_year   The last year in the array.
	 *
	 * @return array
	 */
	protected static function year_array( int $start_year, int $end_year ) {
		return range( $start_year, $end_year, 1 );
	}

	/**
	 * Generate an associative array of quarters, with abbreviation keys and label values.
	 *
	 * @return array
	 */
	protected static function quarter_array() {
		return array(
			'q1' => '1st quarter',
			'q2' => '2nd quarter',
			'q3' => '3rd quarter',
			'q4' => '4th quarter',
		);
	}

	/**
	 * Generate an associative array of months, with numerical keys and string values.
	 *
	 * @return array
	 */
	protected static function month_array() {
		$months = array();

		foreach ( range( 1, 12 ) as $number ) {
			$months[ $number ] = date( 'F', mktime( 0, 0, 0, $number, 10 ) );
		}

		return $months;
	}

	/**
	 * Convert a time period within a given year into specific start and end dates.
	 *
	 * @param int        $year   The year containing the time period.
	 * @param string|int $period The time period to convert. E.g. 2, 'February', 'q1'.
	 *
	 * @return array An associative array containing 'start_date' and 'end_date' keys with string values.
	 */
	protected static function convert_time_period_to_date_range( $year, $period = '' ) {
		$range = array(
			'start_date' => '',
			'end_date'   => '',
		);

		if ( ! is_int( $year ) ) {
			return $range;
		}

		$months = static::month_array();

		if ( ! $period || 'all' === $period ) {
			// Period is the entire year.
			$range['start_date'] = "$year-01-01";
			$range['end_date']   = "$year-12-31";
		} elseif ( in_array( $period, array( 'q1', 'q2', 'q3', 'q4' ), true ) ) {
			// Period is a quarter.
			switch ( $period ) {
				case 'q1' :
					$range['start_date'] = "$year-01-01";
					break;

				case 'q2' :
					$range['start_date'] = "$year-04-01";
					break;

				case 'q3' :
					$range['start_date'] = "$year-07-01";
					break;

				case 'q4' :
					$range['start_date'] = "$year-10-01";
					break;
			}

			$range['end_date'] = date( 'Y-m-d', strtotime( '+ 3 months - 1 second', strtotime( $range['start_date'] ) ) );
		} elseif ( array_key_exists( $period, $months ) || in_array( $period, $months, true ) ) {
			// Period is a specific month.
			if ( in_array( $period, $months, true ) ) {
				// Month name given. Convert it to a number.
				$period = array_search( $period, $months, true );
			}

			$range['start_date'] = "$year-$period-01";
			$range['end_date']   = date( 'Y-m-d', strtotime( '+ 1 month - 1 second', strtotime( $range['start_date'] ) ) );
		}

		return $range;
	}
}
