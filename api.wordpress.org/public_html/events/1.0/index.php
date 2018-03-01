<?php

namespace Dotorg\API\Events;
use stdClass;

/**
 * Main entry point
 */
function main() {
	global $cache_group, $cache_life;

	bootstrap();

	/*
	 * Short-circuit some requests if a traffic spike is larger than we can handle.
	 *
	 * THROTTLE_STICKY_WORDCAMPS prevents the additional `SELECT` query in `get_sticky_wordcamp()`. This is the
	 * least intrusive throttle for users, and should be tried first.
	 *
	 * If that doesn't help enough, then start throttling ip2location, since those happen automatically
	 * and are therefore less likely to be noticed by users. Throttling Geonames should be a last
	 * resort, since users will notice those the most, and will sometimes retry their requests,
	 * which makes the problem worse.
	 *
	 * THROTTLE_{ GEONAMES | IP2LOCATION }
	 * - A value of `0` means that 0% of requests will be throttled.
	 * - A value of `100` means that all cache-miss requests will be short-circuited with an error.
	 * - Any value `n` between `0` and `100` means that `n%` of cache-miss requests will be short-circuited.
	 *   e.g., `75` means that 75% of cache-miss requests will short-circuited, and 25% will processed normally.
	 *
	 * In all of the above scenarios, requests that have cached results will always be served.
	 */
	define( 'THROTTLE_STICKY_WORDCAMPS', false );
	define( 'THROTTLE_GEONAMES',         0 );
	define( 'THROTTLE_IP2LOCATION',      0 );

	defined( 'DAY_IN_SECONDS'  ) or define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
	defined( 'WEEK_IN_SECONDS' ) or define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );

	// The test suite just needs the functions defined and doesn't want any headers or output
	if ( defined( 'RUNNING_TESTS' ) && RUNNING_TESTS ) {
		return;
	}

	wp_cache_init();

	$cache_group   = 'events';
	$cache_life    = 12 * 60 * 60;
	$ttl           = 12 * 60 * 60; // Time the client should cache the document.
	$location_args = parse_request();
	$location      = get_location( $location_args );
	$response      = build_response( $location, $location_args );

	send_response( $response, $ttl );
}

/**
 * Include dependencies
 */
function bootstrap() {
	$base_dir = dirname( dirname( __DIR__ ) );

	require( $base_dir . '/init.php' );
	require( $base_dir . '/includes/hyperdb/bb-10-hyper-db.php' );
	include( $base_dir . '/includes/wp-json-encode.php' );

	if ( ! defined( 'RUNNING_TESTS' ) || ! RUNNING_TESTS ) {
		include( $base_dir . '/includes/object-cache.php' );
	}
}

/**
 * Parse and normalize the client's request
 *
 * @return array
 */
function parse_request() {
	$location_args = array();

	// If a precise location is known, use a GET request. The values here should come from the `location` key of the result of a POST request.
	if ( isset( $_GET['latitude'] ) ) {
		$location_args['latitude']  = $_GET['latitude'];
		$location_args['longitude'] = $_GET['longitude'];
	}

	if ( isset( $_GET['country'] ) ) {
		$location_args['country'] = $_GET['country'];
	}

	// If a precise location is not known, create a POST request with a bunch of data which can be used to determine a precise location for future GET requests.
	if ( isset( $_POST['location_data'] ) ) {
		$location_args = $_POST['location_data'];
	}

	// Simplified parameters for lookup by location (city) name, with optional timezone and locale params for extra context.
	if ( isset( $_REQUEST['location'] ) ) {
		$location_args['location_name'] = trim( str_replace( ',', ' ', $_REQUEST['location'] ) );
	}

	if ( isset( $_REQUEST['timezone'] ) ) {
		$location_args['timezone'] = $_REQUEST['timezone'];
	}

	if ( isset( $_REQUEST['locale'] ) ) {
		$location_args['locale'] = $_REQUEST['locale'];
	}

	if ( isset( $_REQUEST['ip'] ) ) {
		/*
		 * In local development environments, the IP sent by the Events widget will typically be
		 * private. In those cases, we can use the web server's IP address, which is the user's
		 * actual public address.
		 */
		$public_ip = filter_var(
			$_REQUEST['ip'],
			FILTER_VALIDATE_IP,
			FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);

		$location_args['ip'] = $public_ip ? $public_ip : $_SERVER['REMOTE_ADDR'];
	}

	return $location_args;
}

/**
 * Build the API's response to the client's request
 *
 * @param mixed $location      `false` if no location was found;
 *                             A string with an error code if an error occurred;
 *                             An array with location details on success.
 * @param array $location_args
 *
 * @return array
 */
function build_response( $location, $location_args ) {
	$events = array();

	if ( 'temp-request-throttled' === $location ) {
		$location = array();
		$error    = 'temp-request-throttled';
	}

	if ( $location ) {
		$event_args = array(
			'is_client_core' => is_client_core( $_SERVER['HTTP_USER_AGENT'] ),
		);

		if ( isset( $_REQUEST['number'] ) ) {
			$event_args['number'] = $_REQUEST['number'];
		}

		if ( ! empty( $location['latitude'] ) ) {
			$event_args['nearby'] = array(
				'latitude'  => $location['latitude'],
				'longitude' => $location['longitude'],
			);
		}

		if ( ! empty( $location['country'] ) ) {
			$event_args['country'] = $location['country'];
		}

		$events = get_events( $event_args );
		$events = add_regional_wordcamps( $events, $_SERVER['HTTP_USER_AGENT'] );

		// Internal location data cannot be exposed in the response, see get_location().
		if ( isset( $location['internal'] ) && $location['internal'] ) {
			// Let the client know that a location was successfully determined based on their IP
			$location = array( 'ip' => $location_args['ip'] );
		}
	} elseif ( empty( $error ) ) {
		$error = 'no_location_available';
	}

	return compact( 'error', 'location', 'events' );

}

/**
 * Determine if the client making the API request is WordPress Core.
 *
 * This can be used to limit the effects of some data processing to just the Events Widget in
 * Core. Otherwise, those changes would result in unexpected data for other clients, like
 * having WordCamps stuck to the end of the request by `stick_wordcamps()`.
 *
 * Ideally this would be isolated to Core itself, and exclude plugins using `wp_remote_get()`.
 * There isn't a good way to do that, though, so plugins will still get unexpected results.
 * They can set a custom user agent to get the raw data, though.
 *
 * @param string $user_agent
 *
 * @return bool
 */
function is_client_core( $user_agent ) {
	// This doesn't simply return the value of `strpos()` because `0` means `true` in this context
	if ( false === strpos( $user_agent, 'WordPress/' ) ) {
		return false;
	}

	return true;
}

/**
 * Send the API's response to the client's request
 *
 * @param array $response
 * @param int   $ttl
 */
function send_response( $response, $ttl ) {
	header( 'Expires: ' . gmdate( 'r', time() + $ttl ) );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Content-Type: application/json; charset=UTF-8' );

	echo wp_json_encode( $response );
}

/**
 * Guess the location based on a city inside the given input
 *
 * @param string $location_name
 * @param string $timezone
 * @param string $country_code
 *
 * @return false|object false on failure; an object on success
 */
function guess_location_from_city( $location_name, $timezone, $country_code ) {
	global $cache_group, $cache_life;

	$cache_key = 'guess_location_from_city:' . md5( $location_name . ':' . $timezone . ':' . $country_code );
	$guess     = wp_cache_get( $cache_key, $cache_group );

	if ( $guess ) {
		if ( '__NOT_FOUND__' == $guess ) {
			return false;
		}

		return $guess;
	}

	$guess = guess_location_from_geonames( $location_name, $timezone, $country_code );

	/*
	 * Multi-word queries may contain cities, regions, and countries, so try to extract just the city
	 *
	 * This won't work for most ideographic languages, because they don't use the space character as a word
	 * delimiter.
	 */
	$location_name_parts = preg_split( '/\s+/u', $location_name );
	$location_word_count = count( $location_name_parts );

	if ( ! $guess && $location_word_count >= 2 ) {
		// Catch input like "Portland Maine"
		$guess = guess_location_from_geonames( $location_name_parts[0], $timezone, $country_code, $wildcard = false );
	}

	if ( ! $guess && $location_word_count >= 3 ) {
		// Catch input like "Sao Paulo Brazil"
		$city_name = sprintf( '%s %s', $location_name_parts[0], $location_name_parts[1] );
		$guess     = guess_location_from_geonames( $city_name, $timezone, $country_code, $wildcard = false );
	}

	wp_cache_set( $cache_key, ( $guess ?: '__NOT_FOUND__' ), $cache_group, $cache_life );

	return $guess;
}

/**
 * Look for the given location in the Geonames database
 *
 * @param string $location_name
 * @param string $timezone
 * @param string $country
 *
 * @return stdClass|null
 */
function guess_location_from_geonames( $location_name, $timezone, $country, $wildcard = true ) {
	global $wpdb;
	// Look for a location that matches the name.
	// The FIELD() orderings give preference to rows that match the country and/or timezone, without excluding rows that don't match.
	// And we sort by population desc, assuming that the biggest matching location is the most likely one.

	// Exact match
	$row = $wpdb->get_row( $wpdb->prepare( "
		SELECT name, latitude, longitude, country
		FROM geoname_summary
		WHERE name = %s
		ORDER BY
			FIELD( %s, country  ) DESC,
			FIELD( %s, timezone ) DESC,
			population DESC
		LIMIT 1",
		$location_name,
		$country,
		$timezone
	) );

	// Wildcard match
	if ( ! $row && $wildcard && 'ASCII' !== mb_detect_encoding( $location_name ) ) {
		$row = $wpdb->get_row( $wpdb->prepare( "
			SELECT name, latitude, longitude, country
			FROM geoname_summary
			WHERE name LIKE %s
			ORDER BY
				FIELD( %s, country  ) DESC,
				FIELD( %s, timezone ) DESC,
				population DESC
			LIMIT 1",
			$wpdb->esc_like( $location_name ) . '%',
			$country,
			$timezone
		) );
	}

	// Suffix the "State", good in some countries (western countries) horrible in others
	// (where geonames data is not as complete, or region names are similar (but not quite the same) to city names)
	// LEFT JOIN admin1codes ac ON gs.statecode = ac.code
	// if ( $row->state && $row->state != $row->name && $row->name NOT CONTAINED WITHIN $row->state? ) {
	//	 $row->name .= ', ' . $row->state;
	// }

	return $row;
}


/**
 * Determine a location for the given IPv4 address
 *
 * NOTE: The location that is found here cannot be returned to the client.
 *       See `rebuild_location_from_geonames()`.
 *
 * @param string $dotted_ip
 *
 * @return false|object `false` on failure; an object on success
 */
function guess_location_from_ip( $dotted_ip ) {
	global $wpdb, $cache_group, $cache_life;

	$cache_key = 'guess_location_from_ip:' . $dotted_ip;
	$location  = wp_cache_get( $cache_key, $cache_group );

	if ( $location !== false ) {
		if ( '__NOT_FOUND__' == $location ) {
			return false;
		}

		return $location;
	}

	$long_ip = false;

	if ( filter_var( $dotted_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$long_ip = ip2long( $dotted_ip );
		$from    = 'ip2location';
		$where   = 'ip_to >= %d';
	} else if ( filter_var( $dotted_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		$long_ip = _ip2long_v6( $dotted_ip );
		$from    = 'ipv62location';
		$where   = "ip_to >= CAST( %s AS DECIMAL( 39, 0 ) )";
	}

	if ( false === $long_ip || ! isset( $from, $where ) ) {
		wp_cache_set( $cache_key, '__NOT_FOUND__', $cache_group, $cache_life );

		return false;
	}

	$row = $wpdb->get_row( $wpdb->prepare( "
		SELECT ip_city, ip_latitude, ip_longitude, country_short
		FROM $from
		WHERE $where
		ORDER BY ip_to ASC
		LIMIT 1",
		$long_ip
	) );

	// Unknown location:
	if ( ! $row || '-' == $row->country_short ) {
		wp_cache_set( $cache_key, '__NOT_FOUND__', $cache_group, $cache_life );

		return false;
	}

	wp_cache_set( $cache_key, $row, $cache_group, $cache_life );

	return $row;
}

/**
 * Convert an IPv6 address to an IP number than can be queried in the ip2location database.
 *
 * PHP doesn't handle integers large enough to accommodate IPv6 numbers (128 bit), so the number needs
 * to be cast as a string.
 *
 * @link https://en.wikipedia.org/wiki/IPv6
 * @link http://php.net/manual/en/language.types.integer.php
 *
 * The code in this function is based on an answer here: http://lite.ip2location.com/faqs
 *
 * Uses `inet_pton()` which correctly parses truncated IPv6 addresses such as `2a03:2880:2110:df07::`.
 * That is important, because Core will send anonymized addresses instead of complete ones.
 *
 * @access private
 *
 * @param string $address The IPv6 address to convert.
 *
 * @return string|bool `false` if invalid address. Otherwise an IP number cast as a string.
 */
function _ip2long_v6( $address ) {
	$int = inet_pton( $address );

	if ( false === $int ) {
		return false;
	}

	$bits     = 15;
	$ipv6long = 0;

	while ( $bits >= 0 ) {
		$bin = sprintf( "%08b", ( ord( $int[ $bits ] ) ) );

		if ( $ipv6long ) {
			$ipv6long = $bin . $ipv6long;
		} else {
			$ipv6long = $bin;
		}

		$bits--;
	}

	$ipv6long = gmp_strval( gmp_init( $ipv6long, 2 ), 10 );

	return $ipv6long;
}

/**
 * Determine a location for the given parameters
 *
 * @param array $args
 *
 * @return false|array|string `false` if no location was found;
 *                            A string with an error code if an error occurred;
 *                            An array with location details on success.
 */
function get_location( $args = array() ) {
	$throttle_geonames = $throttle_ip2location = false;

	// For a country request, no lat/long are returned.
	if ( isset( $args['country'] ) ) {
		$location = array(
			'country' => $args['country'],
		);
	}

	// Coordinates provided
	if (
		! $location && (
			! empty( $args['latitude'] )  && is_numeric( $args['latitude'] ) &&
			! empty( $args['longitude'] ) && is_numeric( $args['longitude'] )
		)
	) {
		$location = array(
			'description' => false,
			'latitude'    => $args['latitude'],
			'longitude'   => $args['longitude']
		);
	}

	// City was provided by the user:
	if ( ! $location && isset( $args['location_name'] ) ) {
		$throttle_geonames = mt_rand( 1, 100 ) <= THROTTLE_GEONAMES;

		if ( $throttle_geonames ) {
			return 'temp-request-throttled';
		}

		$country_code = get_country_code_from_locale( $args['locale'] ?? '' );
		$guess        = guess_location_from_city( $args['location_name'], $args['timezone'] ?? '', $country_code );

		if ( $guess ) {
			$location = array(
				'description' => $guess->name,
				'latitude'    => $guess->latitude,
				'longitude'   => $guess->longitude,
				'country'     => $guess->country,
			);
		} else {
			$guess = guess_location_from_country( $args['location_name'] );

			if ( $guess ) {
				$location = array(
					'country'     => $guess['country_short'],
					'description' => $guess['country_long'],
				);
			}
		}
	}

	if ( ! $location ) {
		if ( isset( $args['location_name'] ) || isset( $args['ip'] ) || ! empty( $args['latitude'] ) || ! empty( $args['longitude'] ) ) {
			// If any of these are specified, and no localitity was guessed based on the above checks, bail with no location.
			$location = false;
		} else {
			// No specific location details.
			$location = array();
		}
	}

	// IP:
	if ( ! $location && isset( $args['ip'] ) && ! isset( $args['location_name'] ) ) {
		$throttle_ip2location = mt_rand( 1, 100 ) <= THROTTLE_IP2LOCATION;

		if ( $throttle_ip2location ) {
			return 'temp-request-throttled';
		}

		$guess = guess_location_from_ip( $args['ip'] );

		if ( $guess ) {
			$location = array(
				'description' => $guess->ip_city,
				'latitude'    => $guess->ip_latitude,
				'longitude'   => $guess->ip_longitude,
				'country'     => $guess->country_short,

				/*
				 * ip2location's EULA forbids exposing the derived location publicly, so flag the
				 * data for internal use only.
				 */
				'internal' => true,
			);
		}
	}

	return $location;
}

/**
 * Extract the country code from the given locale
 *
 * @param string $locale
 *
 * @return string|null
 */
function get_country_code_from_locale( $locale ) {
	/*
	 * `en_US` is ignored, because it's the default locale in Core, and many users never set it. That
	 * leads to a lot of false-positives; e.g., Hampton-Sydney, Virginia, USA instead of Sydney, Australia.
	 */
	if ( empty( $locale ) || 'en_US' === $locale ) {
		return null;
	}

	preg_match( '/^[a-z]+[-_]([a-z]+)$/i', $locale, $match );

	$country_code = $match[1] ?? null;

	return $country_code;
}

/**
 * Guess the location based on a country identifier inside the given input
 *
 * This isn't perfect because some of the country names in the database are in a format that regular
 * people wouldn't type -- e.g., "Venezuela, Bolvarian Republic Of" -- but this will still match a
 * majority of them.
 *
 * Currently, this only works with English names because that's the only data we have.
 *
 * @param string $location_name
 *
 * @return false|array false on failure; an array with country details on success
 */
function guess_location_from_country( $location_name ) {
	global $cache_group, $cache_life;

	$cache_key = 'guess_location_from_country:' . $location_name;
	$country   = wp_cache_get( $cache_key, $cache_group );

	if ( $country ) {
		if ( '__NOT_FOUND__' == $country ) {
			return false;
		}

		return $country;
	}

	// Check if they entered only the country name, e.g. "Germany" or "New Zealand"
	$country             = get_country_from_name( $location_name );
	$location_word_count = str_word_count( $location_name );
	$location_name_parts = preg_split( '/\s+/u', $location_name );

	/*
	 * Multi-word queries may contain cities, regions, and countries, so try to extract just the country
	 */
	if ( ! $country && $location_word_count >= 2 ) {
		// Catch input like "Vancouver Canada"
		$country_id = $location_name_parts[ $location_word_count - 1 ];
		$country    = get_country_from_name( $country_id );
	}

	if ( ! $country && $location_word_count >= 3 ) {
		// Catch input like "Santiago De Los Caballeros, Dominican Republic"
		$country_name = sprintf(
			'%s %s',
			$location_name_parts[ $location_word_count - 2 ],
			$location_name_parts[ $location_word_count - 1 ]
		);
		$country = get_country_from_name( $country_name );
	}

	if ( ! $country && $location_word_count >= 4 ) {
		// Catch input like "Kaga-Bandoro, Central African Republic"
		$country_name = sprintf(
			'%s %s %s',
			$location_name_parts[ $location_word_count - 3 ],
			$location_name_parts[ $location_word_count - 2 ],
			$location_name_parts[ $location_word_count - 1 ]
		);
		$country = get_country_from_name( $country_name );
	}

	wp_cache_set( $cache_key, ( $country ?: '__NOT_FOUND__' ), $cache_group, $cache_life );

	return $country;
}


/**
 * Get the country that corresponds to the given country name
 *
 * @param string $country_name
 *
 * @return false|array false on failure; an array with country details on success
 */
function get_country_from_name( $country_name ) {
	global $wpdb;

	$field = 'name';

	if ( strlen( $country_name ) == 2 ) {
		$field = 'country';
	}

	return $wpdb->get_row( $wpdb->prepare( "
		SELECT
			country as country_short,
			name as country_long
		FROM countrycodes
		WHERE
			$field = %s
		LIMIT 1",
		$country_name
	), 'ARRAY_A' );
}

/**
 * Get upcoming events for the requested location.
 *
 * @param array $args
 *
 * @return array
 */
function get_events( $args = array() ) {
	global $wpdb, $cache_life, $cache_group;

	// Sort to ensure consistent cache keys.
	ksort( $args );

	// number should be between 0 and 100, with a default of 10.
	$args['number'] = $args['number'] ?? 10;
	$args['number'] = max( 0, min( $args['number'], 100 ) );

	// Distances in kilometers
	$event_distances = array(
		'meetup'   => 100,
		'wordcamp' => 400,
	);

	$cache_key = 'events:' . md5( serialize( $args ) );
	if ( false !== ( $data = wp_cache_get( $cache_key, $cache_group ) ) ) {
		return $data;
	}

	$wheres = array();
	if ( ! empty( $args['type'] ) && in_array( $args['type'], array( 'meetup', 'wordcamp' ) ) ) {
		$wheres[]     = '`type` = %s';
		$sql_values[] = $args['type'];
	}

	// If we want nearby events, create a WHERE based on a bounded box of lat/long co-ordinates.
	if ( ! empty( $args['nearby'] ) ) {
		$nearby_where = array();

		foreach ( $event_distances as $type => $distance ) {
			if ( ! empty( $args['type'] ) && $type != $args['type'] ) {
				continue;
			}

			$bounded_box    = get_bounded_coordinates( $args['nearby']['latitude'], $args['nearby']['longitude'], $distance );
			$nearby_where[] = '( `type` = %s AND `latitude` BETWEEN %f AND %f AND `longitude` BETWEEN %f AND %f )';
			$sql_values[]   = $type;
			$sql_values[]   = $bounded_box['latitude']['min'];
			$sql_values[]   = $bounded_box['latitude']['max'];
			$sql_values[]   = $bounded_box['longitude']['min'];
			$sql_values[]   = $bounded_box['longitude']['max'];
		}
		// Build the nearby where as a OR as different event types have different distances.
		$wheres[] = '(' . implode( ' OR ', $nearby_where ) . ')';
	}

	// Allow queries for limiting to specific countries.
	if ( ! empty( $args['country'] ) && preg_match( '![a-z]{2}!i', $args['country'] ) ) {
		$wheres[]     = '`country` = %s';
		$sql_values[] = $args['country'];
	}

	// Just show events that are currently scheduled (as opposed to cancelled).
	$wheres[]     = '`status` = %s';
	$sql_values[] = 'scheduled';

	// Just show upcoming events
	$wheres[] = '`date_utc` >= %s'; // Not actually UTC. WordCamp posts don't store a timezone value.

	// Dates are in local-time not UTC, so the API output will contain events that have already happened in some parts of the world.
	// TODO update this when the UTC dates are stored.
	$sql_values[] = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );

	// Limit
	if ( isset( $args['number'] ) ) {
		$sql_limits   = 'LIMIT %d';
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

	if ( should_stick_wordcamp( $args, $raw_events ) ) {
		$sticky_wordcamp = get_sticky_wordcamp( $args, $event_distances['wordcamp'] );

		if ( $sticky_wordcamp ) {
			array_pop( $raw_events );
			array_push( $raw_events, $sticky_wordcamp );
		}
	}

	$events = array();
	foreach ( $raw_events as $event ) {
		$events[] = array(
			'type'       => $event->type,
			'title'      => $event->title,
			'url'        => $event->url,
			'meetup'     => $event->meetup,
			'meetup_url' => $event->meetup_url,
			'date'       => $event->date_utc, // TODO: DB stores a local date, not UTC.
			'location'   => array(
				'location'  => $event->location,
				'country'   => $event->country,
				'latitude'  => (float) $event->latitude,
				'longitude' => (float) $event->longitude,
			)
		);
	}

	wp_cache_set( $cache_key, $events, $cache_group, $cache_life );

	return $events;
}

/**
 * Determine if conditions for sticking a WordCamp event to the response are met.
 *
 * @param array $request_args
 * @param array $raw_events
 *
 * @return bool
 */
function should_stick_wordcamp( $request_args, $raw_events ) {
	if ( THROTTLE_STICKY_WORDCAMPS ) {
		return false;
	}

	// $raw_events already contains all the events that are coming up
	if ( count( $raw_events ) < $request_args['number'] ) {
		return false;
	}

	if ( ! $request_args['is_client_core'] ) {
		return false;
	}

	$event_types = array_column( $raw_events, 'type' );

	if ( in_array( 'wordcamp', $event_types, true ) ) {
		return false;
	}

	return true;
}

/**
 * Get the WordCamp that should be stuck to the response.
 *
 * WordCamps are large, all-day (or multi-day) events that require more of attendees that meetups do. Attendees
 * need to have more advanced notice of when they're occurring. In a city with an active meetup, the camp
 * might not show up in the Events Widget until a week or two before it happens, which isn't enough time.
 *
 * @param array $request_args
 * @param int   $distance
 *
 * @return object|false A database row on success; `false` on failure.
 */
function get_sticky_wordcamp( $request_args, $distance ) {
	global $wpdb;

	$sticky_wordcamp_query = build_sticky_wordcamp_query( $request_args, $distance );
	$sticky_wordcamp       = $wpdb->get_results( $wpdb->prepare(
		$sticky_wordcamp_query['query'],
		$sticky_wordcamp_query['values']
	) );

	if ( ! empty( $sticky_wordcamp[0]->type ) ) {
		return $sticky_wordcamp[0];
	}

	return false;
}

/**
 * Build the database query for fetching the WordCamp to stick to the response.
 *
 * @param array $request_args
 * @param int   $distance
 *
 * @return array
 */
function build_sticky_wordcamp_query( $request_args, $distance ) {
	$where = $values = array();

	/*
	 * How far ahead the query should search for an upcoming camp. It should be high enough that attendees have
	 * enough time to prepare for the event, but low enough that it doesn't crowd out meetups that are happening
	 * in the mean-time, or make the content of the Events Widget feel less dynamic. Always having fresh content
	 * there is one of the things that makes the widget engaging.
	 */
	$date_upper_bound = 6 * WEEK_IN_SECONDS;

	if ( ! empty( $request_args['nearby'] ) ) {
		$bounded_box = get_bounded_coordinates( $request_args['nearby']['latitude'], $request_args['nearby']['longitude'], $distance );
		$where[]     = '( `latitude` BETWEEN %f AND %f AND `longitude` BETWEEN %f AND %f )';
		$values[]    = $bounded_box['latitude']['min'];
		$values[]    = $bounded_box['latitude']['max'];
		$values[]    = $bounded_box['longitude']['min'];
		$values[]    = $bounded_box['longitude']['max'];
	}

	// Allow queries for limiting to specific countries.
	if ( ! empty( $request_args['country'] ) && preg_match( '![a-z]{2}!i', $request_args['country'] ) ) {
		$where[]  = '`country` = %s';
		$values[] = $request_args['country'];
	}

	$where = implode( ' AND ', $where );

	$query = "
		SELECT
			`type`, `title`, `url`,
			`meetup`, `meetup_url`,
			`date_utc`, `date_utc_offset`,
			`location`, `country`, `latitude`, `longitude`
		FROM `wporg_events`
		WHERE
			`type` = 'wordcamp' AND
			$where AND
			`date_utc` >= %s AND
			`date_utc` <= %s
		ORDER BY `date_utc` ASC
		LIMIT 1"
	;

	$values[] = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
	$values[] = gmdate( 'Y-m-d', time() + $date_upper_bound );

	return compact( 'query', 'values' );
}

/**
 * Add regional WordCamps to the Events Widget in Core for extra promotion.
 *
 * @param array  $local_events
 * @param string $user_agent
 *
 * @return array
 */
function add_regional_wordcamps( $local_events, $user_agent ) {
	$time               = time();
	$regional_wordcamps = array();

	if ( ! is_client_core( $user_agent ) ) {
		return $local_events;
	}

	if ( $time <= strtotime( 'December 2nd, 2017' ) ) {
		$regional_wordcamps[] = array(
			'type'       => 'wordcamp',
			'title'      => 'WordCamp US',
			'url'        => 'https://2017.us.wordcamp.org/',
			'meetup'     => '',
			'meetup_url' => '',
			'date'       => '2017-12-01 00:00:00',

			'location' => array(
				'location'  => 'Nashville, TN, USA',
				'country'   => 'US',
				'latitude'  => 36.1566085,
				'longitude' => -86.7784909,
			)
		);
	}

	if ( $time >= strtotime( 'May 14th, 2018' ) && $time <= strtotime( 'June 15th, 2018' ) ) {
		$regional_wordcamps[] = array(
			'type'       => 'wordcamp',
			'title'      => 'WordCamp Europe',
			'url'        => 'https://2018.europe.wordcamp.org/',
			'meetup'     => '',
			'meetup_url' => '',
			'date'       => '2018-06-14 00:00:00',

			'location' => array(
				'location'  => 'Belgrade, Serbia',
				'country'   => 'RS',
				'latitude'  => 44.808497,
				'longitude' => 20.432285,
			)
		);
	}

	/**
	 * Remove duplicates events.
	 * Favor the regional event since it'll be pinned to the top.
	 */
	foreach ( $regional_wordcamps as $regional_event ) {
		foreach ( $local_events as $local_key => $local_event ) {
			if ( parse_url( $regional_event['url'], PHP_URL_HOST ) === parse_url( $local_event['url'], PHP_URL_HOST ) ) {
				unset( $local_events[ $local_key ] );
			}
		}
	}

	return array_merge( $regional_wordcamps, $local_events );
}

/**
 * Create a bounded latitude/longitude box of x KM around specific coordinates.
 *
 * @param float $lat            The latitude of the location.
 * @param float $lon            The longitude of the location.
 * @param int   $distance_in_km The distance of the bounded box, in KM.
 *
 * @return array of bounded box.
 */
function get_bounded_coordinates( $lat, $lon, $distance_in_km = 50 ) {
	// Based on http://janmatuschek.de/LatitudeLongitudeBoundingCoordinates

	$angular_distance = $distance_in_km / 6371; // 6371 = radius of the earth in KM.
	$lat              = deg2rad( $lat );
	$lon              = deg2rad( $lon );

	$earth_min_lat = -1.5707963267949; // = deg2rad(  -90 ) = -PI/2
	$earth_max_lat =  1.5707963267949; // = deg2rad(   90 ) =  PI/2
	$earth_min_lon = -3.1415926535898; // = deg2rad( -180 ) = -PI
	$earth_max_lon =  3.1415926535898; // = deg2rad(  180 ) =  PI

	$minimum_lat = $lat - $angular_distance;
	$maximum_lat = $lat + $angular_distance;
	$minimum_lon = $maximum_lon = 0;

	// Ensure that we're not within a pole-area of the world, weirdness will ensue.
	if ( $minimum_lat > $earth_min_lat && $maximum_lat < $earth_max_lat ) {
		$lon_delta   = asin( sin( $angular_distance ) / cos( $lat ) );
		$minimum_lon = $lon - $lon_delta;
		$maximum_lon = $lon + $lon_delta;

		if ( $minimum_lon < $earth_min_lon ) {
			$minimum_lon += 2 * pi();
		}

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

main();
