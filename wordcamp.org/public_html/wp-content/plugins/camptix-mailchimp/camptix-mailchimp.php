<?php
/*
 * Plugin Name: CampTix MailChimp Addon
 * Plugin URI: http://wordcamp.org
 * Description: Export attendee data to MailChimp lists.
 * Version: 0.7
 * Author: Automattic
 * Author URI: http://wordcamp.org
 * License: GPLv2
 */

function camptix_mailchimp_init() {
	global $camptix;
	require_once( plugin_dir_path( __FILE__ ) . 'addons/camptix-mailchimp.php' );
	$camptix->register_addon( 'CampTix_MailChimp_Addon' );
}
add_action( 'camptix_load_addons', 'camptix_mailchimp_init' );