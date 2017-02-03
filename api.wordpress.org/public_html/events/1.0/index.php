<?php

$base_dir = dirname( dirname(__DIR__ ) );
require( $base_dir . '/init.php' );
require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
include( $base_dir . '/includes/object-cache.php' );
include( $base_dir . '/includes/wp-json-encode.php' );

wp_cache_init();

$cache_group = 'events';
$cache_life = 12 * 60 * 60;
$ttl = 12 * 60 * 60; // Time the client should cache the document.

$location_args = array();
// If a precise location is known, use a GET request. The values here should come from the `location` key of the result of a POST request.
if ( isset( $_GET['latitude'] ) ) {
	$location_args['latitude'] = $_GET['latitude'];
	$location_args['longitude'] = $_GET['longitude'];
}
if ( isset( $_GET['country'] ) ) {
	$location_args['country'] = $_GET['country'];
}

// If a precide location is not known, create a POST request with a bunch of data which can be used to determine a precise location for future GET requests.
if ( isset( $_POST['location_data'] ) ) {
	$location_args['location_data'] = $_POST['location_data'];
}

$location = get_location( $location_args );

$event_args = array();
if ( isset( $_REQUEST['number'] ) ) {
	$event_args['number'] = $_REQUEST['number'];
}
if ( !empty( $location['latitude'] ) ) {
	$event_args['nearby'] = array(
		'latitude'  => $location['latitude'],
		'longitude' => $location['longitude'],
	);
}
if ( !empty( $location['country'] ) ) {
	$event_args['country'] = $location['country'];
}

$events = get_events( $event_args );

header( 'Content-Type: application/json; charset=UTF-8' );
echo wp_json_encode( compact( 'location', 'events', 'ttl' ) );

function get_location( $args = array() ) {

	// For a country request, no lat/long are returned.
	if ( isset( $args['country'] ) ) {
		return array(
			// TODO include a 'description' key of the country name?
			'country'     => $args['country'],
		);
	}

	// TODO: Actually determine a location for this city.
	// Support determining a users location from various user-specific data-points to provide a sane default location.
	// This data is provided by a POST request and should only be made once per user (and upon location change).
	if ( isset( $args['location_data'] ) ) {
		// $args['location_data']['ip']
		// $args['location_data']['timezone']
		// $args['location_data']['locale']
	}

	return array(
		// TODO add the human readable description for locations. Perhaps only for POST requests?
		'description' => 'Global',

		// TODO ensure we only return rounded/city-level co-ords here
		'latitude' => $location_args['latitude'] ?? 0,
		'longitude' => $location_args['longtitude'] ?? 0,
	);
}

function get_events( $args = array() ) {
	global $wpdb, $cache_life, $cache_group;

	// Sort to ensure consistent cache keys.
	ksort( $args );

	if ( isset( $args['number'] ) ) {
		$args['number'] = min( $args['number'], 100 );
		if ( ! $args['number'] ) {
			$args['number'] = 10;
		}
	}

	$cache_key = 'events:' . md5( serialize( $args ) );
/*	if ( false !== ( $data = wp_cache_get( $cache_key, $cache_group ) ) ) {
		return $data;
	}*/

	$wheres = array();
	if ( !empty( $args['type'] ) && in_array( $args['type'], array( 'meetup', 'wordcamp' ) ) ) {
		$wheres[] = 'type = %s';
		$sql_values[] = $args['type'];
	}

	if ( !empty( $args['nearby'] ) ) {
		// TODO locate events nearby to these co-ords only.
	}

	// Allow queries for limiting to specific countries.
	if ( !empty( $args['country'] ) ) {
		$wheres[] = 'country = %s';
		// TODO: Sanitize to 2-character country code?
		$sql_values[] = $args['country'];
	}

	// Just show upcoming events
	$wheres[] = 'date_utc >= %s';
	$sql_values[] = gmdate( 'Y-m-d' );

	// Limit 
	if ( !empty( $args['number'] ) ) {
		$sql_limits = 'LIMIT %d';
		$sql_values[] = $args['number'];
	}

	$sql_where = $sql_limit = '';
	if ( $wheres ) {
		$sql_where = 'WHERE ' . implode( ' AND ', $wheres );
	}

	$raw_events = $wpdb->get_results( $wpdb->prepare(
		"SELECT
			type, title, url,
			meetup, meetup_url,
			date_utc, date_utc_offset,
			location, country, latitude, longitude
		FROM `wporg_events`
		$sql_where
		ORDER BY date_utc ASC
		$sql_limits",
		$sql_values
	) );

	$events = array();
	foreach ( $raw_events as $event ) {
		$events[] = array(
			'type'  => $event->type,
			'title' => $event->title,
			'url'   => $event->url,
			'meetup' => $event->meetup,
			'meetup_url' => $event->meetup_url,
			'date' => $event->date_utc, // TODO: Create a JS parsable date, including the timezone data
			'location' => array(
				'location' => $event->location,
				// TODO: Split this into a new DB field
				'country' => $event->country ?? end( array_filter( array_map( 'trim', explode( ',', $event->location ) ) ) ),
				'latitude' => $event->latitude,
				'longitude' => $event->longitude,
			)
		);
	}

//	wp_cache_set( $cache_key, $events, $cache_group, $cache_life );
	return $events;	
}

/*
CREATE TABLE `wporg_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL DEFAULT '',
  `source_id` varchar(32) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `url` text NOT NULL,
  `description` longtext,
  `attendees` int(11) unsigned DEFAULT NULL,
  `meetup` varchar(255) DEFAULT NULL,
  `meetup_url` text,
  `date_utc` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `location` text,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_source_id` (`type`,`source_id`),
  KEY `latitude` (`latitude`),
  KEY `longitude` (`longitude`),
  KEY `date` (`date_utc`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=latin1;
*/
