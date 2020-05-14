<?php
namespace WordCamp\Utilities;

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
	 * @var string The base URL for the API endpoints.
	 */
	protected $api_base = 'https://api.meetup.com/';

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
				401, // Unauthorized (invalid key).
				429, // Too many requests (rate-limited).
				404, // Unable to find group
			),
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
	 * It also tries to account for API request limits and throttles to avoid getting a limit error.
	 *
	 * @param string $request_url The API endpoint URL to send the request to.
	 *
	 * @return array|WP_Error The results of the request.
	 */
	protected function send_paginated_request( $request_url ) {
		$data = array();

		$request_url = add_query_arg( array(
			'page' => 200,
		), $request_url );

		while ( $request_url ) {
			$response = $this->tenacious_remote_get( $request_url, $this->get_request_args() );

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
						'The API response did not provide the expected data format.',
						$response
					);
					break;
				}

				$request_url = $this->get_next_url( $response );
			} else {
				$this->handle_error_response( $response, $request_url );
				break;
			}

			if ( $this->debug ) {
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
	 * @return int|WP_Error
	 */
	protected function send_total_count_request( $request_url ) {
		$count = 0;

		$request_url = add_query_arg( array(
			// We're only interested in the headers, so we don't need to receive more than one result.
			'page' => 1,
		), $request_url );

		$response = $this->tenacious_remote_get( $request_url, $this->get_request_args() );

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
			$this->handle_error_response( $response, $request_url );
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		return $count;
	}

	/**
	 * Generate headers to use in a request.
	 *
	 * @return array
	 */
	protected function get_request_args() {
		$oauth_token = $this->oauth_client->get_oauth_token();

		if ( ! empty( $this->oauth_client->error->get_error_messages() ) ) {
			$this->error = $this->merge_errors( $this->error, $this->oauth_client->error );
		}

		return array(
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => "Bearer $oauth_token",
			),
		);
	}

	/**
	 * Get the URL for the next page of results from a paginated API response.
	 *
	 * @param array $response
	 *
	 * @return string
	 */
	protected function get_next_url( $response ) {
		$url = '';

		// First try v3.
		$links = wp_remote_retrieve_header( $response, 'link' );
		if ( $links ) {
			// Meetup.com is now returning combined link headers
			if ( is_string( $links ) ) {
				$links = preg_split( '!,\s+!', $links );
			}
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
	 *
	 * @return void
	 */
	protected static function throttle( $response ) {
		$headers = wp_remote_retrieve_headers( $response );

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
	 * @param array $data          The data in the response body, parsed as an array.
	 * @param int   $response_code Optional. The HTTP status code from the response.
	 *
	 * @return WP_Error
	 */
	protected function parse_error( array $data, $response_code = 0 ) {
		$error = new WP_Error();

		if ( isset( $data['errors'] ) ) {
			foreach ( $data['errors'] as $details ) {
				$error->add(
					$details['code'],
					$details['message']
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
	 * @param array $args Optional. Additional request parameters.
	 *                    See https://www.meetup.com/meetup_api/docs/pro/:urlname/groups/.
	 *
	 * @return array|WP_Error
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
	 * @param array $args        Optional. Additional request parameters.
	 *                           See https://www.meetup.com/meetup_api/docs/:urlname/events/#list
	 *
	 * @return array|WP_Error
	 */
	public function get_events( array $group_slugs, array $args = array() ) {
		$events = array();

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
	 * Retrieve details about a group.
	 *
	 * @param string $group_slug The slug/urlname of a group.
	 * @param array  $args       Optional. Additional request parameters.
	 *                           See https://www.meetup.com/meetup_api/docs/:urlname/#get
	 *
	 * @return array|WP_Error
	 */
	public function get_group_details( $group_slug, $args = array() ) {
		$request_url = $this->api_base . sanitize_key( $group_slug );

		if ( ! empty( $args ) ) {
			$request_url = add_query_arg( $args, $request_url );
		}

		return $this->send_paginated_request( $request_url );
	}

	/**
	 * Retrieve details about group members.
	 *
	 * @param string $group_slug The slug/urlname of a group.
	 * @param array  $args       Optional. Additional request parameters.
	 *                           See https://www.meetup.com/meetup_api/docs/:urlname/members/#list
	 *
	 * @return array|WP_Error
	 */
	public function get_group_members( $group_slug, $args = array() ) {
		$request_url = $this->api_base . sanitize_key( $group_slug ) . '/members';

		if ( ! empty( $args ) ) {
			$request_url = add_query_arg( $args, $request_url );
		}

		return $this->send_paginated_request( $request_url );
	}

	/**
	 * Retrieve data about events associated with one particular group.
	 *
	 * @param string $group_slug The slug/urlname of a group.
	 * @param array  $args       Optional. Additional request parameters.
	 *                           See https://www.meetup.com/meetup_api/docs/:urlname/events/#list
	 *
	 * @return array|WP_Error
	 */
	public function get_group_events( $group_slug, array $args = array() ) {
		$request_url = $this->api_base . sanitize_key( $group_slug ) . '/events';

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
	 *                      See https://www.meetup.com/meetup_api/docs/.
	 *
	 * @return int|WP_Error
	 */
	public function get_result_count( $route, array $args = array() ) {
		$request_url = $this->api_base . $route;

		if ( ! empty( $args ) ) {
			$request_url = add_query_arg( $args, $request_url );
		}

		return $this->send_total_count_request( $request_url );
	}
}
