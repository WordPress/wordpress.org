<?php
/**
 * Sponsorship Payments via Stripe
 */

namespace WordCamp\Budgets\Sponsor_Payment_Stripe;
use WordCamp\Utilities\Stripe_Client;
use WordCamp\Utilities\Form_Spam_Prevention;
use WordCamp_Loader;
use WordCamp_Budgets;
use Exception;

defined( 'WPINC' ) || die();

const STEP_SELECT_INVOICE  = 1;
const STEP_PAYMENT_DETAILS = 2;
const STEP_PAYMENT_SUCCESS = 3;
const JS_VERSION           = 1;
const CSS_VERSION          = 2;

/**
 * Render the payment UI
 *
 * This function is called after template_redirect when the content is about to get loaded.
 * It is invoked from the template-sponsorship-payment.php page template in the central theme.
 */
function render() {
	$keys = _get_keys();

	if ( empty( $keys['publishable'] ) || empty( $keys['secret'] ) || empty( $keys['hmac_key'] ) ) {
		return;
	}

	require_once( __DIR__ . '/wordcamp-budgets.php' );

	$data = array(
		'keys'                   => $keys,
		'step'                   => STEP_SELECT_INVOICE,
		'wordcamp_query_options' => get_wordcamp_query_options(),
		'currencies'             => WordCamp_Budgets::get_currencies(),
		'errors'                 => array(),
	);

	$submitted = filter_input( INPUT_POST, 'sponsor_payment_submit' );

	if ( $submitted ) {
		_handle_post_data( $data ); // $data passed by ref.
	}

	wp_enqueue_style( 'wcb-sponsor-payments', plugins_url( 'css/sponsor-payments.css', __DIR__ ), array(), CSS_VERSION );
	wp_enqueue_script( 'wcb-sponsor-payments', plugins_url( 'javascript/sponsor-payments.js', __DIR__ ), array( 'jquery' ), JS_VERSION, true );

	wp_localize_script(
		'wcb-sponsor-payments',
		'WordCampSponsorPayments',
		array(
			'steps' => array(
				'select-invoice'  => STEP_SELECT_INVOICE,
				'payment-details' => STEP_PAYMENT_DETAILS,
				'payment-success' => STEP_PAYMENT_SUCCESS,
			),
		)
	);

	$fsp = new Form_Spam_Prevention();
	add_action( 'wp_print_styles', [ $fsp, 'render_form_field_styles' ] );

	require_once( dirname( __DIR__ ) . '/views/sponsor-payment/main.php' );
}

/**
 * Get Stripe and HMAC keys.
 *
 * @return array Stripe and HMAC keys.
 */
function _get_keys() {
	return apply_filters( 'wcorg_sponsor_payment_stripe', array(
		// Stripe API credentials.
		'publishable' => '',
		'secret'      => '',

		// An HMAC key used to sign some data in between requests.
		'hmac_key'    => '',
	) );
}

/**
 * Returns query options to be used with `get_wordcamps()`.
 *
 * This provides a consistent and canonical source for the query options, so that all callers are in sync.
 *
 * @return array
 */
function get_wordcamp_query_options() {
	return array(
		'post_status' => WordCamp_Loader::get_public_post_statuses(),
		'orderby'     => 'title',
		'order'       => 'asc',

		'meta_query' => array(
			array(
				'key'     => 'Start Date (YYYY-mm-dd)',
				'value'   => strtotime( '-2 years' ),
				'compare' => '>',
			),
		),
	);
}

/**
 * Handle POST-ed data
 *
 * This is where all the magic happens, forms validation, Stripe requests,
 * keys verification etc. Note that $data here is passed by reference and
 * can (and should, in some cases) be changed.
 *
 * @param array $data By-ref $data array that is passed to the view.
 */
function _handle_post_data( &$data ) {
	$step = filter_input( INPUT_POST, 'step' );
	$fsp  = new Form_Spam_Prevention();

	switch ( $step ) {
		// An invoice, event, currency and amount have been selected.
		default:
		case STEP_SELECT_INVOICE:
			$payment_type = filter_input( INPUT_POST, 'payment_type' );
			$wordcamp_id  = filter_input( INPUT_POST, 'wordcamp_id', FILTER_VALIDATE_INT );
			$invoice_id   = filter_input( INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT );
			$description  = filter_input( INPUT_POST, 'description' );
			$currency     = filter_input( INPUT_POST, 'currency' );
			$amount       = filter_input( INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT );

			if ( ! $fsp->validate_form_submission() ) {
				$data['errors'][] = 'Your form submission could not be processed. Please try again.';
				return;
			}

			switch ( $payment_type ) {
				default:
				case 'invoice':
					if ( ! $wordcamp_id ) {
						$data['errors'][] = 'Please select an event.';
						return;
					}

					// Make sure the selected WordCamp is valid.
					$valid_ids = wp_list_pluck( get_wordcamps( get_wordcamp_query_options() ), 'ID' );

					if ( ! in_array( $wordcamp_id, $valid_ids ) ) {
						$data['errors'][] = 'Please select a valid event.';
						$fsp->add_score_to_ip_address( [ 1 ] );
						return;
					}

					$wordcamp_site_id = get_wordcamp_site_id( get_post( $wordcamp_id ) );

					if ( empty( $wordcamp_site_id ) ) {
						$data['errors'][] = 'Could not find a site for this WordCamp.';
						return;
					}

					if ( ! $invoice_id ) {
						$data['errors'][] = 'Please provide a valid invoice ID.';
						return;
					}
					break;

				case 'other':
					$description = substr( sanitize_text_field( $description ), 0, 100 );

					if ( ! $description ) {
						$data['errors'][] = 'Please describe the purpose of the payment.';
						return;
					}
					break;
			}

			if ( ! $currency ) {
				$data['errors'][] = 'Please select a currency.';
				return;
			}

			if ( ! array_key_exists( $currency, $data['currencies'] ) || false !== strpos( $currency, 'null' ) ) {
				$data['errors'][] = 'Invalid currency.';
				$fsp->add_score_to_ip_address( [ 1 ] );
				return;
			}

			$amount = round( $amount, 2 );

			if ( ! $amount ) {
				$data['errors'][] = 'Please enter a payment amount.';
				return;
			}

			if ( $amount < 1.00 ) {
				$data['errors'][] = 'Amount can not be less than 1.00.';
				return;
			}

			// Next step is to collect the card details via Stripe.
			$data['step']    = STEP_PAYMENT_DETAILS;
			$data['payment'] = array(
				'payment_type' => $payment_type,
				'wordcamp_id'  => $wordcamp_id,
				'invoice_id'   => $invoice_id,
				'description'  => $description,
				'currency'     => $currency,
				'amount'       => $amount,
			);

			// Passed through to the charge step.
			$data['payment_data_json']      = wp_json_encode( $data['payment'] );
			$data['payment_data_signature'] = hash_hmac( 'sha256', $data['payment_data_json'], $data['keys']['hmac_key'] );

			// Add a WordCamp object for convenience.
			$data['payment']['wordcamp_obj'] = get_post( $wordcamp_id );
			break;

		// The card details have been entered and Stripe has submitted our form.
		case STEP_PAYMENT_DETAILS:
			$stripe_token           = filter_input( INPUT_POST, 'stripeToken' );
			$payment_data_json      = filter_input( INPUT_POST, 'payment_data_json' );
			$payment_data_signature = filter_input( INPUT_POST, 'payment_data_signature' );

			if ( ! $stripe_token ) {
				$data['errors'][] = 'Stripe token not found.';
				return;
			}

			if ( ! $payment_data_json || ! $payment_data_signature ) {
				$data['errors'][] = 'Payment data is missing.';
				return;
			}

			// Make sure our data hasn't been altered.
			if ( ! hash_equals( hash_hmac( 'sha256', $payment_data_json, $data['keys']['hmac_key'] ), $payment_data_signature ) ) {
				$data['errors'][] = 'Could not verify payload signature.';
				return;
			}

			$payment_data = json_decode( wp_unslash( $payment_data_json ), true );

			switch ( $payment_data['payment_type'] ) {
				case 'invoice':
					$wordcamp_obj     = get_post( $payment_data['wordcamp_id'] );
					$wordcamp_site_id = get_wordcamp_site_id( $wordcamp_obj );

					$description = sprintf( 'WordCamp Sponsorship: %s', get_wordcamp_name( $wordcamp_site_id ) );
					$metadata    = array(
						'invoice_id'       => $payment_data['invoice_id'],
						'wordcamp_id'      => $payment_data['wordcamp_id'],
						'wordcamp_site_id' => $wordcamp_site_id,
						'wordcamp_url'     => set_url_scheme( esc_url_raw( get_blog_option( $wordcamp_site_id, 'home', '' ) ), 'https' ),
					);
					break;

				case 'other':
					$description = 'Other Payment';
					$metadata    = array(
						'description' => $payment_data['description'],
					);
					break;
			}

			$body = array(
				'amount'      => round( $payment_data['amount'], 0 ) * 100, // TODO handle zero-decimal currencies and currencies with multipliers other than 100.
				'currency'    => $payment_data['currency'],
				'source'      => $stripe_token,
				'description' => $description,
				'metadata'    => $metadata,
			);

			try {
				$stripe = new Stripe_Client( $data['keys']['secret'] );
				$charge = $stripe->charge( $body );
			} catch ( Exception $exception ) {
				$data['errors'][] = $exception->getMessage();
				return;
			}

			// All good!
			$data['step'] = STEP_PAYMENT_SUCCESS;
			$fsp->add_score_to_ip_address( [ -1 ] );
			break;
	}
}
