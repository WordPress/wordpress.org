<?php
namespace WordCamp\Utilities;

use WP_Error;

defined( 'WPINC' ) || die();

/**
 * Class API_Client
 *
 * A generic, extendable class for making requests to an API.
 *
 * Important: This class is used in multiple locations in the WordPress/WordCamp ecosystem. Because of complexities
 * around SVN externals and the reliability of GitHub's SVN bridge during deploys, it was decided to maintain multiple
 * copies of this file rather than have SVN externals pointing to one canonical source.
 *
 * If you make changes to this file, make sure they are propagated to the other locations:
 *
 * - wordcamp: wp-content/mu-plugins/utilities
 * - wporg: wp-content/plugins/official-wordpress-events/meetup
 *
 * @package WordCamp\Utilities
 */
class API_Client {
	/**
	 * @var WP_Error|null Container for errors.
	 */
	public $error = null;

	/**
	 * @var string|array A function to call to determine whether to throttle requests to the API.
	 */
	protected $throttle_callback = '';

	/*
	 * @var array A list of integer response codes that should break the "tenacious" remote request loop.
	 */
	protected $breaking_response_codes = array();

	/**
	 * @var string|null The URL for the current request being attempted.
	 */
	protected $current_request_url = null;

	/**
	 * @var array|null The args for the current request being attempted.
	 */
	protected $current_request_args = null;

	/**
	 * API_Client constructor.
	 *
	 * @param array $settings {
	 *     Optional. Settings for the client.
	 *
	 *     @type callable $throttle_callback       A function to call to determine whether to throttle requests to
	 *                                             the API.
	 *     @type array    $breaking_response_codes A list of integer response codes that should break the "tenacious"
	 *                                             remote request loop.
	 * }
	 */
	public function __construct( array $settings = array() ) {
		$this->error = new WP_Error();

		$defaults = array(
			'throttle_callback'       => '',
			'breaking_response_codes' => array( 400, 401, 404, 429 ),
		);

		$settings = wp_parse_args( $settings, $defaults );

		$this->throttle_callback       = $settings['throttle_callback'];
		$this->breaking_response_codes = $settings['breaking_response_codes'];
	}

	/**
	 * Wrapper for `wp_remote_get` to retry requests that fail temporarily for various reasons.
	 *
	 * One common example of a reason a request would fail, but later succeed, is when the first request times out.
	 *
	 * Based on `wcorg_redundant_remote_get`.
	 *
	 * @param string $url
	 * @param array  $args
	 *
	 * @return array|WP_Error
	 */
	protected function tenacious_remote_request( $url, array $args = array() ) {
		$attempt_count  = 0;
		$max_attempts   = 3;
		$breaking_codes = $this->breaking_response_codes;

		// The default of 5 seconds often results in frequent timeouts.
		if ( empty( $args['timeout'] ) ) {
			$args['timeout'] = 15;
		}

		// Set current request in state so it can be manipulated between request attempts if necessary.
		$this->current_request_url  = $url;
		$this->current_request_args = $args;

		while ( $attempt_count < $max_attempts ) {
			$response      = wp_remote_request( $this->current_request_url, $this->current_request_args );
			$response_code = wp_remote_retrieve_response_code( $response );

			// This is called before breaking in case a new request is made immediately.
			$this->maybe_throttle( $response );

			if ( in_array( $response_code, $breaking_codes, true ) ) {
				break;
			}

			/*
			 * Sometimes an API inexplicably returns a success code with an empty body, but will return a valid
			 * response if the exact request is retried.
			 */
			if ( 200 === $response_code && ! empty( wp_remote_retrieve_body( $response ) ) ) {
				break;
			}

			$attempt_count++;

			/**
			 * Action: Fires when tenacious_remote_request fails a request attempt.
			 *
			 * Note that the request parameter includes the request URL which may contain sensitive information such as
			 * an API key. This should be redacted before outputting anywhere public.
			 *
			 * @param array $response
			 * @param array $request
			 * @param int   $attempt_count
			 * @param int   $max_attempts
			 */
			do_action(
				'api_client_tenacious_remote_request_attempt',
				$response,
				array(
					'url'  => $this->current_request_url,
					'args' => $this->current_request_args,
				),
				$attempt_count,
				$max_attempts
			);

			if ( $attempt_count < $max_attempts ) {
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' ) ?: 5;
				$wait        = min( $retry_after * $attempt_count, 30 );

				self::cli_message( "Request failed $attempt_count times. Pausing for $wait seconds before retrying." );

				sleep( $wait );
			}
		}

		// Reset current request.
		$this->current_request_url  = null;
		$this->current_request_args = null;

		if ( $attempt_count === $max_attempts && ( 200 !== $response_code || is_wp_error( $response ) ) ) {
			self::cli_message( "Request failed $attempt_count times. Giving up." );
		}

		return $response;
	}

	/**
	 * Wrapper method for a request using the GET method.
	 *
	 * @param $url
	 * @param array $args
	 *
	 * @return array|WP_Error
	 */
	public function tenacious_remote_get( $url, array $args = array() ) {
		$args['method'] = 'GET';

		return $this->tenacious_remote_request( $url, $args );
	}

	/**
	 * Wrapper method for a request using the POST method.
	 *
	 * @param $url
	 * @param array $args
	 *
	 * @return array|WP_Error
	 */
	public function tenacious_remote_post( $url, array $args = array() ) {
		$args['method'] = 'POST';

		return $this->tenacious_remote_request( $url, $args );
	}

	/**
	 * Check the rate limit status in an API response and delay further execution if necessary.
	 *
	 * @param array $response
	 */
	private function maybe_throttle( $response ) {
		if ( ! is_callable( $this->throttle_callback ) ) {
			if ( ! empty( $this->throttle_callback ) ) {
				$this->error->add(
					'invalid_throttle_callback',
					'The specified throttle callback is not callable.'
				);
			}

			return;
		}

		call_user_func( $this->throttle_callback, $response );
	}

	/**
	 * Extract error information from an API response and add it to our error handler.
	 *
	 * This is just a stub. Extending classes should define their own method that handles the error codes and
	 * messages specific to the API they are dealing with.
	 *
	 * @param array|WP_Error $response     The response or error generated from the request.
	 * @param string         $request_url  Optional.
	 * @param array          $request_args Optional.
	 *
	 * @return bool True if the error was handled.
	 */
	public function handle_error_response( $response, $request_url = '', $request_args = array() ) {
		/**
		 * Action: Fires when a remote response is suspected to be an error.
		 *
		 * @param array|WP_Error $response
		 * @param string         $request_url
		 * @param array          $request_args
		 */
		do_action( 'api_client_handle_error_response', $response, $request_url, $request_args );

		if ( is_wp_error( $response ) ) {
			$codes = $response->get_error_codes();

			foreach ( $codes as $code ) {
				$messages = $response->get_error_messages( $code );

				foreach ( $messages as $message ) {
					$this->error->add( $code, $message );
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Merge two error objects into one, new error object.
	 *
	 * @param WP_Error $error1 An error object.
	 * @param WP_Error $error2 An error object.
	 *
	 * @return WP_Error The combined errors of the two parameters.
	 */
	protected function merge_errors( WP_Error $error1, WP_Error $error2 ) {
		$codes = $error2->get_error_codes();

		foreach ( $codes as $code ) {
			$messages = $error2->get_error_messages( $code );

			foreach ( $messages as $message ) {
				$error1->add( $code, $message );
			}
		}

		return $error1;
	}

	/**
	 * Outputs a message when the command is run from the PHP command line.
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	protected static function cli_message( $message ) {
		if ( 'cli' === php_sapi_name() ) {
			echo "\n$message";
		}
	}
}
