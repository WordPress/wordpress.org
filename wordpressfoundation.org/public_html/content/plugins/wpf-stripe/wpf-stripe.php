<?php

/*
Plugin Name: WPF Stripe
Description: Add support for Stripe subscriptions
Author:      Otto
Author URI:  http://ottopress.com
License:     GPLv2 or later
*/

namespace WordPress_Foundation\Stripe;
use Exception;
use Stripe\Stripe, Stripe\Customer, Stripe\Charge;

defined( 'WPINC' ) || die();


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
}
