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

	$load_budget_tool = true;

	// Don't load the budget tool on these sites.
	if ( preg_match( '#\.(?:us|europe)\.wordcamp\.org$#', strtolower( $_SERVER['HTTP_HOST'] ) ) ) {
		$load_budget_tool = false;
	}

	// Don't load the budget tool on non YYYY. sites.
	if ( ! preg_match( '#^[0-9]{4}\.#', $_SERVER['HTTP_HOST'] ) ) {
		$load_budget_tool = false;
	}

	// Force budget tool on testing.wordcamp.org
	if ( 'testing.wordcamp.org' == $_SERVER['HTTP_HOST'] ) {
		$load_budget_tool = true;
	}

	if ( $load_budget_tool ) {
		require_once( __DIR__ . '/includes/budget-tool.php' );
	}

	$GLOBALS['wordcamp_budgets']    = new WordCamp_Budgets();
	$GLOBALS['wcp_payment_request'] = new WCP_Payment_Request();
}
