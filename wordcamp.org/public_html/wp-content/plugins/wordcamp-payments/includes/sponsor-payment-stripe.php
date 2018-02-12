<?php
/**
 * Sponsorship Payments via Stripe
 */

namespace WordCamp\Budgets\Sponsor_Payment_Stripe;
use WordCamp\Utilities\Stripe_Client;
use WordCamp_Budgets;
use Exception;

defined( 'WPINC' ) or die();

const STEP_SELECT_INVOICE  = 1;
const STEP_PAYMENT_DETAILS = 2;
const STEP_PAYMENT_SUCCESS = 3;
const CSS_VERSION          = 1;

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
		'keys'       => $keys,
		'step'       => STEP_SELECT_INVOICE,
		'wordcamps'  => _get_wordcamps(),
		'currencies' => WordCamp_Budgets::get_currencies(),
		'errors'     => array(),
	);

	if ( ! empty( $_POST['sponsor_payment_submit'] ) ) {
		_handle_post_data( $data ); // $data passed by ref.
	}

	wp_enqueue_style( 'wcb-sponsor-payments', plugins_url( 'css/sponsor-payments.css', __DIR__ ), array(), CSS_VERSION );
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
 * Return a list of valid WordCamp posts.
 *
 * @return array An array or WP_Post objects for all public WordCamps.
 */
function _get_wordcamps() {
	static $wordcamps;

	if ( ! isset( $wordcamps ) ) {
		$wordcamps = get_posts( array(
			'post_type'      => 'wordcamp',
			'post_status'    => \WordCamp_Loader::get_public_post_statuses(),
			'posts_per_page' => - 1,
			'orderby'        => 'title',
			'order'          => 'asc',

			'meta_query' => array(
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'value'   => strtotime( '-3 months' ),
					'compare' => '>'
				)
			)
		) );
	}

	return $wordcamps;
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
	$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : STEP_SELECT_INVOICE;

	switch ( $_POST['step'] ) {
		// An invoice, event, currency and amount have been selected.
		case STEP_SELECT_INVOICE:
			if ( empty( $_POST['currency'] ) ) {
				$data['errors'][] = 'Please select a currency.';
				return;
			}

			$currency = $_POST['currency'];
			if ( ! array_key_exists( $currency, $data['currencies'] ) || false !== strpos( $currency, 'null' ) ) {
				$data['errors'][] = 'Invalid currency.';
				return;
			}

			if ( empty( $_POST['amount'] ) ) {
				$data['errors'][] = 'Please enter a payment amount.';
				return;
			}

			$amount = round( floatval( $_POST['amount'] ), 2 );
			if ( $amount < 1.00 ) {
				$data['errors'][] = 'Amount can not be less than 1.00.';
				return;
			}

			if ( empty( $_POST['wordcamp_id'] ) ) {
				$data['errors'][] = 'Please select an event.';
				return;
			}

			// Make sure the selected WordCamp is valid.
			$wordcamp_id = absint( $_POST['wordcamp_id'] );
			$valid_ids   = wp_list_pluck( _get_wordcamps(), 'ID' );

			if ( ! in_array( $wordcamp_id, $valid_ids ) ) {
				$data['errors'][] = 'Please select a valid event.';
				return;
			}

			if ( empty( $_POST['invoice_id'] ) ) {
				$data['errors'][] = 'Please provide a valid invoice ID.';
				return;
			}

			$invoice_id       = absint( $_POST['invoice_id'] );
			$wordcamp_site_id = get_wordcamp_site_id( get_post( $wordcamp_id ) );
			if ( empty( $wordcamp_site_id ) ) {
				$data['errors'][] = 'Could not find a site for this WordCamp.';
				return;
			}

			// Next step is to collect the card details via Stripe.
			$data['step']    = STEP_PAYMENT_DETAILS;
			$data['payment'] = array(
				'currency'    => $currency,
				'amount'      => $amount,
				'wordcamp_id' => $wordcamp_id,
				'invoice_id'  => $invoice_id,
			);

			// Passed through to the charge step.
			$data['payment_data_json']      = json_encode( $data['payment'] );
			$data['payment_data_signature'] = hash_hmac( 'sha256', $data['payment_data_json'], $data['keys']['hmac_key'] );

			// Add a WordCamp object for convenience.
			$data['payment']['wordcamp_obj'] = get_post( $wordcamp_id );
			break;

		// The card details have been entered and Stripe has submitted our form.
		case STEP_PAYMENT_DETAILS:
			if ( empty( $_POST['stripeToken'] ) ) {
				$data['errors'][] = 'Stripe token not found.';
				return;
			}

			// Make sure our data hasn't been altered.
			$payment_data_str = wp_unslash( $_POST['payment_data_json'] );
			$payment_data     = json_decode( $payment_data_str, true );
			if ( ! hash_equals( hash_hmac( 'sha256', $payment_data_str, $data['keys']['hmac_key'] ), $_POST['payment_data_signature'] ) ) {
				$data['errors'][] = 'Could not verify payload signature.';
				return;
			}

			$wordcamp_obj      = get_post( $payment_data['wordcamp_id'] );
			$wordcamp_site_id  = get_wordcamp_site_id( $wordcamp_obj );
			$wordcamp_site_url = set_url_scheme( esc_url_raw( get_blog_option( $wordcamp_site_id, 'home', '' ) ), 'https' );

			$body = array(
				'amount'      => round( $payment_data['amount'], 2 ) * 100,
				'currency'    => $payment_data['currency'],
				'source'      => $_POST['stripeToken'],
				'description' => 'WordCamp Sponsorship: ' . $wordcamp_obj->post_title,
				'metadata'    => array(
					'invoice_id'       => $payment_data['invoice_id'],
					'wordcamp_id'      => $payment_data['wordcamp_id'],
					'wordcamp_site_id' => $wordcamp_site_id,
					'wordcamp_url'     => $wordcamp_site_url,
				),
			);

			try {
				$stripe = new Stripe_Client( $data['keys']['secret'] );
				$charge = $stripe->charge( $body );
			} catch ( Exception $exception ) {
				$data['errors'][] = "An error occurred, please try another card. If that doesn't work, please contact ". EMAIL_CENTRAL_SUPPORT .".";
				return;
			}

			// All good!
			$data['step'] = STEP_PAYMENT_SUCCESS;
			break;
	}
}
