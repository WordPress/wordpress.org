<?php

namespace WordCamp\Utilities;
use WordCamp\Logger;
use Exception;

defined( 'WPINC' ) || die();

/**
 * A lean Stripe client.
 *
 * This is favorable over the official `stripe-php` library, because we don't have to worry about keeping it
 * updated, it only has the functionality we're actually using, we won't end up with unit tests, etc inside
 * a publicly accessible folder, etc.
 */
class Stripe_Client {
	const API_URL = 'https://api.stripe.com';
	const AMOUNT_MAX = 99999999;
	protected $secret_key;

	/**
	 * Constructor
	 *
	 * @param $secret_key
	 */
	public function __construct( $secret_key ) {
		$this->secret_key = $secret_key;
	}

	/**
	 * Charge the attendee for their ticket via Stripe's API
	 *
	 * @param array $body    Transaction data to send to Stripe API.
	 *                       See https://stripe.com/docs/api#create_charge for valid fields.
	 *                       The `source` parameter is a card token from https://checkout.stripe.com/checkout.js.
	 * @param array $headers Optionally add extra headers to the request, like `Idempotency-Key`.
	 *
	 * @return object
	 *
	 * @throws Exception
	 */
	public function charge( $body, $headers = array() ) {
		if ( isset( $body['statement_descriptor'] ) ) {
			$body['statement_descriptor'] = sanitize_text_field( $body['statement_descriptor'] );
			$body['statement_descriptor'] = str_replace( array( '<', '>', '"', "'" ), '', $body['statement_descriptor'] );
			$body['statement_descriptor'] = mb_substr( $body['statement_descriptor'], 0, 22 );
		}

		$headers = shortcode_atts( array(
			'Authorization' => 'Bearer ' . $this->secret_key,
		), $headers );

		$request_args = array(
			'user-agent' => 'WordCamp.org :: ' . __CLASS__,
			'body'       => $body,
			'headers'    => $headers,
		);

		/**
		 * Stripe doesn't allow amounts larger than `AMOUNT_MAX`, even in currencies where that's the equivalent of less than $5k USD.
		 *
		 * The amount in the error message is converted back to the base unit, to avoid confusing the user.
		 *
		 * See https://botbot.me/freenode/stripe/msg/47523902/.
		 * See https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts.
		 */
		if ( isset( $request_args['body']['amount'] ) && $request_args['body']['amount'] > self::AMOUNT_MAX ) {
			throw new Exception( sprintf(
				__( "We're sorry, but we can't accept amounts larger than %s. Please send the equivalent in USD, or break it up into several smaller payments. Feel free to email <a href='mailto:%s'>%s</a> with any questions.", 'wordcamporg' ),
				number_format( self::AMOUNT_MAX / self::get_fractional_unit_multiplier( $request_args['body']['currency'] ), 2 ),
				EMAIL_CENTRAL_SUPPORT,
				EMAIL_CENTRAL_SUPPORT
			) );
		}

		$response = wp_remote_post( self::API_URL . '/v1/charges', $request_args );

		if ( is_wp_error( $response ) ) {
			Logger\log( 'response_error', compact( 'response' ) );
			throw new Exception( $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		/*
		 * Declined cards are the most common type of error, so informing the user that it was declined should
		 * significantly cut down on support requests.
		 */
		if ( isset( $body->error->type ) && 'card_error' === $body->error->type ) {
			throw new Exception( sprintf(
				__( "We're sorry, but that card was declined by the issuer, please try another. You can also contact the card issuer to find out why they declined it, or email <a href='mailto:%s'>%s</a> with any other questions.", 'wordcamporg' ),
				EMAIL_CENTRAL_SUPPORT,
				EMAIL_CENTRAL_SUPPORT
			) );
		}

		if ( empty( $body->id ) || empty( $body->paid ) || ! $body->paid ) {
			Logger\log( 'unexpected_response_body', compact( 'response' ) );
			throw new Exception( sprintf(
				__( "We're sorry, but we encountered an error trying to process that transaction. Please email <a href='mailto:%s'>%s</a> for help.", 'wordcamporg' ),
				EMAIL_CENTRAL_SUPPORT,
				EMAIL_CENTRAL_SUPPORT
			) );
		}

		return $body;
	}

	/**
	 * Get the multiplier needed to convert a currency's base unit to its equivalent fractional unit.
	 *
	 * Stripe wants amounts in the fractional unit (e.g., pennies), not the base unit (e.g., dollars). Zero-decimal
	 * currencies are not included here, because they're not supported at all yet.
	 *
	 * The data here comes from https://en.wikipedia.org/wiki/List_of_circulating_currencies.
	 *
	 * @param string $order_currency
	 *
	 * @return int
	 *
	 * @throws Exception
	 */
	public static function get_fractional_unit_multiplier( $order_currency ) {
		$match = null;

		$currency_multipliers = array(
			5    => array( 'MRO', 'MRU' ),
			100  => array(
				'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN',
				'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CNY', 'COP',
				'CRC', 'CUC', 'CUP', 'CVE', 'CZK', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP',
				'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR',
				'ILS', 'IMP', 'INR', 'IRR', 'ISK', 'JEP', 'JMD', 'JOD', 'KES', 'KGS', 'KHR', 'KPW', 'KYD', 'KZT',
				'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MKD', 'MMK', 'MNT', 'MOP', 'MUR', 'MVR', 'MWK',
				'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR',
				'PLN', 'PRB', 'QAR', 'RON', 'RSD', 'RUB', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLL',
				'SOS', 'SRD', 'SSP', 'STD', 'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TOP', 'TRY', 'TTD', 'TVD', 'TWD',
				'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEF', 'WST', 'XCD', 'YER', 'ZAR', 'ZMW',
			),
			1000 => array( 'BHD', 'IQD', 'KWD', 'LYD', 'OMR', 'TND' ),
		);

		foreach ( $currency_multipliers as $multiplier => $currencies ) {
			if ( in_array( $order_currency, $currencies, true ) ) {
				$match = $multiplier;
			}
		}

		if ( is_null( $match ) ) {
			throw new Exception( "Unknown currency multiplier for $order_currency." );
		}

		return $match;
	}
}
