<?php

namespace WordCamp\Reports\Time;
defined( 'WPINC' ) || die();

use Exception;
use DateTimeImmutable;
use WordCamp\Reports\Utility\Date_Range;

/**
 * Generate a simple array of years.
 *
 * @param int $start_year The first year in the array.
 * @param int $end_year   The last year in the array.
 *
 * @return array
 */
function year_array( int $start_year, int $end_year ) {
	return range( $start_year, $end_year, 1 );
}

/**
 * Generate an associative array of quarters, with abbreviation keys and label values.
 *
 * @return array
 */
function quarter_array() {
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
function month_array() {
	$months = array();

	foreach ( range( 1, 12 ) as $number ) {
		$months[ $number ] = date( 'F', mktime( 0, 0, 0, $number, 10 ) );
	}

	return $months;
}

/**
 * Convert a representation of a time period within a given year into a date range.
 *
 * @param int        $year   The year containing the time period.
 * @param string|int $period The time period to convert. E.g. 2, 'February', 'q1'.
 *
 * @return Date_Range An object representing the valid date range.
 * @throws Exception
 */
function convert_time_period_to_date_range( $year, $period = '' ) {
	if ( ! is_int( $year ) ) {
		throw new Exception( 'Invalid year.' );
	}

	$months = month_array();

	$start_date = '';
	$end_date   = '';

	if ( ! $period || 'all' === $period ) {
		// Period is the entire year.
		$start_date = "$year-01-01";
		$end_date   = "$year-12-31";
	} elseif ( array_key_exists( $period, quarter_array() ) ) {
		// Period is a quarter.
		switch ( $period ) {
			case 'q1' :
				$start_date = "$year-01-01";
				break;
			case 'q2' :
				$start_date = "$year-04-01";
				break;
			case 'q3' :
				$start_date = "$year-07-01";
				break;
			case 'q4' :
				$start_date = "$year-10-01";
				break;
		}

		$end_date = date( 'Y-m-d', strtotime( '+ 3 months - 1 second', strtotime( $start_date ) ) );
	} elseif ( array_key_exists( $period, $months ) || in_array( $period, $months, true ) ) {
		// Period is a specific month.
		if ( in_array( $period, $months, true ) ) {
			// Month name given. Convert it to a number.
			$period = array_search( $period, $months, true );
		}

		$start_date = "$year-$period-01";
		$end_date   = date( 'Y-m-d', strtotime( '+ 1 month - 1 second', strtotime( $start_date ) ) );
	}

	if ( ! $start_date || ! $end_date ) {
		throw new Exception( 'Invalid time period.' );
	}

	try {
		$range = new Date_Range(
			new DateTimeImmutable( $start_date ),
			new DateTimeImmutable( $end_date )
		);
	} catch ( Exception $e ) {
		throw new Exception( sprintf(
			'Invalid range: %s',
			$e->getMessage()
		) );
	}

	return $range;
}

/**
 * Change the expiration time interval based on the current date/time relative to a date range.
 *
 * @param int        $expiration A time interval in seconds.
 * @param Date_Range $range
 *
 * @return int A (possibly) modified time interval in seconds.
 * @throws Exception
 */
function modify_cache_expiration_for_date_range( $expiration, Date_Range $range ) {
	$now = new DateTimeImmutable( 'now' );
	$now->setTime( 0, 0, 0 ); // Beginning of the current day.

	if ( $range->is_within( $now ) ) {
		// Expire the cache sooner if the data includes the current day.
		$expiration = HOUR_IN_SECONDS;
	} elseif ( $range->end->diff( $now )->y > 0 ) {
		// Keep the cache longer if the end of the date range is over a year ago.
		$expiration = MONTH_IN_SECONDS;
	}

	return $expiration;
}
