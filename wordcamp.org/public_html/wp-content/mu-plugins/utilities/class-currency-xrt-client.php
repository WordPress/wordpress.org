<?php

namespace WordCamp\Utilities;
defined( 'WPINC' ) || die();

/**
 * Class Currency_XRT_Client
 *
 * Get historical exchange rates for the major currencies. Designed to be able to use different API sources for
 * the exchange rate data. Initially built using the Fixer API (fixer.io). "XRT" = "exchange rate".
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
	 * @var array Cache of exchange rates by date for reuse.
	 */
	protected $cache = array();

	/**
	 * Currency_XRT_Client constructor.
	 *
	 * @param string $base_currency Optional. Currency symbol for the base currency. Default 'USD'.
	 * @param string $source        Optional. Identifier for the exchange rates source. Default 'fixer'.
	 */
	public function __construct( $base_currency = 'USD', $source = 'fixer' ) {
		$this->error = new \WP_Error();

		$this->base_currency = $base_currency;
		$this->source        = $source;
		$this->api_base      = $this->get_api_base( $this->source );
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
			default :
				$base_url = 'https://api.fixer.io/';
				break;
		}

		return trailingslashit( $base_url );
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

		try {
			$date = new \DateTime( $date );
		} catch ( \Exception $e ) {
			$this->error->add( $e->getCode(), $e->getMessage() );

			return $this->error;
		}

		$cached_rates = $this->get_cached_rates( $date );

		if ( false !== $cached_rates ) {
			return $cached_rates;
		}

		switch ( $this->source ) {
			case 'fixer' :
			default :
				$rates = $this->send_fixer_request( $date );
				break;
		}

		if ( is_wp_error( $rates ) ) {
			return $rates;
		}

		$rates = array_map( 'floatval', $rates );

		$this->cache_rates( $date, $rates );

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

		$request_url = add_query_arg( array(
			'base' => $this->base_currency,
		), $this->api_base . $date->format( 'Y-m-d' ) );

		$response      = wcorg_redundant_remote_get( $request_url );
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $response_code ) {
			if ( isset( $response_body['rates'] ) ) {
				$data = $response_body['rates'];
			} elseif ( isset( $response_body['error'] ) ) {
				$this->error->add(
					'request_error',
					$response_body['error']
				);
			} else {
				$this->error->add(
					'unexpected_response_data',
					'The API response did not provide the expected data.'
				);
			}
		} else {
			$this->error->add(
				'http_response_code',
				$response_code . ': ' . print_r( $response_body, true )
			);
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		return $data;
	}

	/**
	 * Check for cached currency exchange rates for a particular date and return them if available.
	 *
	 * @todo Add object and/or database caching.
	 *
	 * @param \DateTime $date The date to retrieve rates for.
	 *
	 * @return array|bool
	 */
	protected function get_cached_rates( \DateTime $date ) {
		if ( isset( $this->cache[ $date->format( 'Y-m-d' ) ] ) ) {
			return $this->cache[ $date->format( 'Y-m-d' ) ];
		}

		return false;
	}

	/**
	 * Cache the currency exchange rates for a particular date.
	 *
	 * @todo Add object and/or database caching.
	 *
	 * @param \DateTime $date The date of the rates to be cached.
	 * @param array $rates    The rates to be cached.
	 *
	 * @return void
	 */
	protected function cache_rates( \DateTime $date, $rates ) {
		$this->cache[ $date->format( 'Y-m-d' ) ] = $rates;
	}
}
