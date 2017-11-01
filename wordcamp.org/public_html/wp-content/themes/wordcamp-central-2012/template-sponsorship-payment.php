<?php
/**
 * Template Name: Sponsorship Payment (Stripe)
 */

require_once( WP_PLUGIN_DIR . '/wordcamp-payments/includes/sponsor-payment-stripe.php' );

if ( is_callable( 'WordCamp\Budgets\Sponsor_Payment_Stripe\render' ) ) {
	// See: plugins/wordcamp-payments/includes/sponsor-payment-stripe.php
	return WordCamp\Budgets\Sponsor_Payment_Stripe\render();
}
