<?php

namespace WordCamp\RemoteCSS;

defined( 'WPINC' ) or die();

/*
Plugin Name: WordCamp Remote CSS
Description: Allows organizers to develop their Custom CSS with whatever tools and environment they prefer.
Version:     0.1
Author:      WordCamp.org
Author URI:  http://wordcamp.org
License:     GPLv2 or later
*/

require_once( __DIR__ . '/app/common.php' );

if ( is_admin() ) {
	require_once( __DIR__ . '/app/synchronize-remote-css.php' );
	require_once( __DIR__ . '/app/user-interface.php'         );
	require_once( __DIR__ . '/app/webhook-handler.php'        );
	require_once( __DIR__ . '/platforms/github.php'           );
}

if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
	require_once( __DIR__ . '/app/output-cached-css.php' );
}
