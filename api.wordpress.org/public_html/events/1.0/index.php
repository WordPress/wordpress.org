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
	 * - A value of `0` means that 0% of requests will be throttled.
	 * - A value of `100` means that all cache-miss requests will be short-circuited with an error.
	 * - Any value `n` between `0` and `100` means that `n%` of cache-miss requests will be short-circuited.
	 *   e.g., `75` means that 75% of cache-miss requests will short-circuited, and 25% will processed normally.
	 *
	 * In all of the above scenarios, requests that have cached results will always be served.
	 */
	define( 'THROTTLE_GEONAMES',    0 );
	define( 'THROTTLE_IP2LOCATION', 0 );

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
	$base_dir = dirname( dirname(__DIR__ ) );

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
		$location_args['latitude'] = $_GET['latitude'];
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
		$event_args = array();

		if ( isset( $_REQUEST['number'] ) ) {
			$event_args['number'] = $_REQUEST['number'];
		}

		if ( ! empty( $location['latitude'] ) ) {
			$event_args['nearby'] = array(
				'latitude' => $location['latitude'],
				'longitude' => $location['longitude'],
			);
		}

		if ( ! empty( $location['country'] ) ) {
			$event_args['country'] = $location['country'];
		}

		$events = get_events( $event_args );
		$events = add_regional_wordcamps( $events, $_SERVER['HTTP_USER_AGENT'] );

		/*
		 * There are two conditions which can cause a location to not have a description:
		 * 1) When the request only passed latitude/longtude coordinates. We don't lookup
		 *    a location here because it's too expensive. See r5497.
		 * 2) When the location was determined by geolocating the IP. We don't return the
		 *    location here because it would violate the ip2location EULA. See r5491.
		 *
		 * For WP 4.8-beta1 those conditions were handled by setting "fuzzy" locations
		 * instead; the location of the first upcoming event was used, since it will be
		 * within driving distance of the location that was geolocated.
		 *
		 * After beta1 was released, though, there was a lot of feedback about the locations
		 * being too inaccurate, so we're going to try a different approach for beta2. See
		 * #40702-core.
		 *
		 * @todo Update the user-agent strings if 40702-geoip.2.diff doesn't make it into beta2
		 * @todo Remove this back-compat code after 4.8.0 has been out for a few days, to avoid
		 *       impacting the feature plugin in 4.7 installs. rebuild_location_from_event_source()
		 *       can probably be removed at that time too.
		 */
		$use_fuzzy_locations = false !== strpos( $_SERVER['HTTP_USER_AGENT'], '4.7' ) || false !== strpos( $_SERVER['HTTP_USER_AGENT'], '4.8-beta1' );
		if ( $use_fuzzy_locations ) {
			if ( empty( $location['description'] ) || ( isset( $location['internal'] ) && $location['internal'] ) ) {
				$location = rebuild_location_from_event_source( $events );
			}
		} elseif ( isset( $location['internal'] ) && $location['internal'] ) {
			// Let the client know that a location was successfully determined based on their IP
			$location = array( 'ip' => $location_args['ip'] );
		}
	} elseif ( empty( $error ) ) {
		$error = 'no_location_available';
	}

	return compact( 'error', 'location', 'events' );

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
 * Rebuild the location given to the client from the event source data
 *
 * We cannot publicly expose location data that we retrieve from the `ip2location` database, because that would
 * violate their licensing terms. We can only use the information internally, for the purposes of completing the
 * program's business logic (determining nearby events).
 *
 * Once we have nearby events, though, we can take advantage of the data that's available in the `wporg_events` table.
 * That table contains the locations details for the event's venue, which was sourced from the respective APIs
 * (WordCamp.org, Meetup.com, etc). We can return the venue's location data without violating any terms.
 *
 * See https://meta.trac.wordpress.org/ticket/2823#comment:15
 * See https://meta.trac.wordpress.org/ticket/2823#comment:21
 *
 * This isn't ideal, since the location it picks is potentially an hour's driving time from the user. If we get a
 * lot of complaints, we could potentially change this to search the `geonames` database for the name of the city
 * that was returned by the `ip2location` database. That should be more accurate, but it would require an extra
 * database lookup, and could potentially fail to return any results.
 *
 * @param array $events
 *
 * @return array|false
 */
function rebuild_location_from_event_source( $events ) {
	$location = false;

	foreach ( $events as $event ) {
		if ( ! empty( $event['location']['location'] ) && ! empty( $event['location']['latitude'] ) ) {
			$location = $event['location'];
			$location['description'] = $location['location'];
			unset( $location['location'] );

			/*
			 * If the event is a WordCamp, continue searching until a meetup is found. Meetups have a much smaller
			 * search radius in `get_events()`, so they'll be closer to the user's location. Some cities will only
			 * have WordCamps scheduled at the moment, though, so we can fall back to those.
			 */
			if ( 'meetup' === $event['type'] ) {
				break;
			}
		}
	}

	return $location;
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
	global $cache_life, $cache_group;

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
		$guess = guess_location_from_city( $args['location_name'], $args['timezone'] ?? '', $country_code  );

		if ( $guess ) {
			$location = array(
				'description' => $guess->name,
				'latitude' => $guess->latitude,
				'longitude' => $guess->longitude,
				'country' => $guess->country,
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
				'internal'    => true, // this location cannot be shared publicly, see `rebuild_location_from_geonames()`
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
		$country_id   = $location_name_parts[ $location_word_count - 1 ];
		$country      = get_country_from_name( $country_id );
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
		// Distances in kilometers
		$event_distances = array(
			'meetup' => 100,
			'wordcamp' => 400,
		);
		$nearby_where = array();

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
 * Add regional WordCamps to the Events Widget in Core for extra promotion.
 *
 * @param array  $local_events
 * @param string $user_agent
 *
 * @return array
 */
function add_regional_wordcamps( $local_events, $user_agent ) {
	$time = time();
	$regional_wordcamps = array();

	/*
	 * Limit effects to the Events Widget in Core.
	 * Otherwise this would return unexpected results to other clients.
	 *
	 * This is the closest we can get to detecting Core, so it'll still distort results for any
	 * plugins that are fetching events with `wp_remote_get()`.
	 */
	if ( false === strpos( $user_agent, 'WordPress/' ) ) {
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

main();

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
