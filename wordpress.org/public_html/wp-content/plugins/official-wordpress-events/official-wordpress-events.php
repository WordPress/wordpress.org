<?php

/*
Plugin Name: Official WordPress Events
Description: Retrieves data on official WordPress events
Version:     0.1
Author:      WordPress.org Meta Team
*/

use WordCamp\Utilities\API_Client;
use WordCamp\Utilities\Meetup_Client;

class Official_WordPress_Events {
	const EVENTS_TABLE          = 'wporg_events';
	const WORDCAMP_API_BASE_URL = 'https://central.wordcamp.org/wp-json/';
	const MEETUP_API_BASE_URL   = 'https://api.meetup.com/';
	const MEETUP_MEMBER_ID      = 72560962;
	const CACHEBUSTER           = 3;

	/*
	 * @todo
	 *
	 * Database
	 * ==============
	 * Can/should probably remove calls to geocode API in favor of using meetup v2/group or some other endpoint that returns detailed location breakdown
	 * Look at meetup-stats.php and see if any differences are relevant, or if there's anything else that'd be helpful in general
	 * Check non-latin characters, accents etc to make sure stored properly in db
	 * Store wordcamp dates in UTC, and also store timezone? Would need to start collecting timezone for wordcamps and then back-fill old records
	 *
	 *
	 * Shortcode
	 * ==============
	 * Ability to feature a camp in a hero area
	 * Add a "load more" button that retrieves more events via AJAX and updates the DOM. Have each click load the next month of events?
	 */


	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts',               array( $this, 'enqueue_scripts'       ) );
		add_action( 'init',                             array( $this, 'schedule_cron_jobs'    ) );
		add_action( 'owpe_prime_events_cache',          array( $this, 'prime_events_cache'    ) );
		add_action( 'owpe_mark_deleted_meetups',        array( $this, 'mark_deleted_meetups'  ) );
		add_action( 'api_client_handle_error_response', array( $this, 'handle_error_response' ), 10, 3 );

		add_shortcode( 'official_wordpress_events', array( $this, 'render_events'      ) );
	}

	/**
	 * Schedule cron jobs.
	 *
	 * This needs to run on the `init` action, because Cavalcade isn't fully loaded before that, and events
	 * wouldn't be scheduled.
	 *
	 * @see https://dotorg.trac.wordpress.org/changeset/15351/
	 */
	public function schedule_cron_jobs() {
		if ( ! wp_next_scheduled( 'owpe_prime_events_cache' ) ) {
			wp_schedule_event( time(), 'hourly', 'owpe_prime_events_cache' );
		}

		if ( ! wp_next_scheduled( 'owpe_mark_deleted_meetups' ) ) {
			wp_schedule_event( time(), 'hourly', 'owpe_mark_deleted_meetups' );
		}
	}

	/**
	 * Get an instance of the generic API Client, loading files first as necessary.
	 *
	 * @return API_Client
	 */
	protected function get_api_client() {
		if ( ! class_exists( '\WordCamp\Utilities\API_Client' ) ) {
			$files = array(
				'class-api-client.php',
			);

			foreach ( $files as $file ) {
				require_once trailingslashit( __DIR__ ) . "meetup/$file";
			}
		}

		return new API_Client();
	}

	/**
	 * Get an instance of the Meetup Client, loading files first as necessary.
	 *
	 * @return Meetup_Client
	 */
	protected function get_meetup_client() {
		if ( ! class_exists( '\WordCamp\Utilities\Meetup_Client' ) ) {
			$files = array(
				'class-api-client.php',
				'class-meetup-oauth2-client.php',
				'class-meetup-client.php',
			);

			foreach ( $files as $file ) {
				require_once trailingslashit( __DIR__ ) . "meetup/$file";
			}
		}

		return new Meetup_Client();
	}

	/**
	 * Prime the cache of WordPress events
	 *
	 * WARNING: The database table is used by api.wordpress.org/events/1.0 (and possibly by future versions), so
	 * be careful to maintain consistency when making any changes to this.
	 */
	public function prime_events_cache() {
		global $wpdb;

		$this->log( 'started call #' . did_action( 'owpe_prime_events_cache' ) );

		if ( did_action( 'owpe_prime_events_cache' ) > 1 ) {
			$this->log( 'Successive call detected, returning early' );
			return;
		}

		$events = $this->fetch_upcoming_events();

		$this->log( sprintf( 'looping through %d events', count( $events ) ) );

		foreach ( $events as $event ) {
			$row_values = array(
				'id'              => null,
				'type'            => $event->type,
				'source_id'       => $event->source_id,
				'status'          => $event->status,
				'title'           => $event->title,
				'url'             => $event->url,
				'description'     => $event->description,
				'attendees'       => $event->num_attendees,
				'meetup'          => $event->meetup_name,
				'meetup_url'      => $event->meetup_url,
				'date_utc'        => gmdate( 'Y-m-d H:i:s', $event->start_timestamp ),
				'end_date'        => gmdate( 'Y-m-d H:i:s', $event->end_timestamp ),
				'date_utc_offset' => $event->utc_offset,
				'location'        => $event->location,
				'country'         => $event->country_code,
				'latitude'        => $event->latitude,
				'longitude'       => $event->longitude,
			);

			// Latitude and longitude are required by the database, so skip events that don't have one.
			if ( empty( $row_values['latitude'] ) || empty( $row_values['longitude'] ) ) {
				continue;
			}

			/*
			 * Insert the events into the table, without creating duplicates
			 *
			 * Note: Since replace() is matching against a unique key rather than the primary `id` key, it's
			 * expected for each row to be deleted and re-inserted, making the IDs increment each time.
			 *
			 * See http://stackoverflow.com/a/12205366/450127
			 */
			$wpdb->replace( self::EVENTS_TABLE, $row_values );
		}

		$this->log( 'finished job' );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		global $post;

		wp_register_style(
			'official-wordpress-events',
			plugins_url( 'official-wordpress-events.css', __FILE__ ),
			array(),
			self::CACHEBUSTER
		);

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'official_wordpress_events' ) ) {
			wp_enqueue_style( 'official-wordpress-events' );
		}
	}

	/**
	 * Gather the events data and render the events template with it
	 */
	public function render_events() {
		$output = '';
		$events = $this->group_events_by_date( $this->get_cached_events() );

		if ( $events ) {
			ob_start();
			require_once( __DIR__ . '/template-events.php' );
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * Get cached events from the local database
	 *
	 * @return array
	 */
	protected function get_cached_events() {
		global $wpdb;

		$cached_events = array();

		// Include yesterday's events because server timezone may be ahead of user's timezone
		$raw_events = $wpdb->get_results( "
			SELECT *
			FROM `". self::EVENTS_TABLE ."`
			WHERE
				date_utc >= SUBDATE( CURRENT_DATE(), 1 ) AND
				status    = 'scheduled'
			ORDER BY date_utc ASC
			LIMIT 300"
		);

		foreach ( $raw_events as $event ) {
			$cached_events[] = new Official_WordPress_Event( array(
				'id'              => $event->id,
				'type'            => $event->type,
				'source_id'       => $event->source_id,
				'title'           => $event->title,
				'url'             => $event->url,
				'description'     => $event->description,
				'num_attendees'   => $event->attendees,
				'meetup_name'     => $event->meetup,
				'meetup_url'      => $event->meetup_url,
				'start_timestamp' => strtotime( $event->date_utc ),
				'end_timestamp'   => strtotime( $event->end_date ),
				'location'        => $event->location,
				'country_code'    => $event->country,
				'latitude'        => $event->latitude,
				'longitude'       => $event->longitude,
			) );
		}

		return $cached_events;
	}

	/**
	 * Fetch all upcoming official events from various external APIs
	 *
	 * @return array
	 */
	protected function fetch_upcoming_events() {
		$wordcamp_events = $this->get_wordcamp_events();
		$meetup_events   = $this->get_meetup_events();

		$events = array_merge( $wordcamp_events, $meetup_events );

		return $events;
	}

	/**
	 * Group a list of events by the date
	 *
	 * @param array $events
	 *
	 * @return array
	 */
	protected function group_events_by_date( $events ) {
		$grouped_events = array();

		foreach ( $events as $event ) {
			$grouped_events[ date( 'Y-m-d', (int) $event->start_timestamp ) ][] = $event;
		}

		// todo if event spans multiple days then it should appear on all dates

		return $grouped_events;
	}

	/**
	 * Retrieve events fromm the WordCamp.org API
	 *
	 * @return array
	 */
	protected function get_wordcamp_events() {
		$api_client = $this->get_api_client();

		// Note: With the number of WordCamps per year growing fast, we may need to batch requests in the near future, like we do for meetups.
		$request_url = add_query_arg( array(
			'status'   => array( 'wcpt-scheduled', 'wcpt-pre-planning', 'wcpt-cancelled' ),
			'per_page' => 100,
		), self::WORDCAMP_API_BASE_URL . 'wp/v2/wordcamps' );

		$response = $api_client->tenacious_remote_get( $request_url );

		$api_client->handle_error_response( $response, $request_url );

		$events = $this->parse_wordcamp_events( $response );

		$this->log( sprintf( 'returning %d events', count( $events ) ) );

		return $events;
	}

	/**
	 * Parse an event response from the WordCamp.org API.
	 *
	 * @param array $response
	 *
	 * @return array
	 */
	protected function parse_wordcamp_events( $response ) {
		$events    = array();
		$wordcamps = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $wordcamps ) {
			foreach ( $wordcamps as $wordcamp ) {
				$event = array(
					'source_id'   => $wordcamp->id,
					'status'      => str_replace( 'wcpt-', '', $wordcamp->status ),
					'type'        => 'wordcamp',
					'title'       => $wordcamp->title->rendered,
					'description' => $wordcamp->content->rendered,
				);

				foreach ( $wordcamp as $field => $value ) {
					switch ( $field ) {
						case 'Start Date (YYYY-mm-dd)':
							$value = absint( $value );
							if ( empty( $value ) || $value < strtotime( '-1 day' ) ) {
								continue 3;
							} else {
								$event['start_timestamp'] = $value;
							}
							break;

						case 'End Date (YYYY-mm-dd)':
							$value                  = absint( $value );
							$event['end_timestamp'] = $value;
							break;

						case 'URL':
							if ( empty( $value ) ) {
								continue 3;
							} else {
								$event['url'] = $value;
							}
							break;

						case 'Number of Anticipated Attendees':
							$event['num_attendees'] = $value;
							break;

						case 'Location':
							$event['location'] = $value;
							break;

						case '_venue_coordinates' :
							if ( isset( $value->latitude, $value->longitude ) ) {
								$event['latitude']  = $value->latitude;
								$event['longitude'] = $value->longitude;
							}
							break;

						case '_venue_country_code':
							$event['country_code'] = strtoupper( $value );
							break;
					}
				}

				if ( $event['start_timestamp'] && empty( $event['end_timestamp'] ) ) {
					$event['end_timestamp'] = $event['start_timestamp'];
				}

				$events[] = new Official_WordPress_Event( $event );
			}
		}

		return $events;
	}

	/**
	 * Get WordPress meetups from the Meetup.com API
	 *
	 * @return array
	 */
	protected function get_meetup_events() {
		$events = array();

		// Fetching events for a large number of groups from the Meetup API is currently a very inefficient process.
		ini_set( 'memory_limit', '900M' );
		ini_set( 'max_execution_time', 500 );

		$meetup_client = $this->get_meetup_client();
		if ( ! empty( $meetup_client->error->errors ) ) {
			$this->log( 'Failed to instantiate meetup client: ' . wp_json_encode( $meetup_client->error ), true );
			return $events;
		}

		$groups = $meetup_client->get_groups();
		if ( ! empty( $meetup_client->error->errors ) ) {
			$this->log( 'Failed to fetch groups: ' . wp_json_encode( $meetup_client->error ), true );
			return $events;
		}

		$yesterday    = date( 'c', strtotime( '-1 day' ) );
		$one_year_out = date( 'c', strtotime( '+1 year' ) );
		$meetups      = $meetup_client->get_events(
			wp_list_pluck( $groups, 'urlname' ),
			array(
				// We want cancelled events too so they will be updated in our database table.
				'status'          => 'upcoming,cancelled',
				// We don't want cancelled events in the past, but need some leeway here for timezones.
				'no_earlier_than' => substr( $yesterday, 0, strpos( $yesterday, '+' ) ),
				// We don't need to cache events happening more than a year from now.
				'no_later_than'   => substr( $one_year_out, 0, strpos( $one_year_out, '+' ) ),
			)
		);
		if ( ! empty( $meetup_client->error->errors ) ) {
			$this->log( 'Failed to fetch meetups: ' . wp_json_encode( $meetup_client->error ), true );
			return $events;
		}

		$events = $this->parse_meetup_events( $meetups );

		$this->log( sprintf( 'returning %d events', count( $events ) ) );

		return $events;
	}

	/**
	 * Parse meetup events out of a response from the Meetup API.
	 *
	 * @param array $meetups
	 *
	 * @return array
	 */
	protected function parse_meetup_events( $meetups ) {
		$events = array();

		foreach ( $meetups as $meetup ) {
			if ( empty( $meetup['id'] ) || empty( $meetup['name'] ) ) {
				$this->log( 'Malformed meetup: ' . wp_json_encode( $meetup ) );
				continue;
			}

			$start_timestamp = ( $meetup['time'] / 1000 ) + ( $meetup['utc_offset'] / 1000 ); // convert to seconds
			$latitude        = ! empty( $meetup['venue']['lat'] ) ? $meetup['venue']['lat'] : $meetup['group']['lat'];
			$longitude       = ! empty( $meetup['venue']['lon'] ) ? $meetup['venue']['lon'] : $meetup['group']['lon'];

			if ( isset( $meetup['venue'] ) ) {
				$location = $this->format_meetup_venue_location( $meetup['venue'] );
			} else {
				$geocoded_location = $this->reverse_geocode( $latitude, $longitude );
				$location_parts    = $this->parse_reverse_geocode_address( $geocoded_location );
				$location          = sprintf(
					'%s%s%s',
					$location_parts['city'] ?? '',
					empty( $location_parts['state'] )        ? '' : ', ' . $location_parts['state'],
					empty( $location_parts['country_name'] ) ? '' : ', ' . $location_parts['country_name']
				);
				$location          = trim( $location, ", \t\n\r\0\x0B" );
			}

			if ( ! empty( $meetup['venue']['country'] ) ) {
				$country_code = $meetup['venue']['country'];
			} elseif ( ! empty( $location_parts['country_code'] ) ) {
				$country_code = $location_parts['country_code'];
			} else {
				$country_code = '';
			}

			$events[] = new Official_WordPress_Event( array(
				'type'            => 'meetup',
				'source_id'       => $meetup['id'],
				'status'          => 'upcoming' === $meetup['status'] ? 'scheduled' : 'cancelled',
				'title'           => $meetup['name'],
				'url'             => $meetup['link'],
				'meetup_name'     => $meetup['group']['name'],
				'meetup_url'      => sprintf( 'https://www.meetup.com/%s/', $meetup['group']['urlname'] ),
				'description'     => $meetup['description'] ?? '',
				'num_attendees'   => $meetup['yes_rsvp_count'],
				'start_timestamp' => $start_timestamp,
				'end_timestamp'   => ( empty( $meetup['duration'] ) ? $start_timestamp : $start_timestamp + ( $meetup['duration'] / 1000 ) ), // convert to seconds.
				'utc_offset'      => $meetup['utc_offset'] / 1000, // convert to seconds.
				'location'        => $location,
				'country_code'    => $country_code,
				'latitude'        => $latitude,
				'longitude'       => $longitude,
			) );
		}

		return $events;
	}

	/**
	 * Reverse-geocodes a set of coordinates
	 *
	 * @param string $latitude
	 * @param string $longitude
	 *
	 * @return false | array
	 */
	protected function reverse_geocode( $latitude, $longitude ) {
		$address  = false;
		$cache_key       = 'geocode_' . md5( $latitude, $longitude );
		$cached_response = wp_cache_get( $cache_key, 'events' );

		if ( false !== $cached_response ) {
			return $cached_response;
		}

		// Rough attempt at avoiding rate limit.
		usleep( 75000 );

		$api_client  = $this->get_api_client();
		$request_url = sprintf(
			'https://maps.googleapis.com/maps/api/geocode/json?latlng=%s,%s&sensor=false&key=%s',
			$latitude,
			$longitude,
			OFFICIAL_WP_EVENTS_GOOGLE_MAPS_API_KEY
		);

		$response = $api_client->tenacious_remote_get( $request_url );
		$body     = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_wp_error( $response ) && isset( $body->results ) && empty( $body->error_message ) ) {
			$this->log( 'geocode successful' );

			if ( isset( $body->results[0]->address_components ) ) {
				$address = $body->results[0]->address_components;
				wp_cache_set( $cache_key, $address, 'events', 0 );
			}
		}
		else {
			$api_client->handle_error_response( $response, $request_url );
			$this->log( 'geocode failed: ' . wp_json_encode( $response ) );
		}

		return $address;
	}

	/**
	 * Parses an address returned from Google's reverse-geocode API
	 *
	 * @param array $address_components
	 *
	 * @return array
	 */
	protected function parse_reverse_geocode_address( $address_components ) {
		$address = array();

		if ( empty( $address_components ) ) {
			return $address;
		}

		foreach ( $address_components as $component ) {
			if ( 'locality' == $component->types[0] ) {
				$address['city'] = $component->short_name;

			} elseif ( 'administrative_area_level_1' == $component->types[0] ) {
				$address['state'] = $component->short_name;

			} elseif ( 'country' == $component->types[0] ) {
				$address['country_code'] = strtoupper( $component->short_name );
				$address['country_name'] = $component->long_name;
			}
		}

		return $address;
	}

	/**
	 * Format a meetup venue's location
	 *
	 * @param object $venue
	 *
	 * @return string
	 */
	protected function format_meetup_venue_location( $venue ) {
		$location = array();

		if ( isset( $venue['id'] ) && 26906060 === $venue['id'] ) {
			return 'online';
		}

		foreach ( array( 'city', 'state', 'localized_country_name' ) as $part ) {
			if ( ! empty( $venue[ $part ] ) ) {
				if ( in_array( $part, array( 'state' ) ) ) {
					$location[] = strtoupper( $venue[ $part ] );
				} else {
					$location[] = $venue[ $part ];
				}
			}
		}

		return implode( ', ', $location );
	}

	/**
	 * Mark Meetup events as deleted in our database when they're deleted from Meetup.com.
	 *
	 * Meetup.com allows organizers to either cancel or delete events. If the event is cancelled, then the status
	 * in our database will be updated the next time `prime_events_cache` runs. If the event is deleted, though,
	 * it is removed from their API results, so `prime_events_cache` won't see it, and the status will remain
	 * `scheduled`.
	 *
	 * This checks all the upcoming Meetup.com events to see if any of them are missing. If they are, it assumes
	 * that they were deleted, and updates their status.
	 */
	public function mark_deleted_meetups() {
		global $wpdb;

		$chunked_db_events = array();

		// Don't include anything before tomorrow, because time zone differences could result in past events being flagged.
		$raw_events = $wpdb->get_results( "
			SELECT id, source_id, meetup_url
			FROM `". self::EVENTS_TABLE ."`
			WHERE
				type      = 'meetup'    AND
				status    = 'scheduled' AND
				date_utc >= DATE_ADD( NOW(), INTERVAL 24 HOUR )
			LIMIT 5000
		" );

		foreach ( $raw_events as $event ) {
			$chunked_db_events[ $event->meetup_url ][] = $event;
		}

		$meetup_client = $this->get_meetup_client();

		if ( ! empty( $meetup_client->error->errors ) ) {
			$this->log( 'Failed to instantiate meetup client: ' . wp_json_encode( $meetup_client->error ), true );
			return;
		}

		foreach ( $chunked_db_events as $group_url => $db_events ) {
			$url_name = trim( wp_parse_url( $group_url, PHP_URL_PATH ), '/' );

			$events = $meetup_client->get_group_events(
				$url_name,
				array(
					'status' => 'upcoming,cancelled',
				)
			);

			// Make sure we have a valid API response, to avoid marking events as deleted just because the request failed.
			if ( is_wp_error( $events ) ) {
				continue;
			}

			$api_events = wp_list_pluck( $events, 'id' );

			foreach ( $db_events as $db_event ) {
				// If the event is still appearing in the Meetup.com API results, it hasn't been deleted.
				if ( in_array( $db_event->source_id, $api_events, true ) ) {
					continue;
				}

				// The event is missing from a valid response, so assume that it's been deleted.
				$wpdb->update( self::EVENTS_TABLE, array( 'status' => 'deleted' ), array( 'id' => $db_event->id ) );

				$this->log( "Marked {$db_event->source_id} as deleted." );
			}
		}
	}

	/**
	 * Error logging and notification.
	 *
	 * Hooked to `api_client_handle_error_response`.
	 *
	 * @param array|WP_Error $response
	 *
	 * @return void
	 */
	public function handle_error_response( $response, $request_url, $request_args ) {
		$error = null;

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body    = wp_remote_retrieve_body( $response );

		if ( is_wp_error( $response ) ) {
			$error_messages = implode( ', ', $response->get_error_messages() );

			if ( false === strpos( $error_messages, 'Operation timed out' ) ) {
				$error = sprintf(
					'Received WP_Error message: %s; Request was to %s; Arguments were: %s',
					$error_messages,
					$request_url,
					print_r( $request_args, true )
				);
			}
		} elseif ( 200 !== $response_code ) {
			// trigger_error() has a message limit of 1024 bytes, so we truncate $response['body'] to make sure that $body doesn't get truncated.
			$error = sprintf(
				"HTTP Code: %d\nMessage: %s\nBody: %s\nRequest URL: %s\nArgs: %s",
				$response_code,
				sanitize_text_field( $response_message ),
				substr( sanitize_text_field( $response_body ), 0, 500 ),
				$request_url,
				print_r( $request_args, true )
			);
		}

		if ( $error ) {
			$error = preg_replace( '/&key=[a-z0-9]+/i', '&key=[redacted]', $error );

			$this->log( sanitize_text_field( $error ), true );

			trigger_error( sprintf(
				'%s error for %s: %s',
				__METHOD__,
				parse_url( site_url(), PHP_URL_HOST ),
				sanitize_text_field( $error )
			), E_USER_WARNING );

			$to = apply_filters( 'owe_error_email_addresses', array() );

			if ( $to && ( ! defined( 'WPORG_SANDBOXED_REQUEST' ) || ! WPORG_SANDBOXED_REQUEST ) ) {
				wp_mail(
					$to,
					sprintf(
						'%s error for %s',
						__METHOD__,
						parse_url( site_url(), PHP_URL_HOST )
					),
					sanitize_text_field( $error )
				);
			}
		}
	}

	/**
	 * Log messages to the database
	 *
	 * To avoid storing too much data, the log is reset during each run, and only $limit rows are stored
	 *
	 * @todo Remove this when the stuck cron job bug is fixed
	 *
	 * @param string $message
	 * @param bool   $write_to_disk If true, writes the message to the standard error log in addition to the
	 *                              `owpe_log` option.
	 */
	protected function log( $message, $write_to_disk = false ) {
		$limit = 500;
		$api_keys = array( MEETUP_API_KEY, OFFICIAL_WP_EVENTS_GOOGLE_MAPS_API_KEY );

		if ( 'cli' === php_sapi_name() ) {
			echo $message;
		}

		if ( $write_to_disk ) {
			error_log( sprintf(
				/*
				 * Use the folder name as a prefix so that Slack searches/highlights will match this and log
				 * entries generated by PHP itself.
				 */
				'official-wordpress-events: %s',
				str_replace( $api_keys, '[redacted]', $message )
			) );
		}

		if ( ! isset( $this->log ) ) {
			$this->log = array();
		}

		if ( count( $this->log ) > $limit ) {
			return;
		}

		$backtrace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 2 );

		$this->log[] = sprintf(
			'%s - %s MB - %s - %s',
			microtime( true ),
			number_format( memory_get_usage( true ) / 1024 / 2014, 2 ),
			$backtrace[1]['function'],
			$message
		);

		if ( $limit === count( $this->log ) ) {
			$this->log[] = array( 'Reached log limit, assuming some kind of infinite loop. Will not log any more messages.' );
		}

		update_option( 'owpe_log', $this->log, false );
	}

	/**
	 * Prune an HTTP response so the relevant data can be logged for troubleshooting
	 *
	 * @todo Remove this when the stuck cron job bug is fixed
	 *
	 * @param WP_HTTP_Response $response
	 *
	 * @return array
	 */
	protected function prune_response_for_log( $response ) {
		$pruned_response = (array) $response;

		if ( isset( $pruned_response['body'] ) ) {
			$pruned_response['original_body_meta'] = sprintf(
				'type: %s / length: %s',
				gettype( $pruned_response['body'] ),
				is_string( $pruned_response['body'] ) ? strlen( $pruned_response['body'] ) : 'not a string'
			);
			$pruned_response['body'] = (array) json_decode( $pruned_response['body'] );

			if ( isset( $pruned_response['body']['results'] ) ) {
				$pruned_response['body']['results'] = 'pruned';
			}
		}

		if ( isset( $pruned_response['http_response'] ) ) {
			unset( $pruned_response['http_response'] );
		}

		if ( isset( $pruned_response['cookies'] ) ) {
			unset( $pruned_response['cookies'] );
		}

		if ( isset( $pruned_response['filename'] ) ) {
			unset( $pruned_response['filename'] );
		}

		return $pruned_response;
	}
}

require_once( __DIR__ . DIRECTORY_SEPARATOR . 'official-wordpress-event.php' );
require_once( __DIR__ . DIRECTORY_SEPARATOR . 'official-events-online.php' );
$GLOBALS['Official_WordPress_Events'] = new Official_WordPress_Events();
