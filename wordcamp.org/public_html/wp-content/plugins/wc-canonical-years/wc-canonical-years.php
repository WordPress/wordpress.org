<?php

/*
 * Plugin Name: WordCamp.org Canonical Years
 * Author: Andrew Nacin
 * Description: Adds a rel="canonical" tag to the front page of WordCamps for previous years pointing to the current year, providing better SEOs.
 */

class WordCamp_Canonical_Years_Plugin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Only on the home page.
		if ( isset( $_SERVER['REQUEST_URI'] ) && '/' === $_SERVER['REQUEST_URI'] ) {
			add_action( 'wp_head', array( $this, 'wp_head' ), 9 );
		}

		// WordPress will print rel=conanical for singular posts by default, we don't want that.
		remove_action( 'wp_head', 'rel_canonical' );
	}

	/**
	 * Runs during wp_head, prints the canonical link for the most recent
	 * WordCamp in the same city as the current site.
	 */
	public function wp_head() {
		global $wpdb;

		$matches = array();

		// Match `xxxx.city.wordcamp.org` pattern.
		if ( ! preg_match( '/^([0-9]{4})+\.((.+)\.wordcamp\.(lo|dev|test|org))$/i', $_SERVER['HTTP_HOST'], $matches ) ) {
			return;
		}

		$wordcamp = get_wordcamp_post();
		$end_date = $wordcamp->meta['End Date (YYYY-mm-dd)'][0] ?? false;

		/*
		 * In rare cases, the site for next year's camp will be created before this year's camp is over. When that
		 * happens, we should wait to add the canonical link until after the current year's camp is over.
		 *
		 * This won't prevent the link from being added to past years, but that edge case isn't significant enough
		 * to warrant the extra complexity.
		 */
		if ( $end_date && time() < ( (int) $end_date + DAY_IN_SECONDS ) ) {
			return;
		}

		$current_domain = $matches[0];
		$city_domain    = $matches[2];

		$latest_domain = $wpdb->get_var( $wpdb->prepare( "
			SELECT domain
			FROM $wpdb->blogs
			WHERE
				domain LIKE %s AND
				SUBSTR( domain, 1, 4 ) REGEXP '^-?[0-9]+$' -- exclude secondary language domains like fr.2013.ottawa.wordcamp.org
			ORDER BY domain
			DESC LIMIT 1;",
			"%.{$city_domain}"
		) );

		if ( $latest_domain !== $current_domain && $latest_domain ) {
			printf(
				'<link rel="canonical" href="%s" />' . "\n",
				esc_url( trailingslashit( $latest_domain ) )
			);
		}
	}
}

// Initialize the plugin.
$GLOBALS['wc_canonical_years'] = new WordCamp_Canonical_Years_Plugin();
