<?php
/*
Plugin Name: WordCamp Budgets
Plugin URI:  http://wordcamp.org/
Description: Provides tools for managing WordCamp budgets, sponsor invoices, vendor payments, and reimbursement requests.
Author:      WordCamp.org
Author URI:  https://wordcamp.org
Version:     0.1
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

if ( is_admin() ) {
	require_once( __DIR__ . '/includes/wordcamp-budgets.php' );
	require_once( __DIR__ . '/includes/payment-request.php' );
	require_once( __DIR__ . '/includes/sponsor-invoice.php' );
	require_once( __DIR__ . '/includes/reimbursement-request.php' );
	require_once( __DIR__ . '/includes/encryption.php' );
	require_once( __DIR__ . '/includes/budget-tool.php' );

	$GLOBALS['wordcamp_budgets']    = new WordCamp_Budgets();
	$GLOBALS['wcp_payment_request'] = new WCP_Payment_Request();
}
