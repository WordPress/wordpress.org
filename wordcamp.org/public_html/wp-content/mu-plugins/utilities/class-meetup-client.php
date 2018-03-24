<?php

namespace WordCamp\Utilities;
defined( 'WPINC' ) || die();

/**
 * Class Meetup_Client
 */
class Meetup_Client {
	/**
	 * @var string The base URL for the API endpoints.
	 */
	protected $api_base = 'https://api.meetup.com/';

	/**
	 * @var string The API key.
	 */
	protected $api_key = '';

	/**
	 * @var bool If true, the client will fetch fewer results, for faster debugging.
	 */
	protected $debug_mode;

	/**
	 * @var \WP_Error|null Container for errors.
	 */
	public $error = null;

	/**
	 * Meetup_Client constructor.
	 */
	public function __construct() {
		$this->error = new \WP_Error();

		if ( defined( 'MEETUP_API_KEY' ) ) {
			$this->api_key = MEETUP_API_KEY;
		} else {
			$this->error->add(
				'api_key_undefined',
				'The Meetup.com API Key is undefined.'
			);
		}

		$this->debug_mode = apply_filters( 'wcmc_debug_mode', false );
	}

	/**
	 * Send a paginated request to the Meetup API and return the aggregated response.
	 *
	 * This automatically paginates requests and will repeat requests to ensure all results are retrieved.
	 * It also tries to account for API request limits and throttles to avoid getting a limit error.
	 *
	 * @param string $request_url The API endpoint URL to send the request to.
	 *
	 * @return array|\WP_Error The results of the request.
	 */
	protected function send_paginated_request( $request_url ) {
		$data = array();

		$request_url = add_query_arg( array(
			'page' => 200,
		), $request_url );

		while ( $request_url ) {
			$request_url = $this->sign_request_url( $request_url );

			$response = $this->tenacious_remote_get( $request_url );

			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( isset( $body['results'] ) ) {
					$new_data = $body['results'];
				} else {
					$new_data = $body;
				}

				if ( is_array( $new_data ) ) {
					$data = array_merge( $data, $new_data );
				} else {
					$this->error->add(
						'unexpected_response_data',
						'The API response did not provide the expected data format.'
					);
					break;
				}

				$request_url = $this->get_next_url( $response );
			} else {
				$this->handle_error_response( $response );
				break;
			}

			if ( $this->debug_mode ) {
				break;
			}
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		return $data;
	}

	/**
	 * Send a single request to the Meetup API and return the total number of results available.
	 *
	 * @param string $request_url The API endpoint URL to send the request to.
	 *
	 * @return int|\WP_Error
	 */
	protected function send_total_count_request( $request_url ) {
		$count = 0;

		$request_url = add_query_arg( array(
			// We're only interested in the headers, so we don't need to receive more than one result.
			'page' => 1,
		), $request_url );

		$request_url = $this->sign_request_url( $request_url );

		$response = $this->tenacious_remote_get( $request_url );

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$count_header = wp_remote_retrieve_header( $response, 'X-Total-Count' );

			if ( $count_header ) {
				$count = absint( $count_header );
			} else {
				$this->error->add(
					'unexpected_response_data',
					'The API response did not provide a total count value.'
				);
			}
		} else {
			$this->handle_error_response( $response );
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		return $count;
	}

	/**
	 * Wrapper for `wp_remote_get` to retry requests that fail temporarily for various reasons.
	 *
	 * Based on `wcorg_redundant_remote_get`.
	 *
	 * @param string $url
	 * @param array  $args
	 *
	 * @return array|\WP_Error
	 */
	protected function tenacious_remote_get( $url, $args = array() ) {
		$attempt_count = 0;
		$max_attempts  = 3;

		// Response codes that should break the loop. See https://www.meetup.com/meetup_api/docs/#errors
		// TODO are there others?
		$breaking_codes = array(
			200, // Ok.
			400, // Bad request.
			401, // Unauthorized (invalid key).
			429, // Too many requests (rate-limited).
		);

		while ( $attempt_count < $max_attempts ) {
			$response      = wp_remote_get( $url, $args );
			$response_code = wp_remote_retrieve_response_code( $response );

			$this->maybe_throttle( wp_remote_retrieve_headers( $response ) );

			if ( in_array( $response_code, $breaking_codes, true ) || is_wp_error( $response ) ) {
				break;
			}

			$attempt_count ++;

			/**
			 * Action: Fires when tenacious_remote_get fails a request attempt.
			 *
			 * Note that the request parameter includes the request URL that contains a query string for the API key.
			 * This should be redacted before outputting anywhere public.
			 *
			 * @param array $response
			 * @param array $request
			 * @param int   $attempt_count
			 * @param int   $max_attempts
			 */
			do_action( 'meetup_client_tenacious_remote_get_attempt', $response, compact( 'url', 'args' ), $attempt_count, $max_attempts );

			if ( $attempt_count < $max_attempts ) {
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' ) ?: 5;
				$wait        = min( $retry_after * $attempt_count, 30 );

				sleep( $wait );
			}
		}

		return $response;
	}

	/**
	 * Sign a request URL with our API key.
	 *
	 * @param string $request_url
	 *
	 * @return string
	 */
	protected function sign_request_url( $request_url ) {
		return add_query_arg( array(
			'sign' => true,
			'key'  => $this->api_key,
		), $request_url );
	}

	/**
	 * Get the URL for the next page of results from a paginated API response.
	 *
	 * @param array $response
	 *
	 * @return string
	 */
	protected function get_next_url( $response ) {
		$url   = '';

		// First try v3.
		$links = wp_remote_retrieve_header( $response, 'link' );
		if ( $links ) {
			foreach ( (array) $links as $link ) {
				if ( false !== strpos( $link, 'rel="next"' ) && preg_match( '/^<([^>]+)>/', $link, $matches ) ) {
					$url = $matches[1];
					break;
				}
			}
		}

		// Then try v2.
		if ( ! $url ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['meta']['next'] ) ) {
				$url = $body['meta']['next'];
			}
		}

		return esc_url_raw( $url );
	}

	/**
	 * Check the rate limit status in an API response and delay further execution if necessary.
	 *
	 * @param array $headers
	 */
	protected function maybe_throttle( $headers ) {
		if ( ! isset( $headers['x-ratelimit-remaining'], $headers['x-ratelimit-reset'] ) ) {
			return;
		}

		$remaining = absint( $headers['x-ratelimit-remaining'] );
		$period    = absint( $headers['x-ratelimit-reset'    ] );

		// Pause more frequently than we need to, and for longer, just to be safe
		if ( $remaining > 2 ) {
			return;
		}

		if ( $period < 2 ) {
			$period = 2;
		}

		sleep( $period );
	}

	/**
	 * Extract error information from an API response and add it to our error handler.
	 *
	 * @param array|\WP_Error $response
	 *
	 * @return void
	 */
	protected function handle_error_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$codes = $response->get_error_codes();

			foreach ( $codes as $code ) {
				$messages = $response->get_error_messages( $code );

				foreach ( $messages as $message ) {
					$this->error->add( $code, $message );
				}
			}

			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$data          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['errors'] ) ) {
			foreach ( $data['errors'] as $error ) {
				$this->error->add( $error['code'], $error['message'] );
			}
		} elseif ( isset( $data['code'] ) && isset( $data['details'] ) ) {
			$this->error->add( $data['code'], $data['details'] );
		} elseif ( $response_code ) {
			$this->error->add(
				'http_response_code',
				sprintf( 'HTTP Status: %d', absint( $response_code ) )
			);
		} else {
			$this->error->add( 'unknown_error', 'There was an unknown error.' );
		}
	}

	/**
	 * Retrieve data about groups in the Chapter program.
	 *
	 * @param array $args Optional. Additional request parameters.
	 *                    See https://www.meetup.com/meetup_api/docs/pro/:urlname/groups/
	 *
	 * @return array|\WP_Error
	 */
	public function get_groups( array $args = array() ) {
		$request_url = $this->api_base . 'pro/wordpress/groups';

		if ( ! empty( $args ) ) {
			$request_url = add_query_arg( $args, $request_url );
		}

		return $this->send_paginated_request( $request_url );
	}

	/**
	 * Retrieve data about events associated with a set of groups.
	 *
	 * This automatically breaks up requests into chunks of 50 groups to avoid overloading the API.
	 *
	 * @param array $group_ids The IDs of the groups to get events for.
	 * @param array $args      Optional. Additional request parameters.
	 *                         See https://www.meetup.com/meetup_api/docs/2/events/
	 *
	 * @return array|\WP_Error
	 */
	public function get_events( array $group_ids, array $args = array() ) {
		$url_base     = $this->api_base . '2/events';
		$group_chunks = array_chunk( $group_ids, 50, true ); // Meetup API sometimes throws an error with chunk size larger than 50.
		$events       = array();

		foreach ( $group_chunks as $chunk ) {
			$query_args = array_merge( array(
				'group_id' => implode( ',', $chunk ),
			), $args );

			$request_url = add_query_arg( $query_args, $url_base );

			$data = $this->send_paginated_request( $request_url );

			if ( is_wp_error( $data ) ) {
				return $data;
			}

			$events = array_merge( $events, $data );
		}

		return $events;
	}

	/**
	 * Retrieve data about events associated with one particular group.
	 *
	 * @param string $group_slug The slug/urlname of a group.
	 * @param array  $args       Optional. Additional request parameters.
	 *                           See https://www.meetup.com/meetup_api/docs/:urlname/events/
	 *
	 * @return array|\WP_Error
	 */
	public function get_group_events( $group_slug, array $args = array() ) {
		$request_url = $this->api_base . "$group_slug/events";

		if ( ! empty( $args ) ) {
			$request_url = add_query_arg( $args, $request_url );
		}

		return $this->send_paginated_request( $request_url );
	}

	/**
	 * Find out how many results are available for a particular request.
	 *
	 * @param string $route The Meetup.com API route to send a request to.
	 * @param array  $args  Optional. Additional request parameters.
	 *                      See https://www.meetup.com/meetup_api/docs/
	 *
	 * @return int|\WP_Error
	 */
	public function get_result_count( $route, array $args = array() ) {
		$request_url = $this->api_base . $route;

		if ( ! empty( $args ) ) {
			$request_url = add_query_arg( $args, $request_url );
		}

		return $this->send_total_count_request( $request_url );
	}
}
