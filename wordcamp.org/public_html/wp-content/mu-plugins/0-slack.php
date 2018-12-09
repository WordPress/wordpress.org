<?php

/*
 * Load the Slack includes early so they'll be available to send fatal errors.
 */

defined( 'WPINC' ) or die();

if( is_readable( __DIR__ . '/includes/slack/send.php' ) ) {
	require_once( __DIR__ . '/includes/slack/send.php' );
}
