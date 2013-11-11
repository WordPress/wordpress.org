<?php
/*
 * Plugin Name: WordCamp.org Canonical Years
 * Author: Andrew Nacin
 * Description: Adds a rel="canonical" tag to the front page of WordCamps for previous years pointing to the current year, providing better SEOs.
 */

class WordCamp_Canonical_Years_Plugin {
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		// Only on the home page.
		if ( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] == '/' )
			add_action( 'wp_head', array( $this, 'wp_head' ), 9 );
	}

	/**
	 * Runs during wp_head, prints the canonical link for the most recent
	 * WordCamp in the same city as the current site.
	 */
	function wp_head() {
		global $wpdb;

		$matches = array();

		// match xxxx.city.wordcamp.org
		if ( ! preg_match( '/^([0-9]{4})+\.((.+)\.wordcamp\.(lo|dev|org))$/i', $_SERVER['HTTP_HOST'], $matches ) )
			return;

		// WordPress will print rel=conanical for singular posts by default, we don't want that.
		remove_action( 'wp_head', 'rel_canonical' );

		$current_domain = $matches[0];
		$city_domain = $matches[2];

		$latest_domain = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM $wpdb->blogs WHERE domain LIKE %s ORDER BY domain DESC LIMIT 1;", "%.{$city_domain}" ) );
		if ( $latest_domain != $current_domain && $latest_domain )
			printf( '<link rel="canonical" href="%s" />' . "\n", trailingslashit( esc_url( $latest_domain ) ) );
	}
}

// Initialize the plugin.
new WordCamp_Canonical_Years_Plugin;