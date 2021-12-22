<?php
namespace WordCamp\Utilities;

use DateTimeInterface, DateTimeImmutable, DateTimeZone, DateInterval;
use WP_Error;

defined( 'WPINC' ) || die();

/**
 * Class Meetup_Client
 *
 * Important: This class and its dependency classes are used in multiple locations in the WordPress/WordCamp
 * ecosystem. Because of complexities around SVN externals and the reliability of GitHub's SVN bridge during deploys,
 * it was decided to maintain multiple copies of these files rather than have SVN externals pointing to one canonical
 * source.
 *
 * If you make changes to this file, make sure they are propagated to the other locations:
 *
 * - wordcamp: wp-content/mu-plugins/utilities
 * - wporg: wp-content/plugins/official-wordpress-events/meetup
 */
class Meetup_Client extends API_Client {

	/**
	 * @var int The Venue ID for online events.
	 */
	const ONLINE_VENUE_ID = 26906060;

	/**
	 * @var string The URL for the API endpoints.
	 */
	protected $api_url = 'https://api.meetup.com/gql';

	/**
	 * @var string The GraphQL field that must be present for pagination to work.
	 */
	public $pageInfo = 'pageInfo { hasNextPage endCursor }';

	/**
	 * @var Meetup_OAuth2_Client|null
	 */
	protected $oauth_client = null;

	/**
	 * @var bool If true, the client will fetch fewer results, for faster debugging.
	 */
	protected $debug = false;

	/**
	 * Meetup_Client constructor.
	 *
	 * @param array $settings {
	 *     Optional. Settings for the client.
	 *
	 *     @type bool $debug If true, the client will fetch fewer results, for faster debugging.
	 * }
	 */
	public function __construct( array $settings = [] ) {
		parent::__construct( array(
			/*
			 * Response codes that should break the request loop.
			 *
			 * See https://www.meetup.com/meetup_api/docs/#errors.
			 *
			 * `200` (ok) is not in the list, because it needs to be handled conditionally.
			 *  See API_Client::tenacious_remote_request.
			 *
			 * `400` (bad request) is not in the list, even though it seems like it _should_ indicate an unrecoverable
			 * error. In practice we've observed that it's common for a seemingly valid request to be rejected with
			 * a `400` response, but then get a `200` response if that exact same request is retried.
			 */
			'breaking_response_codes' => array(
				// TODO: NOTE: These headers are not returned from the GraphQL API, every request is 200 even if throttled.
				401, // Unauthorized (invalid key).
				429, // Too many requests (rate-limited).
				404, // Unable to find group

				503, // Timeout between API cache & GraphQL Server.
			),
			// NOTE: GraphQL does not expose the Quota Headers.
			'throttle_callback'       => array( __CLASS__, 'throttle' ),
		) );

		$settings = wp_parse_args(
			$settings,
			array(
				'debug' => false,
			)
		);

		$this->debug = $settings['debug'];

		if ( $this->debug ) {
			self::cli_message( "Meetup Client debug is on. Results will be truncated." );
		}

		$this->oauth_client = new Meetup_OAuth2_Client;

		if ( ! empty( $this->oauth_client->error->get_error_messages() ) ) {
			$this->error = $this->merge_errors( $this->error, $this->oauth_client->error );
		}

		add_action( 'api_client_tenacious_remote_request_attempt', array( $this, 'maybe_reset_oauth_token' ) );
	}

	/**
	 * Attempt to fix authorization errors before they permanently fail.
	 *
	 * Hooked to `api_client_tenacious_remote_request_attempt` so that a request that has failed due to an invalid
	 * oauth token can be retried after resetting the token.
	 *
	 * @param array $response
	 *
	 * @return void
	 */
	public function maybe_reset_oauth_token( $response ) {
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$parsed_error = $this->parse_error( $body );

		if (
			( 400 === $code && $parsed_error->get_error_message( 'invalid_grant' ) )
			|| ( 401 === $code && $parsed_error->get_error_message( 'auth_fail' ) )
		) {
			$this->oauth_client->reset_oauth_token();

			if ( ! empty( $this->oauth_client->error->get_error_messages() ) ) {
				$this->error = $this->merge_errors( $this->error, $this->oauth_client->error );
			}

			// Reset the request headers, so that they include the new oauth token.
			$this->current_request_args = $this->get_request_args();
		}
	}

	/**
	 * Send a paginated request to the Meetup API and return the aggregated response.
	 *
	 * This automatically paginates requests and will repeat requests to ensure all results are retrieved.
	 * For pagination to work, $this->pageInfo must be present within the string, and a 'cursor' variable defined.
	 *
	 * @param string $request_url The API endpoint URL to send the request to.
	 * @param array  $variables   The Query variables used in the query.
	 *
	 * @return array|WP_Error The results of the request.
	 */
	public function send_paginated_request( $query, $variables = null ) {
		$data = array();

		$has_next_page        = false;
		$is_paginated_request = ! empty( $variables ) &&
			array_key_exists( 'cursor', $variables ) &&
			false !== stripos( $query, $this->pageInfo );

		do {
			$request_args = $this->get_request_args( $query, $variables );
			$response     = $this->tenacious_remote_post( $this->api_url, $request_args );

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$this->handle_error_response( $response, $this->api_url, $request_args );
				break;
			}

			$new_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! empty( $new_data['error'] ) ) {
				$this->handle_error_response( $response, $this->api_url, $request_args );
				break;
			}

			if ( ! is_array( $new_data ) || ! isset( $new_data['data'] ) ) {
				$this->error->add(
					'unexpected_response_data',
					'The API response did not provide the expected data format.',
					$response
				);
				break;
			}

			// Merge the data, overwriting scalar values (they should be the same), and merging arrays.
			$data = ! $data ? $new_data : $this->array_merge_recursive_numeric_arrays(
				$data,
				$new_data
			);

			// Pagination - Find the values inside the 'pageInfo' key.
			if ( $is_paginated_request ) {
				$has_next_page = false;
				$end_cursor    = null;

				// Flatten the data array to a set of [ $key => $value ] pairs for LEAF nodes,
				// $value will never be an array, and $key will never be set to 'pageInfo' where
				// the targetted values are living.
				array_walk_recursive(
					$new_data,
					function( $value, $key ) use( &$has_next_page, &$end_cursor ) {
						// NOTE: This will be truthful and present on the final page causing paged
						// requests to always make an additional request to a final empty page.
						if ( $key === 'hasNextPage' ) {
							$has_next_page = $value;
						} elseif ( 'endCursor' === $key ) {
							$end_cursor = $value;
						}
					}
				);

				// Do not iterate if the cursor was what we just made the request with.
				// This should never happen, but protects against an infinite loop otherwise.
				if ( ! $end_cursor || $end_cursor === $variables['cursor'] ) {
					$has_next_page = false;
					$end_cursor    = false;
				}

				$variables['cursor'] = $end_cursor;
			}

			if ( $has_next_page && $this->debug ) {
				if ( 'cli' === php_sapi_name() ) {
					echo "\nDebug mode: Skipping future paginated requests";
				}

				break;
			}
		} while ( $has_next_page );

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		return $data['data'];
	}

	/**
	 * Similar to array_merge_recursive(), but only merges numeric arrays with one another, overwriting associative elements.
	 *
	 * Based on https://www.php.net/manual/en/function.array-merge-recursive.php#92195
	 */
	private function array_merge_recursive_numeric_arrays( array &$array1, array &$array2 ) {
		$merged = $array1;

		foreach ( $array2 as $key => &$value ) {
			// Merge numeric arrays
			if ( is_array( $value ) && wp_is_numeric_array( $value ) && isset( $merged[ $key ] ) ) {
				$merged[ $key ] = array_merge( $merged[ $key ], $value );
			} elseif ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $this->array_merge_recursive_numeric_arrays( $merged[ $key ], $value );
			} else {
				$merged[ $key ] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Generate headers to use in a request.
	 *
	 * @return array
	 */
	protected function get_request_args( $query, $variables = null ) {
		$oauth_token = $this->oauth_client->get_oauth_token();

		if ( ! empty( $this->oauth_client->error->get_error_messages() ) ) {
			$this->error = $this->merge_errors( $this->error, $this->oauth_client->error );
		}

		if ( is_array( $variables ) ) {
			$variables = wp_json_encode( $variables );
		}

		return array(
			'timeout' => 60,
			'headers' => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => "Bearer $oauth_token",
			),
			'body' => wp_json_encode( compact( 'query', 'variables' ) )
		);
	}

	/**
	 * Check the rate limit status in an API response and delay further execution if necessary.
	 *
	 * @param array $headers
	 *
	 * @return void
	 */
	protected static function throttle( $response ) {
		$headers = wp_remote_retrieve_headers( $response );

		/*
		 * NOTE: This is not in use, as GraphQL API doesn't return rate limit headers,
		 *       but does throttle requests & fail if you exceed it.
		 */

		if ( ! isset( $headers['x-ratelimit-remaining'], $headers['x-ratelimit-reset'] ) ) {
			return;
		}

		$remaining = absint( $headers['x-ratelimit-remaining'] );
		$period    = absint( $headers['x-ratelimit-reset'] );

		/**
		 * Don't throttle if we have sufficient requests remaining.
		 *
		 * We don't let this number get to 0, though, because there are scenarios where multiple processes are using
		 * the API at the same time, and there's no way for them to be aware of each other.
		 */
		if ( $remaining > 3 ) {
			return;
		}

		// Pause for longer than we need to, just to be safe.
		if ( $period < 2 ) {
			$period = 2;
		}

		self::cli_message( "Pausing for $period seconds to avoid rate-limiting." );

		sleep( $period );
	}

	/**
	 * Convert any timestamp such as a ISO8601-ish DateTime returned from the API to a epoch timestamp.
	 *
	 * Handles timestamps in two main formats:
	 *  - 2021-11-20T17:00+05:30
	 *  - 2021-11-20T06:30-05:00[US/Eastern]
	 *  - Seconds since epoch
	 *  - Milliseconds since epoch
	 *  - DateTime objects
	 *
	 * Some extra compat formats are included, just incase Meetup.com decides to return in other similar formats,
	 * or with different timezone formats, etc.
	 *
	 * @param mixed $datetime A DateTime string returned by the API, a DateTime instance, or a numeric epoch with or without milliseconds.
	 * @return int The UTC epoch timestamp.
	 */
	public function datetime_to_time( $datetime ) {
		if ( is_numeric( $datetime ) && $datetime > 4102444800 /* 2100-01-01 */ ) {
			$datetime /= 1000;
			return (int) $datetime;
		} elseif ( is_numeric( $datetime ) ) {
			return (int) $datetime;
		}

		// Handle DateTime objects.
		if ( $datetime instanceof DateTimeInterface ) {
			return $datetime->getTimestamp();
		}

		$datetime_formats = [
			'Y-m-d\TH:iP',   // 2021-11-20T17:00+05:30
			'Y-m-d\TH:i:sP', // 2021-11-20T17:00:00+05:30
			// DateTime::createFromFormat() doesn't handle the final `]` character in the following timezone format.
			'Y-m-d\TH:i\[e', // 2021-11-20T06:30[US/Eastern]
			'c',             // ISO8601, just incase the above don't cover it.
			'Y-m-d\TH:i:s',  // timezoneless 2021-11-20T17:00:00
			'Y-m-d\TH:i',    // timezoneless 2021-11-20T17:00
		];

		// See above, just keep one timezone if the timezone format is `P\[e\]`. Simpler matching, assume the timezones are the same.
		$datetime = preg_replace( '/([-+][0-9:]+)[[].+[]]$/', '$1', $datetime );

		// See above..
		$datetime = rtrim( $datetime, ']' );

		// Just being hopeful.
		$time = strtotime( $datetime );
		if ( $time ) {
			return $time;
		}

		// Try each of the timezone formats.
		foreach ( $datetime_formats as $format ) {
			$time = DateTimeImmutable::createFromFormat( $format, $datetime );
			if ( $time ) {
				break;
			}
		}

		if ( ! $time ) {
			return false;
		}

		return (int) $time->format( 'U' );
	}

	/**
	 * Extract error information from an API response and add it to our error handler.
	 *
	 * Make sure you don't include the full $response in the error as data, as that could expose sensitive information
	 * from the request payload.
	 *
	 * @param array|WP_Error $response     The response or error generated from the request.
	 * @param string         $request_url  Optional.
	 * @param array          $request_args Optional.
	 *
	 * @return void
	 */
	public function handle_error_response( $response, $request_url = '', $request_args = array() ) {
		if ( parent::handle_error_response( $response, $request_url, $request_args ) ) {
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$data          = json_decode( wp_remote_retrieve_body( $response ), true );

		$parsed_error = $this->parse_error( $data, $response_code );

		if ( ! empty( $parsed_error->get_error_messages() ) ) {
			$this->error = self::merge_errors( $this->error, $parsed_error );
		} else {
			$this->error->add(
				'unknown_error',
				'There was an unknown error.'
			);
		}
	}

	/**
	 * Attempt to extract codes and messages from a suspected error response.
	 *
	 * @param null|array $data          The data in the response body, parsed as an array. May be null for HTTP errors such as 404's.
	 * @param int        $response_code Optional. The HTTP status code from the response.
	 *
	 * @return WP_Error
	 */
	protected function parse_error( $data, $response_code = 0 ) {
		$error = new WP_Error();

		if ( isset( $data['errors'] ) ) {
			foreach ( $data['errors'] as $details ) {
				$error->add(
					$details['extensions']['code'],
					$details['message'],
					$details['locations'] ?? '' // TODO This isn't being passed through to the final error?
				);
			}
		} elseif ( isset( $data['error'], $data['error_description'] ) ) {
			$error->add(
				$data['error'],
				$data['error_description']
			);
		} elseif ( isset( $data['code'], $data['details'] ) ) {
			$error->add(
				$data['code'],
				$data['details']
			);
		} elseif ( $response_code ) {
			$error->add(
				'http_response_code',
				sprintf( 'HTTP Status: %d', absint( $response_code ) )
			);
		}

		return $error;
	}

	/**
	 * Retrieve data about groups in the Chapter program.
	 *
	 * @param array $args Optional. 'fields' and 'filters' may be defined.
	 *
	 * @return array|WP_Error
	 */
	public function get_groups( array $args = array() ) {
		$fields = $this->get_default_fields( 'group' );

		if ( !empty( $args['fields'] ) && is_array( $args['fields'] ) ) {
			$fields = array_merge( $fields, $args['fields'] );
		}

		$filters = [];
		/*
		 *  See https://www.meetup.com/api/schema/#GroupAnalyticsFilter for valid filters.
		 */
		if ( isset( $args['pro_join_date_max'] ) ) {
			$filters['proJoinDateMax'] = 'proJoinDateMax: ' . $this->datetime_to_time( $args['pro_join_date_max'] ) * 1000;
		}
		if ( isset( $args['last_event_min'] ) ) {
			$filters['lastEventMin'] = 'lastEventMin: ' . $this->datetime_to_time( $args['last_event_min'] ) * 1000;
		}

		if ( isset( $args['filters'] ) ) {
			foreach ( $args['filters'] as $key => $value ) {
				$filters[ $key ] = "{$key}: {$value}";
			}
		}

		$variables = [
			'urlname' => 'wordpress',
			'perPage' => 200,
			'cursor'  => null,
		];

		$query = '
		query ($urlname: String!, $perPage: Int!, $cursor: String ) {
			proNetworkByUrlname( urlname: $urlname ) {
				groupsSearch( input: { first: $perPage, after: $cursor }, filter: { ' . implode( ', ', $filters ) . '} ) {
					count
					'  . $this->pageInfo . '
					edges {
						node {
							' . implode( ' ', $fields ) . '
						}
					}
				}
			}
		}';

		$result = $this->send_paginated_request( $query, $variables );

		if ( is_wp_error( $result ) || ! array_key_exists( 'groupsSearch', $result['proNetworkByUrlname'] ) ) {
			return $result;
		}

		$results = array_column(
			$result['proNetworkByUrlname']['groupsSearch']['edges'],
			'node'
		);

		$results = $this->apply_backcompat_fields( 'groups', $results );

		return $results;
	}

	/**
	 * Retrieve data about events associated with a set of groups.
	 *
	 * Because of the way that the Meetup API v3 endpoints are structured, we unfortunately have to make one request
	 * (or more, if there's pagination) for each group that we want events for. When there are hundreds of groups, and
	 * we are throttling to make sure we don't get rate-limited, this process can literally take several minutes.
	 *
	 * So, when building the array for the $group_slugs parameter, it's important to filter out groups that you know
	 * will not provide relevant results. For example, if you want all events during a date range in the past, you can
	 * filter out groups that didn't join the chapter program until after your date range.
	 *
	 * Note that when using date/time related parameters in the $args array, unlike other endpoints and fields in the
	 * Meetup API which use an epoch timestamp in milliseconds, this one requires a date/time string formatted in
	 * ISO 8601, without the timezone part. Because consistency is overrated.
	 *
	 * @param array $group_slugs The URL slugs of each group to retrieve events for. Also known as `urlname`.
	 * @param array $args        Optional.  'fields' and 'filters' may be defined.
	 *
	 * @return array|WP_Error
	 */
	public function get_events( array $group_slugs, array $args = array() ) {
		$events = array();

		// See get_network_events(), which should be preferred for most cases.
		// This is kept for back-compat.

		if ( $this->debug ) {
			$chunked     = array_chunk( $group_slugs, 10 );
			$group_slugs = $chunked[0];
		}

		foreach ( $group_slugs as $group_slug ) {
			$response = $this->get_group_events( $group_slug, $args );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$events = array_merge( $events, $response );
		}

		return $events;
	}

	/**
	 * Retrieve Event Details
	 *
	 * @param string $event_id The Event ID.
	 * @return array
	 */
	function get_event_details( $event_id ) {

		$fields = $this->get_default_fields( 'event' );

		// Accepts, slug / id / slugId as the query-by fields.
		$query = '
		query ( $eventId: ID ) {
			event( id: $eventId ) {
				' . implode( ' ', $fields ) . '
			}
		}';
		$variables = [
			'eventId' => $event_id,
		];

		$result = $this->send_paginated_request( $query, $variables );

		if ( is_wp_error( $result ) || ! array_key_exists( 'event', $result ) ) {
			return $result;
		}

		$event = $result['event'] ?: false;

		if ( $event ) {
			$event = $this->apply_backcompat_fields( 'event',  $event );
		}

		return $event;
	}

	/**
	 * Retrieve the event Status for a range of given IDs.
	 *
	 * @param array $event_ids An array of [ id => MeetupID, id2 => MeetupID2 ] to query for.
	 * @return array Array of Event Statuses if events is found, null values if MeetupID doesn't exist.
	 */
	public function get_events_status( $event_ids ) {
		/* $events = [ id => $meetupID, id2 => $meetupID2 ] */

		$return = [];
		$chunks = array_chunk( $event_ids, 250, true );

		foreach ( $chunks as $chunked_events ) {
			$keys      = [];
			$query     = '';

			foreach ( $chunked_events as $id => $event_id ) {
				$key = 'e' . md5( $id );
				$keys[ $key ] = $id;

				$query .= sprintf(
					'%s: event( id: "%s" ) { id status timeStatus }' . "\n",
					$key,
					esc_attr( $event_id )
				);
			}

			$result = $this->send_paginated_request( "query { $query }" );

			if ( is_wp_error( $result ) || ! isset( $result ) ) {
				return $result;
			}

			// Unwrap it.
			foreach ( $result as $id => $data ) {
				$return[ $keys[ $id ] ] = $data;
			}
		}

		return $return;
	}

	/**
	 * Retrieve details about a group.
	 *
	 * @param string $group_slug The slug/urlname of a group.
	 * @param array  $args       Optional. 'fields' and 'event_fields' may be defined.
	 *
	 * @return array|WP_Error
	 */
	public function get_group_details( $group_slug, $args = array() ) {
		$fields = $this->get_default_fields( 'group' );;

		$events_fields = [
			'dateTime',
			'going',
		];

		if ( !empty( $args['fields'] ) && is_array( $args['fields'] ) ) {
			$fields = array_merge( $fields, $args['fields'] );
		}
		if ( !empty( $args['events_fields'] ) && is_array( $args['events_fields'] ) ) {
			$events_fields = array_merge( $events_fields, $args['events_fields'] );
		} elseif ( !empty( $args['events_fields'] ) && true === $args['events_fields'] ) {
			$events_fields = array_merge( $events_fields, $this->get_default_fields( 'events' ) );
		}

		// pastEvents cannot filter to the most recent past event, `last: 1`, `reverse:true, first: 1`, etc doesn't work.
		// Instead, we fetch the details for every past event instead.

		$query = '
		query ( $urlname: String!, $perPage: Int!, $cursor: String ) {
			groupByUrlname( urlname: $urlname ) {
				' . implode( ' ', $fields ) . '
				pastEvents ( input: { first: $perPage, after: $cursor } ) {
					' . $this->pageInfo . '
					edges {
						node {
							' . implode( ' ', $events_fields ) . '
						}
					}
				}
			}
		}';
		$variables = [
			'urlname' => $group_slug,
			'perPage' => 200,
			'cursor'  => null,
		];

		$result = $this->send_paginated_request( $query, $variables );

		if ( is_wp_error( $result ) || ! isset( $result['groupByUrlname'] ) ) {
			return $result;
		}

		// Format it similar to previous response payload??
		$result = $result['groupByUrlname'];

		$result = $this->apply_backcompat_fields( 'group', $result );

		return $result;
	}

	/**
	 * Retrieve details about group members.
	 *
	 * @param string $group_slug The slug/urlname of a group.
	 * @param array  $args       Optional. 'fields' and 'filters' may be defined.
	 *
	 * @return array|WP_Error
	 */
	public function get_group_members( $group_slug, $args = array() ) {
		$fields = $this->get_default_fields( 'memberships' );

		if ( ! empty( $args['fields'] ) && is_array( $args['fields'] ) ) {
			$fields = array_merge(
				$fields,
				$args['fields']
			);
		}

		// Filters
		$filters = [];
		if ( isset( $args['role'] ) && 'leads' === $args['role'] ) {
			// See https://www.meetup.com/api/schema/#MembershipStatus for valid statuses.
			$filters[] = 'status: LEADER';
		}

		if ( isset( $args['filters'] ) ) {
			foreach ( $args['filters'] as $key => $value ) {
				$filters[] = "{$key}: {$value}";
			}
		}

		// 'memberships' => 'GroupUserConnection' not documented.
		$query = '
		query ( $urlname: String!, $perPage: Int!, $cursor: String ) {
			groupByUrlname( urlname: $urlname ) {
				memberships ( input: { first: $perPage, after: $cursor }, filter: { ' . implode( ', ', $filters ) . ' } ) {
					' . $this->pageInfo . '
					edges {
						node {
							' . implode( ' ', $fields ) . '
						}
					}
				}
			}
		}';
		$variables = [
			'urlname' => $group_slug,
			'perPage' => 200,
			'cursor'  => null,
		];

		$results = $this->send_paginated_request( $query, $variables );
		if ( is_wp_error( $results ) || ! isset( $results['groupByUrlname'] ) ) {
			return $results;
		}

		// Select memberships.edges[*].node
		$results = array_column(
			$results['groupByUrlname']['memberships']['edges'],
			'node'
		);

		return $results;
	}

	/**
	 * Query all events from the Network.
	 */
	public function get_network_events( array $args = array() ) {
		$defaults = [
			'filters'        => [],
			'max_event_date' => time() + YEAR_IN_SECONDS,
			'min_event_date' => false,
			'online_events'  => null, // true: only online events, false: only IRL events
			'status'         => 'upcoming', //  UPCOMING, PAST, CANCELLED
			'sort'           => '',
		];
		$args = wp_parse_args( $args, $defaults );

		$fields = $this->get_default_fields( 'event' );

		// See https://www.meetup.com/api/schema/#ProNetworkEventsFilter
		$filters = [];

		if ( $args['min_event_date'] ) {
			$filters['eventDateMin'] = 'eventDateMin: ' . $this->datetime_to_time( $args['min_event_date'] ) * 1000;
		}
		if ( $args['max_event_date'] ) {
			$filters['eventDateMax'] = 'eventDateMax: ' . $this->datetime_to_time( $args['max_event_date'] ) * 1000;
		}

		if ( ! is_null( $args['online_events'] ) ) {
			$filters['isOnlineEvent'] = 'isOnlineEvent: ' . ( $args['online_events'] ? 'true' : 'false' );
		}

		// See https://www.meetup.com/api/schema/#ProNetworkEventStatus
		if ( $args['status'] && in_array( $args['status'], [ 'cancelled', 'upcoming', 'past' ] ) ) {
			$filters['status'] = 'status: ' . strtoupper( $args['status'] );
		}

		if ( $args['filters'] ) {
			foreach( $args['filters'] as $key => $filter ) {
				$filters[ $key ] = "{$key}: {$filter}";
			}
		}

		$query = '
		query ( $urlname: String!, $perPage: Int!, $cursor: String ) {
			proNetworkByUrlname( urlname: $urlname ) {
				eventsSearch ( input: { first: $perPage, after: $cursor }, filter: { ' . implode( ', ', $filters )  . ' } ) {
					' . $this->pageInfo . '
					edges {
						node {
							' . implode( ' ', $fields ) . '
						}
					}
				}
			}
		}';
		$variables = [
			'urlname' => 'wordpress',
			'perPage' => 1000, // More per-page to avoid hitting request limits
			'cursor'  => null,
		];


		$results = $this->send_paginated_request( $query, $variables );

		if ( is_wp_error( $results ) || ! array_key_exists( 'eventsSearch', $results['proNetworkByUrlname'] ) ) {
			return $results;
		}

		if ( empty( $results['proNetworkByUrlname']['eventsSearch'] ) ) {
			return [];
		}

		// Select edges[*].node
		$results = array_column(
			$results['proNetworkByUrlname']['eventsSearch']['edges'],
			'node'
		);

		$results = $this->apply_backcompat_fields( 'events', $results );

		return $results;

	}

	/**
	 * Retrieve data about events associated with one particular group.
	 *
	 * @param string $group_slug The slug/urlname of a group.
	 * @param array  $args       Optional. 'status', 'fields' and 'filters' may be defined.
	 *
	 * @return array|WP_Error
	 */
	public function get_group_events( $group_slug, array $args = array() ) {
		$defaults = [
			'status'          => 'upcoming',
			'no_earlier_than' => '',
			'no_later_than'   => '',
			'fields'          => [],
		];
		$args = wp_parse_args( $args, $defaults );

		/*
		 * The GraphQL API has 4 events fields, here's some comments:
		 *  - upcomingEvents: Supports filtering via the 'GroupUpcomingEventsFilter', which allows for 'includeCancelled'.
		 *  - pastEvents: No filters.
		 *  - draftEvents: No Filters.
		 *  - unifiedEvents: Supports Filtering via the undocumented 'GroupEventsFilter', does not support status/dates?
		 *
		 * Querying for multiple of these fields results in multiple paginated subkeys, complicating the requests, not
		 * impossible but not within the spirit of this simplified query class, so we'll avoid requesting multiple paginated
		 * fields.
		 *
		 * As a result of this, if the request is for multiple statuses, we're going to recursively call ourselves.. so that
		 * we can query using the individual fields to get the statii we want, and apply the other filters directly.
		 */
		if ( false !== strpos( $args['status'], ',' ) ) {
			$events = [];
			foreach ( explode( ',', $args['status'] ) as $status ) {
				$args['status'] = $status;
				$status_events  = $this->get_group_events( $group_slug, $args );

				// If any individual API request fails, fail it all.
				if ( is_wp_error( $status_events ) ) {
					return $status_events;
				}

				$events = array_merge( $events, $status_events );
			}

			// Resort all items.
			usort( $events, function( $a, $b ) {
				if ( $a['time'] == $b['time'] ) {
					return 0;
				}

				return ( $a['time'] < $b['time'] ) ? -1 : 1;
			} );

			return $events;
		}

		$fields = $this->get_default_fields( 'event' );

		// TODO: Check the above list against Official_WordPress_Events::parse_meetup_events()

		if ( ! empty( $args['fields'] ) && is_array( $args['fields'] ) ) {
			$fields = array_merge(
				$fields,
				$args['fields']
			);
		}

		// The GraphQL field to query.
		switch ( $args['status'] ) {
			case 'upcoming':
			case 'past':
			case 'draft':
				$event_field = $args['status'] . 'Events';
				break;
			default:
				// We got nothing.
				return [];
		}

		// No filters defined, as we have to do it ourselves. See above.

		$query = '
		query ( $urlname: String!, $perPage: Int!, $cursor: String ) {
			groupByUrlname( urlname: $urlname ) {
				' . $event_field . ' ( input: { first: $perPage, after: $cursor } ) {
					' . $this->pageInfo . '
					edges {
						node {
							' . implode( ' ', $fields ) . '
						}
					}
				}
			}
		}';
		$variables = [
			'urlname' => $group_slug,
			'perPage' => 200,
			'cursor'  => null,
		];

		$results = $this->send_paginated_request( $query, $variables );
		if ( is_wp_error( $results ) || ! isset( $results['groupByUrlname'] ) ) {
			return $results;
		}

		// Select {$event_field}.edges[*].node
		$results = array_column(
			$results['groupByUrlname'][ $event_field ]['edges'],
			'node'
		);

		$results = $this->apply_backcompat_fields( 'events', $results );

		// Apply filters.
		if ( $args['no_earlier_than'] || $args['no_later_than'] ) {
			$args['no_earlier_than'] = $this->datetime_to_time( $args['no_earlier_than'] ) ?: 0;
			$args['no_later_than']   = $this->datetime_to_time( $args['no_later_than'] ) ?: PHP_INT_MAX;

			$results = array_filter(
				$results,
				function( $event ) use( $args ) {
					return
						$event['time'] >= $args['no_earlier_than'] &&
						$event['time'] < $args['no_later_than'];
				}
			);
		}

		return $results;
	}

	/**
	 * Find out how many results are available for a particular request.
	 *
	 * @param string $route The Meetup.com API route to send a request to.
	 * @param array  $args  Optional.  'pro_join_date_max', 'pro_join_date_min', and 'filters' may be defined.
	 *
	 * @return int|WP_Error
	 */
	public function get_result_count( $route, array $args = array() ) {
		$result  = false;
		$filters = [];

		// Number of groups in the Pro Network.
		if ( 'pro/wordpress/groups' !== $route ) {
			return false;
		}

		// https://www.meetup.com/api/schema/#GroupAnalyticsFilter
		if ( ! empty( $args['pro_join_date_max'] ) ) {
			$filters['proJoinDateMax'] = 'proJoinDateMax: ' . $this->datetime_to_time( $args['pro_join_date_max'] ) * 1000;
		}
		if ( ! empty( $args['pro_join_date_min'] ) ) {
			$filters['proJoinDateMin'] = 'proJoinDateMin: ' . $this->datetime_to_time( $args['pro_join_date_min'] ) * 1000;
		}

		if ( isset( $args['filters'] ) ) {
			foreach ( $args['filters'] as $key => $value ) {
				$filters[ $key ] = "{$key}: {$value}";
			}
		}

		$query = '
		query {
			proNetworkByUrlname( urlname: "wordpress" ) {
				groupsSearch( filter: { ' .  implode( ', ', $filters ) . ' } ) {
					count
				}
			}
		}';

		$results = $this->send_paginated_request( $query );
		if ( is_wp_error( $results ) ) {
			return $results;
		}

		return (int) $results['proNetworkByUrlname']['groupsSearch']['count'];
	}

	/**
	 * Get the default fields for each object type.
	 *
	 * @param string $type The Object type.
	 * @return array Fields to query.
	 */
	protected function get_default_fields( $type ) {
		if ( 'event' === $type ) {
			// See https://www.meetup.com/api/schema/#Event for valid fields.
			return [
				'id',
				'title',
				'description',
				'eventUrl',
				'status',
				'timeStatus',
				'dateTime',
				'timezone',
				'endTime',
				'duration',
				'createdAt',
				'isOnline',
				'going',
				'group {
					' . implode( ' ', $this->get_default_fields( 'group' ) ) . '
				}',
				'venue {
					id
					lat
					lng
					name
					city
					state
					country
				}'
			];
		} elseif ( 'memberships' === $type ) {
			// See https://www.meetup.com/api/schema/#User for valid fields.
			return [
				'id',
				'name',
				'email',
			];
		} elseif ( 'group' === $type ) {
			return [
				'id',
				'name',
				'urlname',
				'link',
				'city',
				'state',
				'country',
				'groupAnalytics {
					totalPastEvents,
					totalMembers,
					lastEventDate,
				}',
				'foundedDate',
				'proJoinDate',
				'latitude',
				'longitude',
			];
		}
	}

	/**
	 * Apply back-compat fields/filters for previous uses of the client.
	 *
	 * Can be removed once all uses of the library have migrated over.
	 *
	 * @param string $type   The type of result object.
	 * @param array  $result The result to back-compat.
	 * @return The $result with back-compat.
	 */
	protected function apply_backcompat_fields( $type, $result ) {
		if ( 'event' === $type ) {

			$result['name'] = $result['title'];

			if ( ! empty( $result['dateTime'] ) ) {
				// Required for utc_offset below.
				$result['time'] = $this->datetime_to_time( $result['dateTime'] );
			}

			// Parse an ISO DateInterval into seconds.
			$now = time();
			$result['duration'] = ( DateTimeImmutable::createFromFormat( 'U', $now ) )->add( new DateInterval( $result['duration'] ) )->getTimestamp() - $now;

			$result['utc_offset'] = 0;
			if ( ! empty( $result['timezone'] ) && isset( $result['time'] ) ) {
				$result['utc_offset'] = (
					new DateTimeImmutable(
						// $result['time'] is back-compat above.
						gmdate( 'Y-m-d H:i:s', $result['time'] ),
						new DateTimeZone( $result['timezone'] )
					)
				)->getOffset();
			}

			if ( ! empty( $result['venue'] ) ) {
				if ( is_numeric( $result['venue']['id'] ) ) {
					$result['venue']['id'] = (int) $result['venue']['id'];
				}

				$result['venue']['localized_location']     = $this->localise_location( $result['venue'] );
				$result['venue']['localized_country_name'] = $this->localised_country_name( $result['venue']['country'] );

				// For online events, disregard the Venue lat/lon. It's not correct. In back-compat methods to allow for BC for existing uses of the class.
				if ( ! empty( $result['venue']['lng'] ) && self::ONLINE_VENUE_ID == $result['venue']['id'] ) {
					$result['venue']['lat'] = '';
					$result['venue']['lng'] = '';
				}

				// Seriously.
				if ( ! empty( $result['venue']['lng'] ) ) {
					$result['venue']['lon'] = $result['venue']['lng'];
				}
			}

			if ( ! empty( $result['group'] ) ) {
				$result['group'] = $this->apply_backcompat_fields( 'group', $result['group'] );
			}

			$result['status'] = strtolower( $result['status'] );
			if ( in_array( $result['status'], [ 'published', 'past', 'active', 'autosched' ] ) ) {
				$result['status'] = 'upcoming'; // Right, past is upcoming in this context
			}

			$result['yes_rsvp_count'] = $result['going'];
			$result['link']           = $result['eventUrl'];
		}

		if ( 'events' === $type ) {
			foreach ( $result as &$event ) {
				$event = $this->apply_backcompat_fields( 'event', $event );
			}
		}

		if ( 'group' === $type ) {
			// Stub in the fields that are different.
			$result['founded_date']           = $this->datetime_to_time( $result['foundedDate'] );
			$result['created']                = $result['founded_date'];
			$result['localized_location']     = $this->localise_location( $result );
			$result['localized_country_name'] = $this->localised_country_name( $result['country'] );
			$result['members']                = $result['groupAnalytics']['totalMembers'] ?? 0;
			$result['member_count']           = $result['members'];

			if ( ! empty( $result['proJoinDate'] ) ) {
				$result['pro_join_date'] = $this->datetime_to_time( $result['proJoinDate'] );
			}

			if ( ! empty( $result['pastEvents']['edges'] ) ) {
				$result['last_event']       = [
					'time'           => $this->datetime_to_time( end( $result['pastEvents']['edges'] )['node']['dateTime'] ),
					'yes_rsvp_count' => end( $result['pastEvents']['edges'] )['node']['going'],
				];
				$result['past_event_count'] = count( $result['pastEvents']['edges'] );
			} elseif ( ! empty( $result['groupAnalytics']['lastEventDate'] ) ) {
				// NOTE: last_event here vs above differs intentionally.
				$result['last_event']       = $this->datetime_to_time( $result['groupAnalytics']['lastEventDate'] );
				$result['past_event_count'] = $result['groupAnalytics']['totalPastEvents'];
			}

			$result['lat'] = $result['latitude'];
			$result['lon'] = $result['longitude'];
		}
		if ( 'groups' === $type ) {
			foreach ( $result as &$group ) {
				$group = $this->apply_backcompat_fields( 'group', $group );
			}
		}

		return $result;
	}

	/**
	 * Generate a localised location name.
	 *
	 * For the US this is 'City, ST, USA'
	 * For Canada this is 'City, ST, Canada'
	 * For the rest of world, this is 'City, CountryName'
	 */
	protected function localise_location( $args = array() ) {
		// Hard-code the Online event location
		if ( ! empty( $args['id'] ) && self::ONLINE_VENUE_ID == $args['id'] ) {
			return 'online';
		}

		$country = $args['country'] ?? '';
		$state   = $args['state']   ?? '';
		$city    = $args['city']    ?? '';
		$country = strtoupper( $country );

		// Only the USA & Canada have valid states in the response. Others have states, but are incorrect.
		if ( 'US' === $country || 'CA' === $country ) {
			$state = strtoupper( $state );
		} else {
			$state = '';
		}

		// Set countries to USA, AU, or Australia in that order.
		$country = $this->localised_country_name( $country );

		return implode( ', ',  array_filter( [ $city, $state, $country ] ) ) ?: false;
	}

	/**
	 * Localise a country code to a country name using WP-CLDR if present.
	 *
	 * @param string $country Country Code.
	 * @return Country Name, or country code upon failure.
	 */
	public function localised_country_name( $country ) {
		$localised_country = '';
		$country           = strtoupper( $country );

		// Shortcut, CLDR isn't always what we expect here.
		$shortcut = [
			'US' => 'USA',
			'HK' => 'Hong Kong',
			'SG' => 'Singapore',
		];
		if ( ! empty( $shortcut[ $country ] ) ) {
			return $shortcut[ $country ];
		}

		if ( ! class_exists( '\WP_CLDR' ) && file_exists( WP_PLUGIN_DIR . '/wp-cldr/class-wp-cldr.php' ) ) {
			require WP_PLUGIN_DIR . '/wp-cldr/class-wp-cldr.php';
		}

		if ( class_exists( '\WP_CLDR' ) ) {
			$cldr = new \WP_CLDR();

			$localised_country = $cldr->get_territory_name( $country );
		}

		return $localised_country ?: $country;
	}
}
