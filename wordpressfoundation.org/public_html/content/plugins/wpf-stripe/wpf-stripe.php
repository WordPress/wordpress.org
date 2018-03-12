<?php
/*
Plugin Name: WPF Stripe
Plugin URI: https://wordpressfoundation.org/wpf-stripe
Description: Add support for Stripe subscriptions
Version: 1.0
Author: Otto
Author URI: http://ottopress.com
License: GPLv2 or later
Text Domain: wpf-stripe
*/

$wpf_success_url = 'https://wordpressfoundation.org/successful-donation/';
$wpf_fail_url    = 'https://wordpressfoundation.org/unsuccessful-donation/';

// Real live data, only use on live site
$wpf_stripe_publishable_key = 'pk_live_qKgFC6r4tVgMx7yIQl5QatpM';

add_shortcode( 'wpfstripe', 'wpf_stripe_buttons' );
function wpf_stripe_buttons() {
	global $wpf_stripe_publishable_key;
	$image = plugins_url( 'blue-xl.png', __FILE__ );

	$output = <<< EOT
<form action="" method="POST">
  <script src="https://checkout.stripe.com/checkout.js" class="stripe-button"
    data-key="{$wpf_stripe_publishable_key}"
    data-image="{$image}"
    data-name="WordPress Foundation"
    data-description="Yearly Subscription"
    data-amount="1000"
    data-label="Give $10 per year">
  </script>
  <input type='hidden' name='wpf_plan' value='lowest'>
</form>
<form action="" method="POST">
  <script src="https://checkout.stripe.com/checkout.js" class="stripe-button"
    data-key="{$wpf_stripe_publishable_key}"
    data-image="{$image}"
    data-name="WordPress Foundation"
    data-description="Yearly Subscription"
    data-amount="5000"
    data-label="Give $50 per year">
  </script>
  <input type='hidden' name='wpf_plan' value='low'>
</form>
<form action="" method="POST">
  <script src="https://checkout.stripe.com/checkout.js" class="stripe-button"
    data-key="{$wpf_stripe_publishable_key}"
    data-image="{$image}"
    data-name="WordPress Foundation"
    data-description="Yearly Subscription"
    data-amount="20000"
    data-label="Give $200 per year">
  </script>
  <input type='hidden' name='wpf_plan' value='medium'>
</form>
<form action="" method="POST">
  <script src="https://checkout.stripe.com/checkout.js" class="stripe-button"
    data-key="{$wpf_stripe_publishable_key}"
    data-image="{$image}"
    data-name="WordPress Foundation"
    data-description="Yearly Subscription"
    data-amount="100000"
    data-label="Give $1000 per year">
  </script>
  <input type='hidden' name='wpf_plan' value='high'>
</form>
EOT;

	return $output;
}

add_action('init', 'wpf_stripe_check_subscribe');
function wpf_stripe_check_subscribe() {
	if ( !empty( $_POST ) && isset( $_POST['stripeToken'] ) ) {
		wpf_stripe_process_payments();
	}
}

function wpf_stripe_process_payments() {
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

add_action('wp_head','wpf_custom_styles');
function wpf_custom_styles() {
?><style>.stripe-button-el { text-transform: none; float: left; margin: 8px; }</style><?php
}

