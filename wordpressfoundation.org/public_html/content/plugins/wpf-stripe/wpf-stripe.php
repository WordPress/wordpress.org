<?php

/*
Plugin Name: WPF Stripe
Description: Add support for Stripe subscriptions
Author:      Otto
Author URI:  http://ottopress.com
License:     GPLv2 or later
*/

namespace WordPress_Foundation\Stripe;

use stdClass;
use Exception, UnexpectedValueException;
use Stripe\Stripe, Stripe\Customer, Stripe\Charge, Stripe\Webhook, Stripe\Event, Stripe\Error as Stripe_Error;
use WP_REST_Request, WP_Error;

defined( 'WPINC' ) || die();

require_once( __DIR__ . '/email.php' );

/**
 * Display Stripe donate buttons.
 *
 * @return string
 */
function render_donate_shortcode() {
	$image = plugins_url( 'blue-xl.png', __FILE__ );

	ob_start();
	?>

	<p>You can choose to donate annually:</p>

	<div class="subscription-plans">
		<form action="" method="POST">
			<script
				src="https://checkout.stripe.com/checkout.js"
				class="stripe-button"
				data-key="<?php echo esc_html( STRIPE_PUBLIC_KEY ); ?>"
				data-image="<?php echo esc_url( $image ); ?>"
				data-name="WordPress Foundation"
				data-description="Yearly Subscription"
				data-amount="1000"
				data-label="Give $10 per year"
				data-zip-code="true"
				data-billing-address="true">
			</script>
			<input type='hidden' name='wpf_plan' value='lowest'>
		</form>

		<form action="" method="POST">
			<script
				src="https://checkout.stripe.com/checkout.js"
				class="stripe-button"
				data-key="<?php echo esc_html( STRIPE_PUBLIC_KEY ); ?>"
				data-image="<?php echo esc_url( $image ); ?>"
				data-name="WordPress Foundation"
				data-description="Yearly Subscription"
				data-amount="5000"
				data-zip-code="true"
				data-billing-address="true"
				data-label="Give $50 per year">
			</script>
			<input type='hidden' name='wpf_plan' value='low'>
		</form>

		<form action="" method="POST">
			<script
				src="https://checkout.stripe.com/checkout.js"
				class="stripe-button"
				data-key="<?php echo esc_html( STRIPE_PUBLIC_KEY ); ?>"
				data-image="<?php echo esc_url( $image ); ?>"
				data-name="WordPress Foundation"
				data-description="Yearly Subscription"
				data-amount="20000"
				data-label="Give $200 per year"
				data-zip-code="true"
				data-billing-address="true">
			</script>
			<input type='hidden' name='wpf_plan' value='medium'>
		</form>

		<form action="" method="POST">
			<script
				src="https://checkout.stripe.com/checkout.js"
				class="stripe-button"
				data-key="<?php echo esc_html( STRIPE_PUBLIC_KEY ); ?>"
				data-image="<?php echo esc_url( $image ); ?>"
				data-name="WordPress Foundation"
				data-description="Yearly Subscription"
				data-amount="100000"
				data-label="Give $1000 per year"
				data-zip-code="true"
				data-billing-address="true">
			</script>
			<input type='hidden' name='wpf_plan' value='high'>
		</form>
	</div>

	<p>Or make a one-time donation in the amount of your choosing:</p>

	<div class="one-time-donation">
		<form action="" method="POST">
			<label>$
				<input type="number" class="amount" name="amount" min="1" />
			</label>

			<script
				src="https://checkout.stripe.com/checkout.js"
				class="stripe-button"
				data-key="<?php echo esc_html( STRIPE_PUBLIC_KEY ); ?>"
				data-image="<?php echo esc_url( $image ); ?>"
				data-name="WordPress Foundation"
				data-description="One-Time Donation"
				data-label="Give once"
				data-zip-code="true"
				data-billing-address="true">
			</script>
		</form>
	</div>

	<?php

	return ob_get_clean();
}
add_shortcode( 'wpfstripe', __NAMESPACE__ . '\render_donate_shortcode' );

/**
 * Output CSS for the `[wpfstripe]` shortcode.
 */
function custom_styles() {
	global $post;

	if ( ! is_a( $post, 'WP_Post' ) || 'donate' !== $post->post_name ) {
		return;
	}

	?>

	<style>
		.stripe-button-el {
			text-transform: none;
		}

		.subscription-plans .stripe-button-el {
			margin: 0 12px 12px 0;
		}

			@media screen and ( min-width: 415px ) {
				.subscription-plans {
					display: flex;
					flex-wrap: wrap;
					justify-content: space-around;
				}
			}

		.one-time-donation {
			margin-top: .5em;
		}

		.one-time-donation form {
			margin-top: .5em;
			text-align: center;
		}

			.amount {
				position: relative;
				top: 3px;
				width: 5em;
				margin-right: .5em;
				padding: 1px 5px;
				text-align: center;
			}
	</style>

	<?php
}
add_action( 'wp_head', __NAMESPACE__ . '\custom_styles' );

/**
 * Process donation transactions through Stripe's API.
 *
 * Emails for successful transactions are sent by `handle_webhooks()`.
 */
function process_payments() {
	if ( ! isset( $_POST['stripeEmail'], $_POST['stripeToken'] ) ) {
		return;
	}

	require_once( __DIR__ . '/stripe-php/init.php' );
	Stripe::setApiKey( STRIPE_SECRET_KEY );

	try {
		if ( isset( $_POST['wpf_plan'] ) ) {
			$params = array(
				'source' => $_POST['stripeToken'],
				'email'  => $_POST['stripeEmail'],
				'plan'   => $_POST['wpf_plan'],	// Subscribe the new Customer to the plan at the same time.
			);

			Customer::create( $params );
		} elseif ( isset( $_POST['amount'] ) ) {
			$params = array(
				'source'        => $_POST['stripeToken'],
				'receipt_email' => $_POST['stripeEmail'],
				'amount'        => $_POST['amount'] * 100,	// Convert dollars to pennies.
				'currency'      => 'USD',
				'description'   => 'WordPress Foundation donation',
			);

			Charge::create( $params );
		} else {
			wp_die( 'Unsupported form action' );
		}

		$redirect_url = home_url( 'successful-donation/' );
	} catch ( Exception $exception ) {
		log( $exception->getMessage(), $params );
		$redirect_url = home_url( 'unsuccessful-donation/' );
	}

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'init', __NAMESPACE__ . '\process_payments' );

/**
 * Register REST API routes.
 */
function register_routes() {
	$route_args = array(
		'methods'  => array( 'POST' ),
		'callback' => __NAMESPACE__ . '\handle_webhooks',
	);

	register_rest_route( 'wpf-stripe/v1', 'webhooks', $route_args );
}
// todo: not ready for production yet -- add_action( 'rest_api_init', __NAMESPACE__ . '\register_routes' );
// todo before enabling this, replace the placeholder content in the Donation Email posts w/ proper content from Andrea

/**
 * Handle webhooks sent from Stripe.
 *
 * @param WP_REST_Request $request
 *
 * @return array|WP_Error
 */
function handle_webhooks( $request ) {
	require_once( __DIR__ . '/stripe-php/init.php' );
	Stripe::setApiKey( STRIPE_SECRET_KEY );

	// todo Stripe's "Billing" feature just launched, and it might replace the need for some of this?
		// https://stripe.com/billing

	$event = get_verified_event( $request );

	if ( is_wp_error( $event ) ) {
		return $event;
	}

	$transaction_data = get_transaction_data_from_event( $event );

	/* todo After deploying all this, we need to create webhook in Stripe that sends all the specific events that we catch here */

	switch ( $event->type ) {
		case 'charge.succeeded':
			$template = empty( $event->data->object->invoice ) ? 'thanks-one-time-donation' : 'thanks-recurring-donation';
			print_r( compact( 'event', 'transaction_data', 'template' ) );

			//send_email( $template, $transaction_data );
			break;

		/*
		 * We don't have any business logic to do here, but Stripe requires us to respond with a 200 status,
		 * or they'll delay the payment for 72 hours.
		 *
		 * See https://stripe.com/docs/subscriptions/webhooks#understand.
		 */
		case 'invoice.created':
			break;

		/*
		    todo

		    also want to send deleted email when a recurring donation fails?
			or does stripe do that automatically?
			it can do it automatically, but we don't have any control over template, and it just points them to our site to update details, which they can't do there
			so we need to send one saying, "charge failed and subscription cancelled. if you want to continue donating, please setup a new subscription"
			which hook? charge failling, or subscription cancelled? how to narrow it down to just situation where 1) it was a subscription; 2) the charge failed and the subscription was canceled
				need to distinguish from situation where one-time donation charged failed; and situation where subscription cancelled b/c they wanted to stop it and so we manually cancelled it
			https://stripe.com/docs/recipes/sending-emails-for-failed-payments

			charge.failed 				  - don't use this, already showed error during donate flow for one time payments. if recurring payment failed, though, then ?
			invoice.payment_failed        - will re-attempt in 7 days. if that fails, then will cancel. can setup new donation
			customer.subscription.deleted - would also fire when we manually cancel. could just say, "if you didn't request this, then it's likely because your credit card expired or could not be charged. if you'd like to continue donating, please visit {url} to setup a new subscription
		*/

		/**
		 * A subscription was deleted.
		 *
		 * The probably because the payment method failed multiple times, since Stripe doesn't provide a way for
		 * donors to cancel the subscription themselves.
		 *
		 * Stripe can send email for these automatically, but it won't let us control the content of the message,
		 * and the message it sends tells the donor to visit our site to update their payment details, which assumes
		 * that we have some custom code written to let them do that via API requests, which of course, we don't.
		 */
		case 'customer.subscription.deleted':
			print_r( compact( 'event', 'transaction_data' ) );
			// send_mail( 'subscription-deleted', $transaction_data );
			// todo need to add a template for this on production
			break;

		// An annual subscription payment is coming up soon.
		case 'invoice.upcoming':
			print_r( compact( 'event', 'transaction_data' ) );
			//send_email( 'upcoming-renewal', $transaction_data );
			break;

		default:
			log( 'unknown event', compact( 'event' ) );
			return new WP_Error( 'unknown_event', 'Unknown event.', array( 'status' => 400 ) );
			break;
	}


	//	var_dump( compact( 'event', 'customer' ) );
	//	die();

	return array(
		'success' => true,
		'message' => 'Event processed successfully, thank you kindly Stripe bot.'
	);
}

/**
 * Convert a REST API request to an authenticated Stripe Webhook Event.
 *
 * This authenticates the request based on its signature, so that it can be trusted as originating from Stripe.
 *
 * @param WP_REST_Request $request
 *
 * @return Event|WP_Error
 */
function get_verified_event( $request ) {
	$signature = $request->get_header( 'Stripe-Signature' );
	$payload   = $request->get_body();

	try {
		$event = Webhook::constructEvent( $payload, $signature, STRIPE_WEBHOOK_SECRET );
	} catch ( UnexpectedValueException $exception ) {
		$event = new WP_Error( 'invalid_payload', $exception->getMessage(), array( 'status' => 400 ) );
	} catch ( Stripe_Error\SignatureVerification $exception ) {
		$event = new WP_Error( 'invalid_signature', $exception->getMessage(), array( 'status' => 400 ) );
	} catch ( Exception $exception ) {
		$event = new WP_Error( $exception->getCode(), $exception->getMessage(), array( 'status' => 400 ) );
	}

	if ( is_wp_error( $event ) ) {
		log( $event->get_error_message(), compact( 'payload', 'signature' ) );
	}

	return $event;
}

/**
 * Extract the relevant
 *
 * @param Event $event
 *
 * @return array
 */
function get_transaction_data_from_event( $event ) {
	switch ( $event->type ) {
		case 'charge.succeeded':
			$source = $event->data->object->source;
			break;

		case 'customer.subscription.deleted':
		case 'invoice.upcoming':
			try {
				// is this the right property in $event for both of these cases?
				$customer = Customer::retrieve( $event->data->object->customer );
				$source   = $customer->sources->data[0];
				// [0] entry isn't necessarily the right one? need the one that's tied to the subscription. maybe 1st is correct in our case b/c Customer is not shared globally w/ other sites?
			} catch ( Exception $exception ) {
				log( $exception->getMessage(), compact( 'event', 'customer' ) );
				$source = new stdClass();
			}
			break;
	}

	/*
	 * todo - Having trouble testing this w/ their test webhook events. Their support said:
	 *		When you use the "Send test webhook" button for your webhook the test event we send you has fake IDs everywhere with zeroes such as evt_000000 so you can't retrieve it through the API.
	 *		The best solution to test this here is to simply create a customer in Test mode in your account and then update its card in the Dashboard for example to get a real event with real data in it.
	 */

	// The keys here must match `get_merge_tags()`.
	$transaction = array(
		'transaction_date'   => isset( $event->data->object->created         ) ? date( 'Y-m-d', $event->data->object->created ) : '',
		'transaction_amount' => isset( $event->data->object->amount          ) ? $event->data->object->amount                   : '',
				// todo event->data->object->amount_due ?
		'email'              => isset( $event->data->object->receipt_email ) ? $event->data->object->receipt_email : '',
		'full_name'          => isset( $source->name            )            ? $source->name                       : '',
		'address1'           => isset( $source->address_line1   )            ? $source->address_line1    : '',
		'address2'           => isset( $source->address_line2   )            ? $source->address_line2    : '',
		'city'               => isset( $source->address_city    )            ? $source->address_city     : '',
		'state'              => isset( $source->address_state   )            ? $source->address_state    : '',
		'zip_code'           => isset( $source->address_zip     )            ? $source->address_zip      : '',
		'country'            => isset( $source->address_country )            ? $source->address_country  : '',
		'payment_card_type'  => isset( $source->brand           )            ? $source->brand            : '',
		'payment_card_last4' => isset( $source->last4           )            ? $source->last4            : '',
	);

	$transaction['full_address'] = trim( sprintf(
		"%s%s%s%s%s%s",
		$transaction['address1'] ? $transaction['address1'] . "\n" : '',
		$transaction['address2'] ? $transaction['address2'] . "\n" : '',
		$transaction['city']     ? $transaction['city']     . ', ' : '',
		$transaction['state']    ? $transaction['state']    . ' '  : '',
		$transaction['zip_code'] ? $transaction['zip_code'] . "\n" : '',
		$transaction['country']  ? $transaction['country']         : ''
	) );

	return $transaction;
}

/**
 * Log an error message.
 *
 * @param string $error_message
 * @param array  $data
 */
function log( $error_message, $data ) {
	// Trigger instead of logging directly, so that it's conveniently displayed in dev environments.
	trigger_error( sprintf(
		'%s error: %s. Data: %s',
		__FUNCTION__,
		$error_message,
		wp_json_encode( $data )
	) );

	// todo need to redact anything, like PII?
		// any key named 'source' probably contains things we shouldn't log, see get_transaction_data_from_event()
}
