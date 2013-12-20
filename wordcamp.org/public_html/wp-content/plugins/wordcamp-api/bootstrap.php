<?php
/*
 * Plugin Name:   WordCamp API
 * Description:   Provides API endpoints for WordCamp.org data in JSON and ICS formats
 * Version:       0.1
 * Author:        WordCamp.org
 * Author URI:    http://wordcamp.org
 */

require_once( __DIR__ . '/classes/ics.php' );
$GLOBALS['wordcamp_api_ics'] = new WordCamp_API_ICS();

/**
 * Activation and deactivation routines.
 */
function wcorg_calendar_plugin_activate() {
	global $wp_rewrite;
	add_rewrite_rule( '^calendar\.ics$', 'index.php?wcorg_wordcamps_ical=1', 'top' );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wcorg_calendar_plugin_activate' );

function wcorg_calendar_plugin_deactivate() {
	flush_rewrite_rules(); // Doesn't really remove the created rule.
	delete_option( 'wcorg_wordcamps_ical' );
}
register_deactivation_hook( __FILE__, 'wcorg_calendar_plugin_deactivate' );
