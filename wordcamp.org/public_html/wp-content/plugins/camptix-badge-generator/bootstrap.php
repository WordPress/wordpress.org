<?php

/*
 * Plugin Name: CampTix Badge Generator
 * Description: Generates personalized attendee badges with HTML/CSS or InDesign.
 * Version:     0.1
 * Author:      WordCamp.org
 * Author URI:  http://wordcamp.org
 * License:     GPLv2 or later
 */

namespace CampTix\Badge_Generator;
defined( 'WPINC' ) or die();

const REQUIRED_CAPABILITY = 'manage_options';

require_once( __DIR__ . '/includes/common.php'      );
require_once( __DIR__ . '/includes/html-badges.php' );

if ( is_admin() ) {
	require_once( __DIR__ . '/includes/indesign-badges.php' );
}
