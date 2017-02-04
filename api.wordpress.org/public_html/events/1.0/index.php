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
	$location_args = $_POST['location_data'];
}

// Simplified parameters for lookup by location (city) name, with optional timezone and locale params for extra context.
if ( isset( $_REQUEST['location'] ) )
	$location_args['location_name'] = $_REQUEST['location'];
if ( isset( $_REQUEST['timezone'] ) && !isset( $location_args['timezone'] ) )
	$location_args['timezone'] = $_REQUEST['timezone'];
if ( isset( $_REQUEST['locale'] ) && !isset( $location_args['locale'] ) )
	$location_args['locale'] = $_REQUEST['locale'];
if ( isset( $_REQUEST['ip'] ) && !isset( $location_args['ip'] ) )
	$location_args['ip'] = $_REQUEST['ip'];

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

header( 'Expires: ' . gmdate( 'r', time() + $ttl ) );
header( 'Access-Control-Allow-Origin: *' );
header( 'Content-Type: application/json; charset=UTF-8' );
echo wp_json_encode( compact( 'location', 'events', 'ttl', 'debug' ) );


function guess_location_from_geonames( $location_name, $timezone, $country ) {
	global $wpdb;
	// Look for a location that matches the name.
	// The FIELD() orderings give preference to rows that match the country and/or timezone, without excluding rows that don't match.
	// And we sort by population desc, assuming that the biggest matching location is the most likely one.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM geoname WHERE MATCH(name,asciiname,alternatenames) AGAINST(%s) ORDER BY FIELD(%s, country) DESC, FIELD(%s, timezone) DESC, population DESC LIMIT 1", $location_name, $country, $timezone ) );
	return $row;
}

function guess_location_from_ip( $dotted_ip, $timezone, $country ) {
	global $wpdb;

	$long_ip = ip2long( $dotted_ip );
	if ( $long_ip === false )
		return;

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ip2location WHERE ip_to >= %d ORDER BY ip_to ASC LIMIT 1", $long_ip ) );
	return $row;
}

function get_location( $args = array() ) {

	// For a country request, no lat/long are returned.
	if ( isset( $args['country'] ) ) {
		return array(
			'country'     => $args['country'],
		);
	}

	$country_code = null;
	if ( isset( $args['location_data']['locale'] ) && preg_match( '/^[a-z]+[-_]([a-z]+)$/i', $args['location_data']['locale'], $match ) ) {
		$country_code = $match[1];
	}

	// Location (City) name provided by the user:
	if ( isset( $args['location_name'] ) ) {
		$guess = guess_location_from_geonames( $args['location_name'], $args['timezone'] ?? '', $country_code );
		if ( $guess )
			return array(
				'description' => $guess->name,
				'latitude' => $guess->latitude,
				'longitude' => $guess->longitude,
				'country' => $guess->country,
			);
	}

	// IP:
	if ( isset( $args['ip'] ) ) {
		$guess = guess_location_from_ip( $args['ip'], $args['timezone'] ?? '', $country_code );
		if ( $guess ) {
			return array(
				'description' => $guess->ip_city,
				'latitude' => $guess->ip_latitude,
				'longitude' => $guess->ip_longitude,
				'country' => $guess->country_short,
				);
		}
	}
				
	if (
		! empty( $args['latitude'] )  && is_numeric( $args['latitude'] ) &&
		! empty( $args['longitude'] ) && is_numeric( $args['longitude'] )
	) {
		// TODO: Ensure that the data here is rounded to city-level and return the name of the city/region.
		return array(
			'description' => "{$args['latitude']}, {$args['longitude']}",
			'latitude'  => $args['latitude'],
			'longitude' => $args['longitude']
		);
	}

	return array(
		'description' => 'Global',
		'latitude'  => 0,
		'longitude' => 0,
	);
}

function get_events( $args = array() ) {
	global $wpdb, $cache_life, $cache_group;

	// Sort to ensure consistent cache keys.
	ksort( $args );

	// number should be between 0 and 100, with a default of 10.
	$args['number'] = $args['number'] ?? 10;
	$args['number'] = max( 0, min( $args['number'], 100 ) );

	$cache_key = 'events:' . md5( serialize( $args ) );
	if ( false !== ( $data = wp_cache_get( $cache_key, $cache_group ) ) ) {
		return $data;
	}

	$wheres = array();
	if ( !empty( $args['type'] ) && in_array( $args['type'], array( 'meetup', 'wordcamp' ) ) ) {
		$wheres[] = '`type` = %s';
		$sql_values[] = $args['type'];
	}

	// If we want nearby events, create a WHERE based on a bounded box of lat/long co-ordinates.
	if ( !empty( $args['nearby'] ) ) {
		$event_distances = array(
			'meetup' => 100,
			'wordcamp' => 350,
		);
		$nearby_where = array();
		$nearby_vals = '';
		foreach ( $event_distances as $type => $distance ) {
			if ( !empty( $args['type'] ) && $type != $args['type'] ) {
				continue;
			}
			$bounded_box = get_bounded_coordinates( $args['nearby']['latitude'], $args['nearby']['longitude'], $distance );
			$nearby_where[] = '( `type` = %s AND `latitude` BETWEEN %f AND %f AND `longitude` BETWEEN %f AND %f )';
			$sql_values[] = $type;			
			$sql_values[] = $bounded_box['latitude']['min'];
			$sql_values[] = $bounded_box['latitude']['max'];
			$sql_values[] = $bounded_box['longitude']['min'];
			$sql_values[] = $bounded_box['longitude']['max'];
		}
		// Build the nearby where as a OR as different event types have different distances.
		$wheres[] = '(' . implode( ' OR ', $nearby_where ) . ')';
	}

	// Allow queries for limiting to specific countries.
	if ( !empty( $args['country'] ) && preg_match( '![a-z]{2}!i', $args['country'] ) ) {
		$wheres[] = '`country` = %s';
		$sql_values[] = $args['country'];
	}

	// Just show upcoming events
	$wheres[] = '`date_utc` >= %s';
	// Dates are in local-time not UTC, so the API output will contain events that have already happened in some parts of the world.
	// TODO update this when the UTC dates are stored.
	$sql_values[] = gmdate( 'Y-m-d', time() - ( 24 * 60 * 60 ) );

	// Limit 
	if ( isset( $args['number'] ) ) {
		$sql_limits = 'LIMIT %d';
		$sql_values[] = $args['number'];
	}

	$sql_where = $sql_limit = '';
	if ( $wheres ) {
		$sql_where = 'WHERE ' . implode( ' AND ', $wheres );
	}

	$raw_events = $wpdb->get_results( $wpdb->prepare(
		"SELECT
			`type`, `title`, `url`,
			`meetup`, `meetup_url`,
			`date_utc`, `date_utc_offset`,
			`location`, `country`, `latitude`, `longitude`
		FROM `wporg_events`
		$sql_where
		ORDER BY `date_utc` ASC
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
			'date' => $event->date_utc, // TODO: DB stores a local date, not UTC.
			'location' => array(
				'location' => $event->location,
				'country' => $event->country,
				'latitude' => (float) $event->latitude,
				'longitude' => (float) $event->longitude,
			)
		);
	}

	wp_cache_set( $cache_key, $events, $cache_group, $cache_life );
	return $events;	
}

/**
 * Create a bounded latitude/longitude box of x KM around specific coordinates.
 *
 * @param float $lat            The latitude of the location.
 * @param float $lon            The longitude of the location.
 * @param int   $distance_in_km The distance of the bounded box, in KM.
 * @return array of bounded box.
 */
function get_bounded_coordinates( $lat, $lon, $distance_in_km = 50 ) {
	// Based on http://janmatuschek.de/LatitudeLongitudeBoundingCoordinates

	$angular_distance = $distance_in_km / 6371; // 6371 = radius of the earth in KM.
	$lat = deg2rad( $lat );
	$lon = deg2rad( $lon );

	$earth_min_lat = -1.5707963267949; // = deg2rad(  -90 ) = -PI/2
	$earth_max_lat =  1.5707963267949; // = deg2rad(   90 ) =  PI/2
	$earth_min_lon = -3.1415926535898; // = deg2rad( -180 ) = -PI
	$earth_max_lon =  3.1415926535898; // = deg2rad(  180 ) =  PI

	$minimum_lat = $lat - $angular_distance;
	$maximum_lat = $lat + $angular_distance;
	$minimum_lon = $maximum_lon = 0;

	// Ensure that we're not within a pole-area of the world, weirdness will ensure.
	if ( $minimum_lat > $earth_min_lat && $maximum_lat < $earth_max_lat ) {

		$lon_delta = asin( sin( $angular_distance ) / cos( $lat ) );

		$minimum_lon = $lon - $lon_delta;
		if ( $minimum_lon < $earth_min_lon ) {
			$minimum_lon += 2 * pi();
		}

		$maximum_lon = $lon + $lon_delta;
		if ( $maximum_lon > $earth_max_lon ) {
			$maximum_lon -= 2 * pi();
		}

	} else {
		// Use a much simpler range in polar regions.
		$minimum_lat = max( $minimum_lat, $earth_min_lat );
		$maximum_lat = min( $maximum_lat, $earth_max_lat );
		$minimum_lon = $earth_min_lon;
		$maximum_lon = $earth_max_lon;
	}

	return array(
		'latitude' => array(
			'min' => rad2deg( $minimum_lat ),
			'max' => rad2deg( $maximum_lat )
		),
		'longitude' => array(
			'min' => rad2deg( $minimum_lon ),
			'max' => rad2deg( $maximum_lon )
		)
	);
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
