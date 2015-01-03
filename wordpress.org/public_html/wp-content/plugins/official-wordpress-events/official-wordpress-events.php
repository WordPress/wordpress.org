<?php
/*
Plugin Name: Official WordPress Events
Description: Retrieves data on official WordPress events
Version:     0.1
Author:      WordPress.org Meta Team
*/

class Official_WordPress_Events {
	const WORDCAMP_API_BASE_URL = 'http://central.wordcamp.org/wp-json.php';
	const MEETUP_API_BASE_URL   = 'https://api.meetup.com/';
	const MEETUP_MEMBER_ID      = 72560962;
	const POSTS_PER_PAGE        = 50;


	/*
	 * @todo
	 *
	 * Ability to feature a camp in a hero area
	 * Add a "load more" button that retrieves more events via AJAX and updates the DOM. Have each click load the next month of events?
	 * Update WORDCAMP_API_BASE_URL to use HTTPS when central.wordcamp.org supports it
	 */


	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'official_wordpress_events', array( $this, 'render_events' ) );
	}

	/**
	 * Gather the events data and render the events template with it
	 */
	public function render_events() {
		$events = $this->get_all_events();
		
		if ( $events ) {
			require_once( __DIR__ . '/template-events.php' );
		}
	}

	/**
	 * Get all official events
	 * 
	 * @return array
	 */
	protected function get_all_events() {
		$events = array_merge( $this->get_wordcamp_events(), $this->get_meetup_events() );
		usort( $events, array( $this, 'sort_events' ) );
		
		return $events;
	}

	/**
	 * Sort events based on start timestamp 
	 * 
	 * This is a callback for usort()
	 * 
	 * @param $a
	 * @param $b
	 * @return int
	 */
	protected function sort_events( $a, $b ) {
		if ( $a->start_timestamp == $b->start_timestamp ) {
			return 0;
		} else {
			return $a->start_timestamp > $b->start_timestamp ? 1 : -1;
		}
	}

	/**
	 * Retrieve events fromm the WordCamp.org API
	 *
	 * @return array
	 */
	protected function get_wordcamp_events() {
		$events    = array();
		$response  = $this->remote_get( self::WORDCAMP_API_BASE_URL . '/posts/?type=wordcamp' );
		$wordcamps = json_decode( wp_remote_retrieve_body( $response ) );
		
		if ( $wordcamps ) {
			foreach ( $wordcamps as $wordcamp ) {
				if ( isset( $wordcamp->post_meta->{'Start Date (YYYY-mm-dd)'}[0] ) && $wordcamp->post_meta->{'Start Date (YYYY-mm-dd)'}[0] < time() ) {
					continue;
					
					// todo if https://github.com/WP-API/WP-API/pull/118 is merged, then finish register_json_query_vars() in WordCamp_Loader and filter this via the url
					// restrict it to just the upcoming month?
				}
					
				$events[] = new Official_WordPress_Event( array(
					'type'            => 'wordcamp',
					'title'           => $wordcamp->title,
					'url'             => $wordcamp->post_meta->URL[0],
					'start_timestamp' => $wordcamp->post_meta->{'Start Date (YYYY-mm-dd)'}[0],
					'end_timestamp'   => $wordcamp->post_meta->{'End Date (YYYY-mm-dd)'}[0],
					'location'        => $wordcamp->post_meta->Location[0],
				) );
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
		
		$response = $this->remote_get( sprintf(
			'%s2/events?group_id=%s&time=0,1m&page=%d&key=%s',
			self::MEETUP_API_BASE_URL,
			implode( ',', $groups ),
			self::POSTS_PER_PAGE,
			MEETUP_API_KEY
		) );

		$meetups = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! empty ( $meetups->results ) ) {
			$meetups = $meetups->results;
		} else {
			$meetups = array();
		}
		
		if ( $meetups ) {
			foreach ( $meetups as $meetup ) {
				$location        = array();
				$start_timestamp = ( $meetup->time / 1000 ) + ( $meetup->utc_offset / 1000 );    // convert to seconds
				
				foreach ( array( 'city', 'state', 'country' ) as $part ) {
					if ( ! empty( $meetup->venue->$part ) ) {
						if ( in_array( $part, array( 'state', 'country' ) ) ) {
							$location[] = strtoupper( $meetup->venue->$part );
						} else {
							$location[] = $meetup->venue->$part;
						}
					}
				}
				
				$events[] = new Official_WordPress_Event( array(
					'type'            => 'meetup',
					'title'           => $meetup->name,
					'url'             => $meetup->event_url,
					'start_timestamp' => $start_timestamp,
					'end_timestamp'   => ( empty ( $meetup->duration ) ? $start_timestamp : $start_timestamp + ( $meetup->duration / 1000 ) ),	// convert to seconds
					'location'        => empty( $location ) ? '' : implode( ' ', $location )
				) );
			}
		}

		return $events;
	}
	
	/*
	 * Gets the IDs of all of the meetup groups associated
	 * 
	 * @return array
	 */
	protected function get_meetup_group_ids() {
		if ( ! defined( 'MEETUP_API_KEY' ) || ! MEETUP_API_KEY ) {
			return array();
		}
		
		$response = $this->remote_get( sprintf(
			'%s2/profiles?&member_id=%d&key=%s',
			self::MEETUP_API_BASE_URL,
			self::MEETUP_MEMBER_ID,
			MEETUP_API_KEY
		) );

		$group_ids = json_decode( wp_remote_retrieve_body( $response ) );
	
		if ( ! empty ( $group_ids->results ) ) {
			$group_ids = wp_list_pluck( $group_ids->results, 'group' );
			$group_ids = wp_list_pluck( $group_ids, 'id' );
		}
		
		if ( ! isset( $group_ids ) || ! is_array( $group_ids ) ) {
			$group_ids = array();
		}
		
		return $group_ids;
	}

	/**
	 * Wrapper for wp_remote_get()
	 *
	 * This adds caching and error logging/notification
	 *
	 * @param string $url
	 * @param array  $args
	 * @return false|array|WP_Error False if a valid $url was not passed; otherwise the results from wp_remote_get()
	 */
	protected function remote_get( $url, $args = array() ) {
		$response = $error = false;

		if ( $url ) {
			$transient_key = 'owe_' . wp_hash( $url . print_r( $args, true ) );
			
			if ( ! $response = get_transient( $transient_key ) ) {
				$response = wp_remote_get( $url, $args );
	
				if ( is_wp_error( $response ) ) {
					$error = sprintf(
						'Recieved WP_Error message: %s; Request was to %s; Arguments were: %s',
						implode( ', ', $response->get_error_messages() ),
						$url,
						print_r( $args, true )
					);
				} elseif ( 200 != $response['response']['code'] ) {
					// trigger_error() has a message limit of 1024 bytes, so we truncate $response['body'] to make sure that $body doesn't get truncated.
	
					$error = sprintf(
						'Recieved HTTP code: %s and body: %s. Request was to: %s; Arguments were: %s',
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
				} else {
					set_transient( $transient_key, $response, HOUR_IN_SECONDS );
				}
			}
		}

		return $response;
	}
}

require_once( __DIR__ . DIRECTORY_SEPARATOR . 'official-wordpress-event.php' );
$GLOBALS['Official_WordPress_Events'] = new Official_WordPress_Events();
