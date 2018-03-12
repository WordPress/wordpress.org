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

	<?php

	return ob_get_clean();
}
add_shortcode( 'wpfstripe', __NAMESPACE__ . '\render_donate_shortcode' );

add_action( 'init', __NAMESPACE__ . '\check_subscribe' );
function check_subscribe() {
	if ( !empty( $_POST ) && isset( $_POST['stripeToken'] ) ) {
		process_payments();
	}
}

function process_payments() {
	global $wpf_success_url, $wpf_fail_url;

	// no token, nothing to do
	if ( empty( $_POST['stripeToken'] ) ) {
		return;
	}

	require_once('stripe-php/init.php');

	\Stripe\Stripe::setApiKey( STRIPE_SECRET_KEY );

	try
	{
		$customer = \Stripe\Customer::create(array(
			'email' => $_POST['stripeEmail'],
			'source'  => $_POST['stripeToken'],
			'plan' => $_POST['wpf_plan'],
	  		));

	  	wp_redirect( $wpf_success_url );
		exit;
	}
	catch(Exception $e)
	{
		wp_redirect( $wpf_fail_url );
		exit;
	}
}

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
			float: left;
			margin: 8px;
		}
	</style>

	<?php
}
add_action( 'wp_head', __NAMESPACE__ . '\custom_styles' );
