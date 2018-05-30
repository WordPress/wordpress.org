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
add_action(    'wp_enqueue_scripts',      __NAMESPACE__ . '\enqueue_scripts'         );
add_shortcode( 'wp15_meetup_events',      __NAMESPACE__ . '\render_events_shortcode' );

if ( ! wp_next_scheduled( 'wp15_prime_events_cache' ) ) {
	wp_schedule_event( time(), 'hourly', 'wp15_prime_events_cache' );
}


/**
 * Fetch the latest WP15 events and cache them locally.
 */
function prime_events_cache() {
	// We can assume that all celebrations will be within a few weeks of the anniversary.
	$start_date = strtotime( 'May   1, 2018' );
	$end_date   = strtotime( 'June 10, 2018' );

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
		'status' => 'upcoming,past',
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
	$relevant_keys = array_flip( array( 'id', 'event_url', 'name', 'time', 'timezone', 'group', 'location', 'latitude', 'longitude' ) );

	foreach ( $potential_events as $event ) {
		$location = array(
			isset( $event['venue']['city'] )                   ? $event['venue']['city']                   : '',
			isset( $event['venue']['localized_country_name'] ) ? $event['venue']['localized_country_name'] : ''
		);

		$event['latitude']    = ! empty( $event['venue']['lat'] ) ? $event['venue']['lat'] : $event['group']['group_lat'];
		$event['longitude']   = ! empty( $event['venue']['lon'] ) ? $event['venue']['lon'] : $event['group']['group_lon'];
		$event['group']       = $event['group']['name'];
		$event['description'] = isset( $event['description'] ) ? trim( $event['description'] ) : '';
		$event['time']        = $event['time'] / 1000;  // Convert to seconds.
		$event['location']    = trim( implode( ' ', $location ) );
		$trimmed_event        = array_intersect_key( $event, $relevant_keys );

		if ( is_wp15_event( $event['id'], $event['name'], $event['description'] ) ) {
			$wp15_events[] = $trimmed_event;
		} else {
			$other_events[] = $trimmed_event;
		}
	}

	if ( 'cli' === php_sapi_name() ) {
		$wp15_names  = wp_list_pluck( $wp15_events,  'name' );
		$other_names = wp_list_pluck( $other_events, 'name' );

		sort( $wp15_names  );
		sort( $other_names );

		echo "\nIgnored these events. Double check for false-negatives.\n\n";
		print_r( $other_names );

		echo "\nWP15 events. Double check for false-positives.\n\n";
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
	$false_positives = array( 'jlzcqlyxhbvb', '250554949', '250458066', '250441988', '250678187' );
	$keywords        = array(
		'wp15', '15 year', '15 ano', '15 año', '15 candeline', 'wordt 15', '15 yaşında',
		'anniversary', 'aniversário', 'aniversario', 'birthday', 'cumpleaños',
		'Tanti auguri'
	);

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
 * Enqueue the plugin's scripts and styles.
 */
function enqueue_scripts() {
	global $post;

	if ( ! is_a( $post, 'WP_Post' ) || 'about' !== $post->post_name ) {
		return;
	}

	wp_enqueue_style(
		'wp15-meetup-events',
		plugins_url( 'wp15-meetup-events.css', __FILE__ ),
		array(),
		filemtime( __DIR__ . '/wp15-meetup-events.css' )
	);

	wp_register_script(
		'google-maps',
		'https://maps.googleapis.com/maps/api/js?key=' . GOOGLE_MAPS_PUBLIC_KEY,
		array(),
		false,
		true
	);

	wp_enqueue_script(
		'marker-clusterer',
		plugins_url( 'libraries/marker-clusterer.min.js', __FILE__ ),
		array(),
		filemtime( __DIR__ . '/libraries/marker-clusterer.min.js' ),
		true
	);

	wp_enqueue_script(
		'wp15-meetup-events',
		plugins_url( 'wp15-meetup-events.js', __FILE__ ),
		array( 'jquery', 'underscore', 'wp-a11y', 'google-maps', 'marker-clusterer' ),
		filemtime( __DIR__ . '/wp15-meetup-events.js' ),
		true
	);

	wp_localize_script(
		'wp15-meetup-events',
		'wp15MeetupEventsData',
		array(
			'strings'     => get_js_strings(),
			'map_options' => get_map_options(),
			'events'      => get_formatted_events(),
		)
	);
}

/**
 * Internationalize strings that will be displayed via JavaScript.
 *
 * @return array
 */
function get_js_strings() {
	return array(
		'search_cleared' => __( 'Search cleared, showing all events.', 'wp15' ),
		'search_match'   => __( 'Showing events that match %s.',       'wp15' ),
	);
}

/**
 * Get the configuration for the Google Map of events.
 *
 * @return array
 */
function get_map_options() {
	return array(
		'mapContainer'            => 'wp15-events-map',
		'markerIconBaseURL'       => plugins_url( '/images/', __FILE__ ),
		'markerIcon'              => 'map-marker.svg',
		'markerIconAnchorXOffset' => 32,
		'markerIconHeight'        => 64,
		'markerIconWidth'         => 64,
		'clusterIcon'             => 'clustered-markers.png',
		'clusterIconWidth'        => 53,
		'clusterIconHeight'       => 52,
	);
}

/**
 * Format the WP15 events for presentation.
 *
 * @return array
 */
function get_formatted_events() {
	$events = get_option( 'wp15_events' );

	// This needs to be done on the fly, in order to use the date format for the visitor's locale.
	foreach ( $events as & $event ) {
		$event['time'] = get_local_formatted_date( $event['time'], $event['timezone'] );
	}
	unset( $event ); // Destroy the reference to restore expected behavior; see https://stackoverflow.com/a/4969286/450127.

	usort( $events, __NAMESPACE__ . '\sort_events' );

	return $events;
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
 * Render the WP15 events shortcode.
 */
function render_events_shortcode() {
	$events = get_formatted_events();

	ob_start();
	require_once( __DIR__ . '/views/events-map.php'  );
	require_once( __DIR__ . '/views/events-list.php' );
	return ob_get_clean();
}

/**
 * Format a UTC timestamp with respect to the local timezone.
 *
 * @param int    $utc_timestamp
 * @param string $timezone
 *
 * @return string
 */
function get_local_formatted_date( $utc_timestamp, $timezone ) {
	// translators: Do not include `T`, `P`, or `O`, because that will show the site's timezone/difference, not the event's. The event dates will already be converted to their local timezone.
	$date_format = _x( 'F jS, Y g:ia', 'WP15 event date format', 'wp15' );

	try {
		$utc_datetime = new DateTime( '@' . $utc_timestamp );
		$utc_datetime->setTimezone( new DateTimeZone( $timezone ) );

		// Convert to a timestamp in the local timezone, in order to pass through `date_i18n()` for month name translation.
		$local_timestamp = strtotime( $utc_datetime->format( 'Y-m-d H:i' ) );
		$formatted_date  = date_i18n( $date_format, $local_timestamp );
	} catch ( Exception $exception ) {
		$formatted_date = '';
	}

	return $formatted_date;
}
