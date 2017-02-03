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


$location = array(
	'description' => 'Global',
	'latitude' => 0,
	'longitude' => 0,
);

$events = get_events( [ 'number' => 10 ] );

header( 'Content-Type: application/json; charset=UTF-8' );
echo wp_json_encode( compact( 'location', 'events', 'ttl' ) );

function get_events( $args = array() ) {
	global $wpdb, $cache_life, $cache_group;

	// Sort to ensure consistent cache keys.
	ksort( $args );

	$cache_key = 'events:' . md5( serialize( $args ) );
	if ( $data = wp_cache_get( $cache_key, $cache_group ) ) {
		return $data;
	}

	$wheres = array();
	if ( !empty( $args['type'] ) && in_array( $args['type'], array( 'meetup', 'wordcamp' ) ) ) {
		$wheres = 'type = %s';
		$sql_values[] = $args['type'];
	}

	// Just show upcoming events
	$wheres[] = 'date_utc >= %s';
	$sql_values[] = gmdate( 'Y-m-d' );

	// Limit 
	if ( !empty( $args['number'] ) ) {
		$sql_limits = 'LIMIT %d';
		$sql_values[] = $args['number'];
	}

	$sql_where = $ql_limit = '';
	if ( $wheres ) {
		$sql_where = 'WHERE ' . implode( ' AND ', $wheres );
	}

	$raw_events = $wpdb->get_results( $wpdb->prepare(
		"SELECT
			title, url,
			meetup, meetup_url,
			date_utc, location,
			latitude, longitude
		FROM `wporg_events`
		$sql_where
		ORDER BY date_utc ASC
		$sql_limits",
		$sql_values
	) );

	$events = array();
	foreach ( $raw_events as $event ) {
		// TODO: Switch from a TEXT location field to city, state, country for query purposes
		$location = array_filter( array_map( 'trim', explode( ',', $event->location ) ) );
		$city = $state = $country = '';
		( count( $location ) == 2 ) ? list( $city, $country ) = $location : list( $city, $state, $country ) = $location;

		$events[] = array(
			'title' => $event->title,
			'url'   => $event->url,
			'meetup' => $event->meetup,
			'meetup_url' => $event->meetup_url,
			'date' => $event->date_utc, // TODO: Mangle to JS date with timezone
			'location' => array(
				'city' => $city,
				'state' => $state,
				'country' => $country,
				'latitude' => $event->latitude,
				'longitude' => $event->longitude,
			)
		);
	}

	wp_cache_set( $cache_key, $events, $cache_group, $cache_life );
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
