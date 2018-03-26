<?php

/*
Plugin Name: WP15 Meetup Events
Description: Provides a map of all the meetup events celebrating WP's 15th anniversary.
Author:      the WordPress Meta Team
Author URI:  https://make.wordpress.org/meta
License:     GPLv2 or later
*/

namespace WP15\Meetup_Events;

use DateTime, DateTimeZone, Exception;
use WP_Error;
use WordCamp\Utilities as WordCampOrg;

defined( 'WPINC' ) || die();

add_action(    'wp15_prime_events_cache', __NAMESPACE__ . '\prime_events_cache' );
add_shortcode( 'wp15_meetup_events',      __NAMESPACE__ . '\render_events_shortcode' );

if ( ! wp_next_scheduled( 'wp15_prime_events_cache' ) ) {
	wp_schedule_event( time(), 'hourly', 'wp15_prime_events_cache' );
}


/**
 * Fetch the latest WP15 events and cache them locally.
 */
function prime_events_cache() {
	// We can assume that all celebrations will be within a week of the anniversary.
	$start_date = strtotime( 'May 21, 2018' );
	$end_date   = strtotime( 'June 2, 2018' );

	/*
	 * This data will no longer be need to be updated after the event is over. Updating it anyway would use up API
	 * resources needlessly, and introduce the risk of overwriting the valid data with invalid data if Meetup.com
	 * endpoint output changes, etc.
	 */
	if ( time() >= $end_date ) {
		return;
	}

	$potential_events = get_potential_events( $start_date, $end_date );

	if ( is_wp_error( $potential_events ) ) {
		trigger_error( $potential_events->get_error_message() );
		return;
	}

	$wp15_events = get_wp15_events( $potential_events );

	// Don't overwrite valid date if the new data is invalid.
	if ( empty( $wp15_events[0]['id'] ) || count( $wp15_events ) < 15 ) {
		trigger_error( 'Event data was invalid. Aborting.' );
		return;
	}

	update_option( 'wp15_events', $wp15_events );
}

/**
 * Get all events that might be WP15 events.
 *
 * @param int $start_date
 * @param int $end_date
 *
 * @return array|WP_Error
 */
function get_potential_events( $start_date, $end_date ) {
	require_once( __DIR__ . '/libraries/meetup-client.php' );

	$meetup_client = new WordCampOrg\Meetup_Client();
	$groups        = $meetup_client->get_groups();
	$group_ids     = wp_list_pluck( $groups, 'id' );

	$event_args = array(
		'status' => array( 'upcoming', 'past' ),
		'fields' => 'timezone',
		'time'   => sprintf(
			'%d,%d',
			$start_date * 1000,
			$end_date   * 1000
		),
	);

	$potential_events = $meetup_client->get_events( $group_ids, $event_args );

	return $potential_events;
}

/**
 * Extract the WP15 events from an array of all meetup events.
 *
 * @param array $potential_events
 *
 * @return array
 */
function get_wp15_events( $potential_events ) {
	$relevant_keys = array_flip( array( 'id', 'event_url', 'name', 'time', 'timezone', 'group' ) );

	foreach ( $potential_events as $event ) {
		$event['group']       = $event['group']['name'];
		$event['description'] = isset( $event['description'] ) ? $event['description'] : '';
		$event['time']        = $event['time'] / 1000;  // Convert to seconds.
		$trimmed_event        = array_intersect_key( $event, $relevant_keys );

		if ( is_wp15_event( $event['id'], $event['name'], $event['description'] ) ) {
			$wp15_events[] = $trimmed_event;
		} else {
			$other_events[] = $trimmed_event;
		}
	}

	if ( 'cli' == php_sapi_name() ) {
		$wp15_names  = wp_list_pluck( $wp15_events,  'name' );
		$other_names = wp_list_pluck( $other_events, 'name' );

		sort( $wp15_names  );
		sort( $other_names );

		echo "\nIgnored these events. Double check for false-negatives.\n\n";
		print_r( $other_names );

		echo "\nWP events. Double check for false-positives.\n\n";
		print_r( $wp15_names );
	}

	return $wp15_events;
}

/**
 * Determine if a meetup event is a WP15 celebration.
 *
 * @param string $id
 * @param string $title
 * @param string $description
 *
 * @return bool
 */
function is_wp15_event( $id, $title, $description ) {
	$match           = false;
	$keywords        = array( 'wp15', 'anniversary', 'birthday', '15 year', 'party', 'picnic', 'picknick' );
	$false_positives = array( '246705594' );

	if ( in_array( $id, $false_positives ) ) {
		return false;
	}

	foreach ( $keywords as $keyword ) {
		if ( false !== stripos( $description, $keyword ) || false !== stripos( $title, $keyword ) ) {
			$match = true;
			break;
		}
	}

	return $match;
}

/**
 * Render the WP15 events shortcode.
 */
function render_events_shortcode() {
	$events = get_option( 'wp15_events' );

	usort( $events, __NAMESPACE__ . '\sort_events' );

	require_once( __DIR__ . '/views/events-list.php' );
}

/**
 * Sort events by their timestamp.
 *
 * @param array $a
 * @param array $b
 *
 * @return int
 */
function sort_events( $a, $b ) {
	if ( $a['time'] === $b['time'] ) {
		return 0;
	}

	return $a['time'] > $b['time'];
}

/**
 * Format a UTC timestamp with respect to the local timezone.
 *
 * @param int    $utc_time
 * @param string $timezone
 *
 * @return string
 */
function get_local_formatted_date( $utc_time, $timezone ) {
	$date_format = _x( 'F jS, Y g:ia', 'WP15 event date format' );

	try {
		$local_timestamp = new DateTime( '@' . $utc_time );
		$local_timestamp->setTimezone( new DateTimeZone( $timezone ) );
		$local_timestamp = $local_timestamp->format( $date_format );
	} catch ( Exception $exception ) {
		$local_timestamp = '';
	}

	return $local_timestamp;
}
