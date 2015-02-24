<?php
/*
Plugin Name: Multi-Event Sponsors
Description: Store and display data on Multi-Event Sponsors
Version:     0.1
Author:      WordCamp Central
Author URI:  http://wordcamp.org
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

require_once( __DIR__ . '/classes/multi-event-sponsors.php' );
require_once( __DIR__ . '/classes/mes-region.php' );
require_once( __DIR__ . '/classes/mes-sponsor.php' );
require_once( __DIR__ . '/classes/mes-sponsorship-level.php' );

$GLOBALS['multi_event_sponsors']  = new Multi_Event_Sponsors();
$GLOBALS['mes_sponsor']           = new MES_Region();
$GLOBALS['mes_sponsor']           = new MES_Sponsor();
$GLOBALS['mes_sponsorship_level'] = new MES_Sponsorship_Level();

register_activation_hook( __FILE__, array( $GLOBALS['multi_event_sponsors'], 'activate' ) );
