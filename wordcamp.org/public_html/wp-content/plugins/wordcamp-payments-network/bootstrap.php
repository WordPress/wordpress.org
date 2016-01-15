<?php

/*
* Plugin Name: WordCamp Budgets Dashboard
* Description: Provides an overview of WordCamp budgets, payment requests, and sponsor invoices across the network.
* Version:     0.1
* Author:      WordCamp.org
* Author URI:  http://wordcamp.org
* License:     GPLv2 or later
* Network:     true
*/

namespace WordCamp\Budgets_Dashboard;

defined( 'WPINC' ) or die();

if ( is_admin() ) {
	require_once( __DIR__ . '/includes/wordcamp-budgets-dashboard.php' );
	require_once( __DIR__ . '/includes/payment-requests-dashboard.php' );

	$GLOBALS['Payment_Requests_Dashboard'] = new \Payment_Requests_Dashboard();

	add_action( 'plugins_loaded', array( $GLOBALS['Payment_Requests_Dashboard'], 'plugins_loaded' ) );
}
