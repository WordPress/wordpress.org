<?php

namespace WordCamp\Reports\Validation;
defined( 'WPINC' ) || die();

use Exception;
use DateTime, DateTimeImmutable, DateInterval;
use WP_Post;
use WordCamp\Reports\Utility\Date_Range;
use WordCamp_Loader;

/**
 * Validate strings for start and end dates in a date range.
 *
 * @param string $start_date A string representation of the beginning of the date range.
 * @param string $end_date   A string representation of the end of the date range.
 * @param array  $config     {
 *     Optional. Modify the default configuration of the validator.
 *
 *     @type bool              $allow_future_start True to allow the date range to start in the future. Default false.
 *     @type bool              $allow_future_end   True to allow the date range to extend into the future. Default true.
 *     @type DateTime|null     $earliest_start     The earliest date that can be used for the start of the date range.
 *     @type DateInterval|null $max_interval       The maximum interval of time between the start and end dates.
 *     @type bool              $include_end_date   True to include the full ending day in the date range.
 * }
 *
 * @return Date_Range An object representing the valid date range.
 * @throws Exception
 */
function validate_date_range( $start_date, $end_date, array $config = [] ) {
	$config_defaults = [
		'allow_future_start' => false,
		'allow_future_end'   => true,
		'earliest_start'     => null,
		'max_interval'       => new DateInterval( 'P1Y' ),
		'include_end_date'   => true,
	];

	$config = wp_parse_args( $config, $config_defaults );

	if ( ! $start_date || ! $end_date ) {
		throw new Exception( 'Please enter valid start and end dates.' );
	}

	if ( $start_date instanceof DateTime ) {
		$start_date = DateTimeImmutable::createFromMutable( $start_date );
	}

	if ( ! $start_date instanceof DateTimeImmutable ) {
		try {
			$start_date = new DateTimeImmutable( $start_date ); // Immutable so methods don't modify the original object.
		} catch ( Exception $e ) {
			throw new Exception( sprintf(
				'Invalid start date: %s',
				$e->getMessage()
			) );
		}
	}

	// No future start dates.
	if ( ! $config['allow_future_start'] && $start_date > date_create( 'now' ) ) {
		throw new Exception( 'Please enter a start date that is the same as or before today\'s date.' );
	}

	// Check for start date boundary.
	if ( $config['earliest_start'] instanceof DateTime && $start_date < $config['earliest_start'] ) {
		throw new Exception( sprintf(
			'Please enter a start date of %s or later.',
			$config['earliest_start']->format( 'Y-m-d' )
		) );
	}

	if ( $end_date instanceof DateTime ) {
		$end_date = DateTimeImmutable::createFromMutable( $end_date );
	}

	if ( ! $end_date instanceof DateTimeImmutable ) {
		try {
			$end_date = new DateTimeImmutable( $end_date ); // Immutable so methods don't modify the original object.
		} catch ( Exception $e ) {
			throw new Exception( sprintf(
				'Invalid end date: %s',
				$e->getMessage()
			) );
		}
	}

	// No negative date intervals.
	if ( $start_date > $end_date ) {
		throw new Exception( 'Please enter an end date that is the same as or after the start date.' );
	}

	// Check for date interval boundary.
	if ( $config['max_interval'] instanceof DateInterval ) {
		$max_end_date = $start_date->add( $config['max_interval'] );

		if ( $end_date > $max_end_date ) {
			throw new Exception( sprintf(
				'Please enter an end date that is no more than %s days after the start date.',
				$start_date->diff( $max_end_date )->format( '%a' )
			) );
		}
	}

	// If the end date doesn't have a specific time, make sure the entire day is included.
	if ( $config['include_end_date'] && '00:00:00' === $end_date->format( 'H:i:s' ) ) {
		$end_date->setTime( 23, 59, 59 );
	}

	return new Date_Range( $start_date, $end_date );
}

/**
 * Validate a WordCamp post ID.
 *
 * @param int   $post_id The ID of a WCPT post.
 * @param array $config  {
 *     Optional. Modify the default configuration of the validator.
 *
 *     @type bool $require_site True if the WordCamp post must have an associated site in the network.
 * }
 *
 * @return array An associative array containing valid post ID and site ID integers for the WordCamp.
 * @throws Exception
 */
function validate_wordcamp_id( $post_id, array $config = [] ) {
	$config_defaults = [
		'require_site' => true,
	];

	$config = wp_parse_args( $config, $config_defaults );

	$switched = false;

	if ( BLOG_ID_CURRENT_SITE !== get_current_blog_id() ) {
		$switched = switch_to_blog( BLOG_ID_CURRENT_SITE );
	}

	$wordcamp_post = get_post( $post_id );

	if ( ! $wordcamp_post instanceof WP_Post || WCPT_POST_TYPE_ID !== get_post_type( $wordcamp_post ) ) {
		throw new Exception( 'Please enter a valid WordCamp ID.' );
	}

	$valid = [
		'post_id' => $post_id,
		'site_id' => 0,
	];

	if ( $config['require_site'] ) {
		$site_id = get_wordcamp_site_id( $wordcamp_post );

		if ( ! $site_id ) {
			throw new Exception( 'The specified WordCamp does not have a site yet.' );
		}

		$valid['site_id'] = $site_id;
	}

	if ( $switched ) {
		restore_current_blog();
	}

	return $valid;
}

/**
 * Validate a WordCamp status ID string.
 *
 * @param string $wordcamp_status A WordCamp status ID string.
 * @param array  $config          {
 *     Optional. Modify the default configuration of the validator.
 *
 *     @type array $status_subset An array of status ID strings that should be considered valid.
 * }
 *
 * @return string The validated WordCamp status ID string.
 * @throws Exception
 */
function validate_wordcamp_status( $wordcamp_status, array $config = [] ) {
	$config_defaults = [
		'status_subset' => [],
	];

	$config = wp_parse_args( $config, $config_defaults );

	$valid_statuses = array_keys( WordCamp_Loader::get_post_statuses() );
	$subset         = array_intersect( $config['status_subset'], $valid_statuses );

	if ( ! empty( $subset ) ) {
		$valid_statuses = $subset;
	}

	if ( ! in_array( $wordcamp_status, $valid_statuses, true ) ) {
		throw new Exception( 'Please enter a valid status ID.' );
	}

	return $wordcamp_status;
}
