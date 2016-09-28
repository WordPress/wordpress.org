<?php
/**
 * Template Name: Sponsorship Payment (Stripe)
 */

if ( is_callable( 'WordCamp\Budgets\Sponsor_Payment_Stripe\render' ) ) {
	// See: plugins/wordcamp-payments/includes/sponsor-payment-stripe.php
	return WordCamp\Budgets\Sponsor_Payment_Stripe\render();
}
