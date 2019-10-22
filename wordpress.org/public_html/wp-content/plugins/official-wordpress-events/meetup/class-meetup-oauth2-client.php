<?php
namespace WordCamp\Utilities;

use WP_Error;

defined( 'WPINC' ) || die();

/**
 * Class Meetup_OAuth2_Client
 *
 * Implements the "Server Flow with User Credentials" for Meetup's OAuth2 authorization scheme.
 *
 * @see https://www.meetup.com/meetup_api/auth/#oauth2servercredentials
 *
 * @todo Maybe make some of the credential strings like consumer key, etc, class parameters that map to private
 *       properties instead of constants. That way the client could be reused elsewhere in the Dotorg ecosystem with
 *       different credentials, instead of being tightly coupled to WordCamp.
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
class Meetup_OAuth2_Client extends API_Client {
	/**
	 * @var string
	 */
	const CONSUMER_KEY = MEETUP_OAUTH_CONSUMER_KEY;

	/**
	 * @var string
	 */
	const CONSUMER_SECRET = MEETUP_OAUTH_CONSUMER_SECRET;

	/**
	 * @var string
	 */
	const REDIRECT_URI = MEETUP_OAUTH_CONSUMER_REDIRECT_URI;

	/**
	 * @var string
	 */
	const EMAIL = MEETUP_USER_EMAIL;

	/**
	 * @var string
	 */
	const PASSWORD = MEETUP_USER_PASSWORD;

	/**
	 * @var string
	 */
	const URL_AUTHORIZE = 'https://secure.meetup.com/oauth2/authorize';

	/**
	 * @var string
	 */
	const URL_ACCESS_TOKEN = 'https://secure.meetup.com/oauth2/access';

	/**
	 * @var string
	 */
	const URL_OAUTH_TOKEN = 'https://api.meetup.com/sessions';

	/**
	 * @var string
	 */
	const SITE_OPTION_KEY_OAUTH = 'meetup_oauth_token';

	/**
	 * @var array
	 */
	protected $oauth_token = array();

	/**
	 * Meetup_OAuth2_Client constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			/**
			 * Response codes that should break the request loop.
			 *
			 * `200` (ok) is not in the list, because it needs to be handled conditionally.
			 *  See API_Client::tenacious_remote_request.
			 */
			'breaking_response_codes' => array(
				400, // Bad request.
				401, // Unauthorized (invalid key).
				429, // Too many requests (rate-limited).
				404, // Unable to find group
			),
		) );

		// Pre-cache the oauth token.
		$this->get_oauth_token();
	}

	/**
	 * Generate the headers for a request.
	 *
	 * @param string $access_token Optional. Providing an access token will add an extra header.
	 *
	 * @return array
	 */
	protected function get_headers( $access_token = '' ) {
		$headers = array(
			'Accept' => 'application/json',
		);

		if ( $access_token ) {
			$headers['Authorization'] = "Bearer $access_token";
		}

		return $headers;
	}

	/**
	 * Step 1 in "Server Flow with User Credentials".
	 *
	 * @see https://www.meetup.com/meetup_api/auth/#oauth2servercredentials-auth
	 *
	 * @return array
	 */
	protected function request_authorization_code() {
		$authorization_code = array();

		$request = array(
			'client_id'     => self::CONSUMER_KEY,
			'redirect_uri'  => self::REDIRECT_URI,
			'response_type' => 'anonymous_code',
		);

		$args = array(
			'headers' => $this->get_headers(),
			'body'    => $request,
		);

		$response = $this->tenacious_remote_post( self::URL_AUTHORIZE, $args );

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! empty( $body['code'] ) ) {
				$authorization_code = $body;
			} else {
				$this->error->add(
					'unexpected_oauth_authorization_code_response',
					'The Meetup OAuth API response did not provide the expected data.'
				);
			}
		} else {
			$this->handle_error_response( $response );
		}

		return $authorization_code;
	}

	/**
	 * Request one of various types of tokens from the Meetup OAuth API.
	 *
	 * Setting $type to 'server_token' is for Step 2 in "Server Flow with User Credentials". This gets the "server
	 * access token" which is then used to request the "oauth access token".
	 * @see https://www.meetup.com/meetup_api/auth/#oauth2servercredentials-access
	 *
	 * Setting $type to 'oauth_token' is for Step 3 in "Server Flow with User Credentials". Technically in the
	 * documentation, this token is also called an "access token", but here we're calling it the "oauth access token" to
	 * differentiate it from the "server access token". Also, why are two separate access tokens necessary??
	 * @see https://www.meetup.com/meetup_api/auth/#oauth2servercredentials-accesspro
	 *
	 * Setting $type to 'refresh_token' will request a new server access token for Step 2. This is for when the oauth
	 * token from Step 3 is expired. The refreshed server token can then be used to obtain a new Step 3
	 * oauth token. This skips the authorization code request (Step 1), but seems largely superfluous since a second
	 * oauth token request must still be made with the new server token. Also, the refresh_token string used to refresh
	 * the server token needs to come from the oauth token array, **not the server token array**. This is not what the
	 * documentation implies. Why is this so terrible??
	 * @see https://www.meetup.com/meetup_api/auth/#oauth2server-refresh
	 *
	 * Check the `get_oauth_token` method to see how these token request flows work.
	 *
	 * @param string $type The type of token request. 'server_token', 'refresh_token', or 'oauth_token'.
	 * @param array  $args The pieces of data required to make the given type of request.
	 *
	 * @return array|mixed|object
	 */
	protected function request_token( $type, array $args = array() ) {
		$token = array();

		$request_url     = '';
		$request_headers = $this->get_headers();
		$request_body    = array();

		switch( $type ) {
			case 'server_token': // Request a new server access token.
				$args = wp_parse_args( $args, array(
					'code' => '',
				) );

				$request_url  = self::URL_ACCESS_TOKEN;
				$request_body = array(
					'client_id'     => self::CONSUMER_KEY,
					'client_secret' => self::CONSUMER_SECRET,
					'redirect_uri'  => self::REDIRECT_URI,
					'code'          => $args['code'],
					'grant_type'    => 'anonymous_code',
				);
				break;
			case 'refresh_token': // Refresh a server access token.
				$args = wp_parse_args( $args, array(
					'refresh_token' => '',
				) );

				$request_url  = self::URL_ACCESS_TOKEN;
				$request_body = array(
					'client_id'     => self::CONSUMER_KEY,
					'client_secret' => self::CONSUMER_SECRET,
					'refresh_token' => $args['refresh_token'],
					'grant_type'    => 'refresh_token',
				);
				break;
			case 'oauth_token': // Request a new oauth token.
				$args = wp_parse_args( $args, array(
					'access_token' => '',
				) );

				$request_url     = self::URL_OAUTH_TOKEN;
				$request_headers = $this->get_headers( $args['access_token'] );
				$request_body    = array(
					'email'    => self::EMAIL,
					'password' => self::PASSWORD,
				);
				break;
		}

		$request_args = array(
			'headers' => $request_headers,
			'body'    => $request_body,
		);

		$response = $this->tenacious_remote_post( $request_url, $request_args );

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $this->is_valid_token( $body, $type ) ) {
				$token = $body;

				$token['timestamp'] = time();
			} else {
				$this->error->add(
					// Don't include the entire response in the error data, because it might include sensitive
					// data from the request payload.
					'unexpected_oauth_token_response',
					'The Meetup OAuth API response did not provide the expected data.'
				);
			}
		} else {
			$this->handle_error_response( $response );
		}

		return $token;
	}

	/**
	 * Get the oauth access token needed to make requests to the Meetup API.
	 *
	 * This encompasses all three of the steps in the "Server Flow with User Credentials" flow, so it's the only
	 * method that should be called directly.
	 *
	 * @see https://www.meetup.com/meetup_api/auth/#oauth2servercredentials
	 *
	 * This stores a successfully retrieved token array to the database for repeated use, until it expires.
	 *
	 * @return string
	 */
	public function get_oauth_token() {
		if ( $this->oauth_token && ! $this->is_expired_token( $this->oauth_token ) ) {
			return $this->oauth_token['oauth_token'];
		}

		$oauth_token   = '';
		$needs_caching = false;

		$token = get_site_option( self::SITE_OPTION_KEY_OAUTH, array() );

		if ( ! $this->is_valid_token( $token, 'oauth_token' ) ) {
			$authorization = $this->request_authorization_code(); // Step 1.
			$server_token  = $this->request_token( 'server_token', $authorization ); // Step 2.
			$token         = $this->request_token( 'oauth_token', $server_token ); // Step 3.
			$needs_caching = true;
		} elseif ( $this->is_expired_token( $token ) ) {
			$server_token = $this->request_token( 'refresh_token', $token ); // Alternate for Steps 1 & 2.

			// If the token is no longer valid but "looked valid" fetch a fresh one.
			if ( ! $server_token && $this->error->get_error_message( 'oauth_error' ) ) {
				$this->error->remove( 'oauth_error' );

				// The token isn't valid for refreshing, request a new one.
				$authorization = $this->request_authorization_code(); // Step 1.
				$server_token  = $this->request_token( 'server_token', $authorization ); // Step 2.
			}

			$token         = $this->request_token( 'oauth_token', $server_token ); // Step 3.
			$needs_caching = true;
		}

		if ( $this->is_valid_token( $token, 'oauth_token' ) ) {
			$this->oauth_token = $token;

			if ( $needs_caching ) {
				update_site_option( self::SITE_OPTION_KEY_OAUTH, $token );
			}

			$oauth_token = $this->oauth_token['oauth_token'];
		}

		return $oauth_token;
	}

	/**
	 * Un-cache any existing oauth token and request a new one.
	 *
	 * This also resets the error property so that it has a clean slate when it attempts to request a new token.
	 *
	 * Useful if a token stops working during a batch of requests :/
	 *
	 * @return void
	 */
	public function reset_oauth_token() {
		delete_site_option( self::SITE_OPTION_KEY_OAUTH );

		$this->oauth_token = array();
		$this->error       = new WP_Error();

		$this->get_oauth_token();
	}

	/**
	 * Check if a token array has the required keys.
	 *
	 * @param array  $token The token array to check.
	 * @param string $type  The type of token. Either 'access' or 'oauth'.
	 *
	 * @return bool
	 */
	protected function is_valid_token( $token, $type ) {
		$valid_types = array( 'server_token', 'refresh_token', 'oauth_token' );

		if ( ! is_array( $token ) || ! in_array( $type, $valid_types, true ) ) {
			return false;
		}

		switch ( $type ) {
			case 'server_token':
			case 'refresh_token':
				$required_properties = array(
					'access_token' => '',
				);
				break;
			case 'oauth_token':
			default:
				$required_properties = array(
					'oauth_token'   => '',
					'refresh_token' => '',
					'expires_in'    => '',
				);
				break;
		}

		$missing_properties = array_diff_key( $required_properties, $token );

		return empty( $missing_properties );
	}

	/**
	 * Check if a token has expired since it was retrieved from the OAuth API.
	 *
	 * @param array $token
	 *
	 * @return bool
	 */
	protected function is_expired_token( array $token ) {
		return absint( $token['timestamp'] ) + absint( $token['expires_in'] ) <= time();
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

		if ( isset( $data['errors'] ) ) {
			foreach ( $data['errors'] as $error ) {
				$this->error->add(
					$error['code'],
					$error['message']
				);
			}
		} elseif ( isset( $data['error'] ) ) {
			$this->error->add(
				'oauth_error',
				$data['error']
			);
		} elseif ( isset( $data['code'] ) && isset( $data['details'] ) ) {
			$this->error->add(
				$data['code'],
				$data['details']
			);
		} elseif ( $response_code ) {
			$this->error->add(
				'http_response_code',
				sprintf( 'HTTP Status: %d', absint( $response_code ) )
			);
		} else {
			$this->error->add(
				'unknown_error',
				'There was an unknown error.'
			);
		}
	}
}
