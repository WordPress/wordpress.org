<?php

namespace WordCamp\Utilities;
defined( 'WPINC' ) || die();

/**
 * Class Currency_XRT_Client
 *
 * Get historical exchange rates for the major currencies. Designed to be able to use different API sources for
 * the exchange rate data. Initially built using the Fixer API (fixer.io). Now includes Open Exchange Rates.
 * "XRT" = "exchange rate".
 */
class Currency_XRT_Client {
	/**
	 * @var \WP_Error|null Container for errors.
	 */
	public $error = null;

	/**
	 * @var string Currency symbol for the base currency.
	 */
	protected $base_currency = '';

	/**
	 * @var string Identifier for the exchange rates source.
	 */
	protected $source = '';

	/**
	 * @var string Base URL for the source's API.
	 */
	protected $api_base = '';

	/**
	 * @var string The key for accessing the source's API.
	 */
	protected $api_key = '';

	/**
	 * @var array Cache of exchange rates by date for reuse.
	 */
	protected $cache = array();

	/**
	 * Currency_XRT_Client constructor.
	 *
	 * @param string $base_currency Optional. Currency symbol for the base currency. Default 'USD'.
	 * @param string $source        Optional. Identifier for the exchange rates source. Default 'oxr'.
	 */
	public function __construct( $base_currency = 'USD', $source = 'oxr' ) {
		$this->error = new \WP_Error();

		$this->base_currency = $base_currency;
		$this->source        = $source;
		$this->api_base      = $this->get_api_base( $this->source );
		$this->api_key       = $this->get_api_key( $this->source );
	}

	/**
	 * Get the API base URL based on the given source.
	 *
	 * @param string $source
	 *
	 * @return string The API base URL.
	 */
	protected function get_api_base( $source ) {
		$base_url = '';

		switch ( $source ) {
			case 'fixer' :
				$base_url = 'https://data.fixer.io/api/';
				break;
			case 'oxr' :
				$base_url = 'https://openexchangerates.org/api/';
				break;
		}

		return trailingslashit( $base_url );
	}

	/**
	 * Get the API key based on the given source.
	 *
	 * @param string $source
	 *
	 * @return string The API key.
	 */
	protected function get_api_key( $source ) {
		$key = '';

		switch ( $source ) {
			case 'fixer' :
				if ( defined( 'WORDCAMP_FIXER_API_KEY' ) ) {
					$key = WORDCAMP_FIXER_API_KEY;
				}
				break;
			case 'oxr' :
				if ( defined( 'WORDCAMP_OXR_API_KEY' ) ) {
					$key = WORDCAMP_OXR_API_KEY;
				}
				break;
		}

		return $key;
	}

	/**
	 * Get the currency exchange rates for a particular date.
	 *
	 * @param string $date The date to retrieve the rates for.
	 *
	 * @return array|\WP_Error An array of rates, or an error.
	 */
	public function get_rates( $date ) {
		$rates = array();
		$cache_key = 'wc_currency_rates_' . $this->source . '_' . strtotime( $date );

		try {
			$date = new \DateTime( $date );
		} catch ( \Exception $e ) {
			$this->error->add( $e->getCode(), $e->getMessage() );

			return $this->error;
		}

		$cached_rates = get_transient( $cache_key );

		if ( false !== $cached_rates ) {
			return $cached_rates;
		}

		switch ( $this->source ) {
			case 'fixer' :
				$rates = $this->send_fixer_request( $date );
				break;
			case 'oxr' :
				$rates = $this->send_oxr_request( $date );
				break;
			default :
				$rates = new \WP_Error(
					'invalid_xrt_source',
					sprintf(
						'%s is not a valid currency exchange rate source.',
						esc_html( $this->source )
					)
				);
				break;
		}

		if ( is_wp_error( $rates ) ) {
			return $rates;
		}

		$rates = array_map( 'floatval', $rates );

		set_transient( $cache_key, $rates, MONTH_IN_SECONDS );

		return $rates;
	}

	/**
	 * Convert an amount in a given currency to an amount in the base currency using
	 * a particular date's rates.
	 *
	 * @param float  $amount        The amount to convert.
	 * @param string $from_currency The currency to convert from.
	 * @param string $date          The date to get the rate for.
	 *
	 * @return object|\WP_Error An object with properties for the beginning and ending currencies,
	 *                          each with a float value. Or an error.
	 */
	public function convert( $amount, $from_currency, $date = '' ) {
		if ( ! $date ) {
			$date = 'now';
		}

		$amount = floatval( $amount );

		$rates = $this->get_rates( $date );

		if ( is_wp_error( $rates ) ) {
			return $rates;
		}

		if ( ! isset( $rates[ $from_currency ] ) ) {
			$this->error->add(
				'unknown_currency',
				sprintf(
					'%s is not an available currency to convert from.',
					esc_html( $from_currency )
				)
			);

			return $this->error;
		}

		$rate = $rates[ $from_currency ];

		try {
			$converted_amount = $amount / $rate;
		} catch ( \Exception $e ) {
			$this->error->add(
				$e->getCode(),
				$e->getMessage()
			);

			return $this->error;
		}

		return (object) [
			$from_currency       => $amount,
			$this->base_currency => $converted_amount,
		];
	}

	/**
	 * Send a request to the Fixer API and return the results.
	 *
	 * @param \DateTime $date The date to retrieve rates for.
	 *
	 * @return array|\WP_Error An array of rates, or an error.
	 */
	protected function send_fixer_request( \DateTime $date ) {
		$data = array();

		$api_endpoint = $date->format( 'Y-m-d' );
		$now = new \DateTime();

		if ( $date->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) || $date > $now ) {
			$api_endpoint = 'latest';
		}

		$request_url = add_query_arg(
			array(
				'access_key' => $this->api_key,
				'base'       => $this->base_currency,
			),
			esc_url( $this->api_base . $api_endpoint )
		);

		$response      = wcorg_redundant_remote_get( $request_url );
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $response_code ) {
			if ( isset( $response_body['rates'] ) ) {
				$data = $response_body['rates'];
			} elseif ( isset( $response_body['error'] ) ) {
				$this->error->add(
					'request_error',
					sprintf(
						'%s: %s',
						$response_body['error']['code'],
						$response_body['error']['info']
					)
				);
			} else {
				$this->error->add(
					'unexpected_response_data',
					'The API response did not provide the expected data.'
				);
			}
		} else {
			if ( isset( $response_body['error'] ) ) {
				$this->error->add(
					'request_error',
					sprintf(
						'%s: %s',
						$response_body['error']['code'],
						$response_body['error']['info']
					)
				);
			} else {
				$this->error->add(
					'http_response_code',
					$response_code . ': ' . print_r( $response_body, true )
				);
			}
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		return $data;
	}

	/**
	 * Send a request to the Open Exchange Rates API and return the results.
	 *
	 * @param \DateTime $date The date to retrieve rates for.
	 *
	 * @return array|\WP_Error An array of rates, or an error.
	 */
	protected function send_oxr_request( \DateTime $date ) {
		$data = array();

		$api_endpoint = 'historical/' . $date->format( 'Y-m-d' );
		$now = new \DateTime();

		if ( $date->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) || $date > $now ) {
			$api_endpoint = 'latest';
		}

		$request_url = add_query_arg(
			array(
				'app_id' => $this->api_key,
				'base'   => $this->base_currency,
			),
			esc_url( sprintf( '%s%s.json', $this->api_base, $api_endpoint ) )
		);

		$response      = wcorg_redundant_remote_get( $request_url );
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $response_code ) {
			if ( isset( $response_body['rates'] ) ) {
				$data = $response_body['rates'];
			} else {
				$this->error->add(
					'unexpected_response_data',
					'The API response did not provide the expected data.'
				);
			}
		} else {
			if ( isset( $response_body['error'], $response_body['message'], $response_body['description'] ) ) {
				$this->error->add(
					esc_html( $response_body['message'] ),
					esc_html( $response_body['description'] )
				);
			} else {
				$this->error->add(
					'http_response_code',
					$response_code . ': ' . print_r( $response_body, true )
				);
			}
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		return $data;
	}
}
