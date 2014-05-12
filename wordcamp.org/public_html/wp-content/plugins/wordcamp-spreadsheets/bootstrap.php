<?php
/*
Plugin Name: WordCamp Spreadsheets
Description: Create and share spreadsheets
Version:     0.1
Author:      WordCamp Central
Author URI:  http://wordcamp.org
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

require_once( __DIR__ . '/classes/wordcamp-spreadsheets.php' );
require_once( __DIR__ . '/classes/wcss-spreadsheet.php' );

$GLOBALS['WordCamp_Spreadsheets'] = new WordCamp_Spreadsheets();
$GLOBALS['WCSS_Spreadsheet']      = new WCSS_Spreadsheet();

register_activation_hook( __FILE__, array( $GLOBALS['WordCamp_Spreadsheets'], 'activate' ) );
