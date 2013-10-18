<?php

/*
Plugin Name: Email Post Changes - Subscribe to Specific Post
Description: Extends the Email Post Changes plugin to allow visitors to subscribe to a specific post/page
Version:     0.1
Author:      Ian Dunn
*/

class EPCSpecificPost {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'widgets_init',              array( $this, 'register_widgets' ) );
		add_filter( 'email_post_changes_emails', array( $this, 'insert_subscribed_emails' ), 10, 3 );
	}

	/**
	 * Register widgets
	 */
	public function register_widgets() {
		register_widget( 'EPCSP_SubscribeWidget' );
	}

	/**
	 * Inserts extra email addresses into the list of recipients
	 * When EPC is crafting a new notification, this method adds all of the addresses we've collected to it
	 * 
	 * @param  array $emails The list of addresses that EPC has collected
	 * @param  int   $old_post_id
	 * @param  int   $new_post_id
	 * @return array
	 */
	public function insert_subscribed_emails( $emails, $old_post_id, $new_post_id ) {
		$subscribed_emails = get_option( 'epcsp_subscribed_addresses', array() );
		
		if ( isset( $subscribed_emails[ $old_post_id ] ) && is_array( $subscribed_emails[ $old_post_id ] ) ) {
			$emails = array_merge( $emails, $subscribed_emails[ $old_post_id ] );
			$emails = array_unique( $emails );
		}
		
		return $emails;
	}
}

require_once( __DIR__ . '/widget-subscribe.php' );
$GLOBALS['EPCSpecificPost'] = new EPCSpecificPost();