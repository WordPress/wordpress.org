<?php

/*
Plugin Name: WordCamp Post Type
Plugin URI: http://wordcamp.org
Description: Creates the custom post type for the central WordCamp directory
Author: The WordCamp Community (and JJJ)
Version: 0.1
*/

/**
 * Set the version early so other plugins have an inexpensive
 * way to check if WordCamp Post Type is already loaded.
 *
 * Note: Loaded does NOT mean initialized
 */
define( 'WCPT_VERSION', '0.1' );
define( 'WCPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPT_URL', plugins_url( '/', __FILE__ ) );

if ( ! class_exists( 'WCPT_Loader' ) ) :
	/**
	 * WCPT_Loader
	 *
	 * @package
	 * @subpackage Loader
	 * @since WordCamp Post Type (0.1)
	 */
	class WCPT_Loader {

		/**
		 * The main WordCamp Post Type loader
		 */
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'core_admin' ) );
			add_action( 'init', array( $this, 'core_text_domain' ) );

			$this->includes();
		}

		/**
		 * WordCamp Core File Includes
		 */
		public function includes() {
			// Load the files.
			require_once( WCPT_DIR . 'wcpt-functions.php' );
			require_once( WCPT_DIR . 'wcpt-wordcamp/wordcamp-loader.php' );
			require_once( WCPT_DIR . 'wcpt-meetup/meetup-loader.php' );
			require_once( WCPT_DIR . 'wcpt-event/tracker.php' );
			require_once( WCPT_DIR . 'wcpt-wordcamp/wordcamp.php' );
			require_once( WCPT_DIR . 'wcpt-meetup/meetup.php' );
			require_once( WCPT_DIR . 'wcpt-meetup/class-meetup-admin.php' );
			require_once( WCPT_DIR . 'wcpt-event/class-event-admin.php' ); // required for declined application cron to work.

			// Require admin files.
			if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
				require_once( WCPT_DIR . 'wcpt-admin.php' );
				require_once( WCPT_DIR . 'wcpt-wordcamp/wordcamp-admin.php' );
				require_once( WCPT_DIR . 'wcpt-wordcamp/privacy.php' );
				require_once( WCPT_DIR . 'mentors/dashboard.php' );
			}
		}

		/**
		 * Initialize core admin objects
		 */
		public function core_admin() {
			// Quick admin check.
			if ( ! is_admin() && ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) ) {
				return;
			}

			// Create admin.
			$GLOBALS['wcpt_admin']     = new WCPT_Admin();
			$GLOBALS['wordcamp_admin'] = new WordCamp_Admin();
			$GLOBALS['meetup_admin']   = new Meetup_Admin();
		}

		/**
		 * Load the translation file for current language
		 */
		public function core_text_domain() {
			$locale = apply_filters( 'wcpt_textdomain', get_locale() );
			$mofile = WCPT_DIR . "wcpt-languages/wcpt-$locale.mo";

			load_textdomain( 'wcpt', $mofile );
		}
	}

endif; // class_exists check.

// Load everything up.
$wcpt_loader     = new WCPT_Loader();
$wordcamp_loader = new WordCamp_Loader();
$meetup_loader   = new Meetup_Loader();
