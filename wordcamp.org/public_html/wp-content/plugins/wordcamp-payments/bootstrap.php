<?php
/*
Plugin Name: WordCamp Payments
Plugin URI:  http://wordcamp.org/
Description: Provides tools for collecting and processing payment requests from WordCamp organizers.
Author:      tellyworth, iandunn
Version:     0.1
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

if ( is_admin() ) {
	require_once( __DIR__ . '/classes/wordcamp-payments.php' );
	require_once( __DIR__ . '/classes/payment-request.php' );
	require_once( __DIR__ . '/classes/encryption.php' );

	$GLOBALS['wordcamp_payments']   = new WordCamp_Payments();
	$GLOBALS['wcp_payment_request'] = new WCP_Payment_Request();
}
