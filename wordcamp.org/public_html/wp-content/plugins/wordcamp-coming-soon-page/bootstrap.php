<?php
/*
Plugin Name: WordCamp Coming Soon Page
Description: Creates a Coming Soon landing page for new WordCamp sites
Version:     0.1
Author:      WordCamp Central
Author URI:  http://wordcamp.org
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

require_once( __DIR__ . '/classes/wordcamp-coming-soon-page.php' );
require_once( __DIR__ . '/classes/wccsp-settings.php' );

$GLOBALS['WordCamp_Coming_Soon_Page'] = new WordCamp_Coming_Soon_Page();
$GLOBALS['WCCSP_Settings']            = new WCCSP_Settings();