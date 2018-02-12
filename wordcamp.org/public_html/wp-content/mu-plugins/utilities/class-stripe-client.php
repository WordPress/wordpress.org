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

		$response = wp_remote_post( self::API_URL . '/v1/charges', $request_args );

		if ( is_wp_error( $response ) ) {
			Logger\log( 'response_error', $response );
			throw new Exception( $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $body->id ) || empty( $body->paid ) || ! $body->paid ) {
			Logger\log( 'unexpected_response_body', $response );
			throw new Exception( 'Unexpected response body.' );
		}

		return $body;
	}
}
