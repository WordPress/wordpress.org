<?php

/*
Plugin Name: Official WordPress Events
Description: Retrieves data on official WordPress events
Version:     0.1
Author:      WordPress.org Meta Team
*/

class Official_WordPress_Events {
	const EVENTS_TABLE          = 'wporg_events';
	const WORDCAMP_API_BASE_URL = 'https://central.wordcamp.org/wp-json/';
	const WORDCAMP_API_VERSION  = 2;
	const MEETUP_API_BASE_URL   = 'https://api.meetup.com/';
	const MEETUP_MEMBER_ID      = 72560962;

	/*
	 * @todo
	 *
	 * Database
	 * ==============
	 * Can/should probably remove calls to geocode API in favor of using meetup v2/group or some other endpoint that returns detailed location breakdown
	 * Look at meetup-stats.php and see if any differences are relevant, or if there's anything else that'd be helpful in general
	 * Check non-latin characters, accents etc to make sure stored properly in db
	 * Add admin_notice to wordcamp post type to warn when coordinates missing. Also back-fill current ones that are missing.
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
		add_action( 'wp_enqueue_scripts',           array( $this, 'enqueue_scripts'    ) );
		add_action( 'owpe_prime_events_cache',      array( $this, 'prime_events_cache' ) );
		add_shortcode( 'official_wordpress_events', array( $this, 'render_events'      ) );

		if ( ! wp_next_scheduled( 'owpe_prime_events_cache' ) ) {
			wp_schedule_event( time(), 'hourly', 'owpe_prime_events_cache' );
		}
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
				'id'          => null,
				'type'        => $event->type,
				'source_id'   => $event->source_id,
				'title'       => $event->title,
				'url'         => $event->url,
				'description' => $event->description,
				'attendees'   => $event->num_attendees,
				'meetup'      => $event->meetup_name,
				'meetup_url'  => $event->meetup_url,
				'date_utc'    => date( 'Y-m-d H:i:s', $event->start_timestamp ),
				'end_date'    => date( 'Y-m-d H:i:s', $event->end_timestamp ),
				'location'    => $event->location,
				'country'     => $event->country_code,
				'latitude'    => $event->latitude,
				'longitude'   => $event->longitude,
			);

			// Latitude and longitude are required by the database, so skip events that don't have one
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

		wp_register_style( 'official-wordpress-events', plugins_url( 'official-wordpress-events.css', __FILE__ ), array(), 1 );

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
			$output = ob_get_flush();
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
		$raw_events = $wpdb->get_results( sprintf( "
			SELECT *
			FROM `%s`
			WHERE date_utc >= SUBDATE( CURRENT_DATE(), 1 )
			ORDER BY date_utc ASC
			LIMIT 300",
			self::EVENTS_TABLE
		) );

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
		$events = array_merge( $this->get_wordcamp_events(), $this->get_meetup_events() );

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
	 * Generate the WordCamps endpoint URL for a particular version of the REST API.
	 *
	 * @param int $api_version
	 *
	 * @return string
	 */
	protected function get_wordcamp_events_endpoint( $api_version = 1 ) {
		switch ( $api_version ) {
			case 1 :
			default :
				$request_params = array(
					'type' => 'wordcamp',
				);
				$endpoint = add_query_arg( $request_params, self::WORDCAMP_API_BASE_URL . 'posts' );
				break;

			case 2 :
				$request_params = array(
					'status'   => 'wcpt-scheduled',
					'per_page' => 100,
					// todo 100 is the built-in limit for per_page. As the number of WordCamps per year grows, we may need to increase this. See https://github.com/WP-API/WP-API/issues/2914#issuecomment-266222585
				);
				$endpoint = add_query_arg( $request_params, self::WORDCAMP_API_BASE_URL . 'wp/v2/wordcamps' );
				break;
		}

		return $endpoint;
	}

	/**
	 * Retrieve events fromm the WordCamp.org API
	 *
	 * @return array
	 */
	protected function get_wordcamp_events() {
		$endpoint = $this->get_wordcamp_events_endpoint( self::WORDCAMP_API_VERSION );
		$response = $this->remote_get( esc_url_raw( $endpoint ) );

		switch ( self::WORDCAMP_API_VERSION ) {
			case 1 :
			default :
				$events = $this->parse_wordcamp_events_api_v1( $response );
				break;

			case 2 :
				$events = $this->parse_wordcamp_events_api_v2( $response );
				break;
		}

		$this->log( sprintf( 'returning %d events', count( $events ) ) );

		return $events;
	}

	/**
	 * Parse a response from the v1 API.
	 *
	 * @param $response
	 *
	 * @return array
	 */
	protected function parse_wordcamp_events_api_v1( $response ) {
		$events    = array();
		$wordcamps = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $wordcamps ) {
			foreach ( $wordcamps as $wordcamp ) {
				if ( empty( $wordcamp->post_meta ) ) {
					continue;
				}

				$event = array(
					'type'  => 'wordcamp',
					'title' => $wordcamp->title,
				);

				foreach ( $wordcamp->post_meta as $meta_item ) {
					switch ( $meta_item->key ) {
						case 'Start Date (YYYY-mm-dd)':
							if ( empty( $meta_item->value ) || $meta_item->value < time() ) {
								// todo this can be removed when we're able to filter the request by post meta (see above)

								continue 3;
							} else {
								$event['start_timestamp'] = $meta_item->value;
							}
							break;

						case 'End Date (YYYY-mm-dd)':
							$event['end_timestamp'] = $meta_item->value;
							break;

						case 'URL':
						case 'Location':
							$event[ strtolower( $meta_item->key ) ] = $meta_item->value;
							break;
					}
				}

				if ( ! empty( $event['url'] ) ) {
					$events[] = new Official_WordPress_Event( $event );
				}
			}
		}

		return $events;
	}

	/**
	 * Parse a response from the v2 API.
	 *
	 * This does additional sorting of the returned events that the v1 parser doesn't do.
	 *
	 * @param $response
	 *
	 * @return array
	 */
	protected function parse_wordcamp_events_api_v2( $response ) {
		$events    = array();
		$wordcamps = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $wordcamps ) {
			foreach ( $wordcamps as $wordcamp ) {
				$event = array(
					'source_id'   => $wordcamp->id,
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

		if ( ! defined( 'MEETUP_API_KEY' ) || ! MEETUP_API_KEY || ! $groups = $this->get_meetup_group_ids() ) {
			return $events;
		}

		$groups = array_chunk( $groups, 200, true );

		foreach ( $groups as $group_batch ) {
			$request_url = sprintf(
				'%s2/events?group_id=%s&time=0,3m&page=%d&key=%s',
				self::MEETUP_API_BASE_URL,
				implode( ',', $group_batch ),
				200,
				MEETUP_API_KEY
			);

			while ( '' !== $request_url ) {
				$response = $this->remote_get( $request_url );
				$body     = json_decode( wp_remote_retrieve_body( $response ) );

				$this->log( 'fetching more events' );

				if ( ! empty ( $body->results ) ) {
					$meetups = $body->results;

					foreach ( $meetups as $meetup ) {
						$start_timestamp = ( $meetup->time / 1000 ) + ( $meetup->utc_offset / 1000 );    // convert to seconds

						if ( isset( $meetup->venue ) ) {
							$location = $this->format_meetup_venue_location( $meetup->venue );
						} else {
							$geocoded_location = $this->reverse_geocode( $meetup->group->group_lat, $meetup->group->group_lon );
							$location_parts    = $this->parse_reverse_geocode_address( $geocoded_location->address_components );
							$location          = sprintf(
								'%s%s%s',
								$location_parts['city'],
								empty( $location_parts['state'] )        ? '' : ', ' . $location_parts['state'],
								empty( $location_parts['country_name'] ) ? '' : ', ' . $location_parts['country_name']
							);
						}

						if ( ! empty( $meetup->venue->country ) ) {
							$country_code = $meetup->venue->country;
						} elseif ( ! empty( $location_parts['country_code'] ) ) {
							$country_code = $location_parts['country_code'];
						} else {
							$country_code = '';
						}

						$events[] = new Official_WordPress_Event( array(
							'type'            => 'meetup',
							'source_id'       => $meetup->id,
							'title'           => $meetup->name,
							'url'             => $meetup->event_url,
							'meetup_name'     => $meetup->group->name,
							'meetup_url'      => sprintf( 'https://www.meetup.com/%s/', $meetup->group->urlname ),
							'description'     => $meetup->description,
							'num_attendees'   => $meetup->yes_rsvp_count,
							'start_timestamp' => $start_timestamp,
							'end_timestamp'   => ( empty ( $meetup->duration ) ? $start_timestamp : $start_timestamp + ( $meetup->duration / 1000 ) ), // convert to seconds
							'location'        => $location,
							'country_code'    => $country_code,
							'latitude'        => empty( $meetup->venue->lat ) ? $meetup->group->group_lat : $meetup->venue->lat,
							'longitude'       => empty( $meetup->venue->lon ) ? $meetup->group->group_lon : $meetup->venue->lon,
						) );
					}
				}

				$request_url = $body->meta->next;
			}
		}

		$this->log( sprintf( 'returning %d events', count( $events ) ) );

		return $events;
	}

	/*
	 * Gets the IDs of all of the meetup groups associated
	 * 
	 * @return array
	 */
	protected function get_meetup_group_ids() {
		$group_ids = array();

		if ( ! defined( 'MEETUP_API_KEY' ) || ! MEETUP_API_KEY ) {
			return $group_ids;
		}

		$request_url = sprintf(
			'%s2/profiles?&member_id=%d&key=%s',
			self::MEETUP_API_BASE_URL,
			self::MEETUP_MEMBER_ID,
			MEETUP_API_KEY
		);

		while ( '' !== $request_url ) {
			$this->log( 'fetching more groups' );

			$response = $this->remote_get( $request_url );
			$body     = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! empty ( $body->results ) ) {
				$groups    = wp_list_pluck( $body->results, 'group' );
				$group_ids = array_merge( $group_ids, wp_list_pluck( $groups, 'id' ) );
			}

			$request_url = $body->meta->next;
		}

		$this->log( sprintf( 'returning %d groups', count( $group_ids ) ) );

		return $group_ids;
	}

	/**
	 * Reverse-geocodes a set of coordinates
	 *
	 * @param string $latitude
	 * @param string $longitude
	 *
	 * @return false | object
	 */
	protected function reverse_geocode( $latitude, $longitude ) {
		$address  = false;
		$response = $this->remote_get( sprintf(
			'https://maps.googleapis.com/maps/api/geocode/json?latlng=%s,%s&sensor=false',
			$latitude,
			$longitude
		) );

		if ( ! is_wp_error( $response ) ) {
			$this->log( 'geocode successful' );

			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( isset( $body->results[0] ) ) {
				$address = $body->results[0];
			}
		}
		else {
			$this->log( 'geocode failed' );
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

		foreach ( array( 'city', 'state', 'localized_country_name' ) as $part ) {
			if ( ! empty( $venue->$part ) ) {
				if ( in_array( $part, array( 'state' ) ) ) {
					$location[] = strtoupper( $venue->$part );
				} else {
					$location[] = $venue->$part;
				}
			}
		}

		return implode( ', ', $location );
	}

	/**
	 * Wrapper for wp_remote_get()
	 *
	 * This adds error logging/notification.
	 *
	 * @param string $url
	 * @param array  $args
	 *
	 * @return false|array|WP_Error False if a valid $url was not passed; otherwise the results from wp_remote_get()
	 */
	protected function remote_get( $url, $args = array() ) {
		$response = $error = false;

		if ( $url ) {
			$args['timeout'] = 30;
			$response        = wp_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				$error_messages = implode( ', ', $response->get_error_messages() );

				if ( false === strpos( $error_messages, 'Operation timed out' ) ) {
					$error = sprintf(
						'Received WP_Error message: %s; Request was to %s; Arguments were: %s',
						$error_messages,
						$url,
						print_r( $args, true )
					);
				}
			} elseif ( 200 != $response['response']['code'] ) {
				// trigger_error() has a message limit of 1024 bytes, so we truncate $response['body'] to make sure that $body doesn't get truncated.

				$error = sprintf(
					'Received HTTP code: %s and body: %s. Request was to: %s; Arguments were: %s',
					$response['response']['code'],
					substr( sanitize_text_field( $response['body'] ), 0, 500 ),
					$url,
					print_r( $args, true )
				);

				$response = new WP_Error( 'woe_invalid_http_response', 'Invalid HTTP response code', $response );
			}

			if ( $error ) {
				$error = preg_replace( '/&key=[a-z0-9]+/i', '&key=[redacted]', $error );
				trigger_error( sprintf( '%s error for %s: %s', __METHOD__, parse_url( site_url(), PHP_URL_HOST ), sanitize_text_field( $error ) ), E_USER_WARNING );

				if ( $to = apply_filters( 'owe_error_email_addresses', array() ) ) {
					wp_mail( $to, sprintf( '%s error for %s', __METHOD__, parse_url( site_url(), PHP_URL_HOST ) ), sanitize_text_field( $error ) );
				}
			}

			$this->maybe_pause( wp_remote_retrieve_headers( $response ) );
		}

		return $response;
	}

	/**
	 * Maybe pause the script to avoid rate limiting
	 *
	 * @param array $headers
	 */
	protected function maybe_pause( $headers ) {
		if ( ! isset( $headers['x-ratelimit-remaining'], $headers['x-ratelimit-reset'] ) ) {
			return;
		}

		$remaining = absint( $headers['x-ratelimit-remaining'] );
		$period    = absint( $headers['x-ratelimit-reset'] );

		// Pause more frequently than we need to, and for longer, just to be safe
		if ( $remaining > 2 ) {
			return;
		}

		if ( $period < 2 ) {
			$period = 2;
		}

		$this->log( 'sleeping to avoid api rate limit' );
		sleep( $period );
	}

	/**
	 * Log messages to the database
	 *
	 * To avoid storing too much data, the log is reset during each run, and only $limit rows are stored
	 *
	 * @param string $message
	 */
	protected function log( $message ) {
		$limit = 500;

		if ( ! isset( $this->log ) ) {
			$this->log = array();
		}

		if ( count( $this->log ) > $limit ) {
			wp_die('early return');
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
}

require_once( __DIR__ . DIRECTORY_SEPARATOR . 'official-wordpress-event.php' );
$GLOBALS['Official_WordPress_Events'] = new Official_WordPress_Events();
