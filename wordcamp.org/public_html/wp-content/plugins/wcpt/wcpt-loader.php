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

if ( !class_exists( 'WCPT_Loader' ) ) :
/**
 * WCPT_Loader
 *
 * @package
 * @subpackage Loader
 * @since WordCamp Post Type (0.1)
 *
 */
class WCPT_Loader {

	/**
	 * The main WordCamp Post Type loader
	 */
	function wcpt_loader () {
		/** COMPONENT HOOKS ***************************************************/

		// Attach the wcpt_loaded action to the WordPress plugins_loaded action.
		add_action( 'plugins_loaded',   array ( $this, 'component_loaded' ) );

		// Attach the wcpt_init to the WordPress init action.
		add_action( 'init',             array ( $this, 'component_init' ) );

		// Attach constants to wcpt_loaded.
		add_action( 'wcpt_loaded',      array ( $this, 'component_constants' ) );

		// Attach includes to wcpt_loaded.
		add_action( 'wcpt_loaded',      array ( $this, 'component_includes' ) );

		// Attach post type registration to wcpt_init.
		add_action( 'wcpt_init',        array ( $this, 'component_post_types' ) );

		// Attach tag registration wcpt_init.
		add_action( 'wcpt_init',        array ( $this, 'component_taxonomies' ) );

		/** CORE HOOKS ********************************************************/

		// Core Constants
		add_action( 'wcpt_started',     array ( $this, 'core_constants' ) );

		// Core Includes
		add_action( 'wcpt_started',     array ( $this, 'core_includes' ) );

		// Core Admin
		add_action( 'wcpt_loaded',      array ( $this, 'core_admin' ) );

		// Attach theme directory wcpt_loaded.
		add_action( 'wcpt_loaded',      array ( $this, 'core_theme_directory' ) );

		// Attach textdomain to wcpt_init.
		add_action( 'wcpt_init',        array ( $this, 'core_text_domain' ) );

		// Register WordCamp Post Type activation sequence
		register_activation_hook( __FILE__,   array( $this, 'activation' ) );

		// Register WordCamp Post Type deactivation sequence
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

		// Get this party started
		do_action( 'wcpt_started' );		
	}

	/**
	 * core_constants ()
	 * 
	 * WordCamp Core Constants
	 */
	function core_constants () {
		// Turn debugging on/off
		if ( !defined( 'WCPT_DEBUG' ) )
			define( 'WCPT_DEBUG', WP_DEBUG );

		// Default slug for post type
		if ( !defined( 'WCPT_THEMES_DIR' ) )
			define( 'WCPT_THEMES_DIR', apply_filters( 'wcpt_themes_dir', WP_PLUGIN_DIR . '/wcpt-themes' ) );

		// WordCamp Post Type root directory
		define( 'WCPT_DIR', WP_PLUGIN_DIR . '/wcpt' );
		define( 'WCPT_URL', plugins_url( $path = '/wcpt' ) );

		// Images URL
		define( 'WCPT_IMAGES_URL', WCPT_URL . '/wcpt-images' );
	}

	/**
	 * core_includes ()
	 * 
	 * WordCamp Core File Includes
	 */
	function core_includes () {
		// Load the files
		require_once ( WCPT_DIR . '/wcpt-functions.php' );
		require_once ( WCPT_DIR . '/wcpt-wordcamp/wordcamp-loader.php' );
		// Require admin files.
		if ( is_admin() ) {
			require_once ( WCPT_DIR . '/wcpt-admin.php' );
			require_once ( WCPT_DIR . '/wcpt-wordcamp/wordcamp-admin.php' );
		}
	}

	function core_admin () {
		// Quick admin check
		if ( !is_admin() )
			return;

		// Create admin
		$GLOBALS['wcpt_admin']      = new WCPT_Admin();
		$GLOBALS['wordcamp_admin']  = new WordCamp_Admin();
	}

	/**
	 * core_text_domain ()
	 *
	 * Load the translation file for current language
	 */
	function core_text_domain () {
		$locale = apply_filters( 'wcpt_textdomain', get_locale() );

		$mofile = WCPT_DIR . "/wcpt-languages/wcpt-$locale.mo";

		load_textdomain( 'wcpt', $mofile );

		/**
		 * Text domain has been loaded
		 */
		do_action( 'wcpt_load_textdomain' );
	}

	/**
	 * core_theme_directory ()
	 *
	 * Sets up the WordCamp Post Type theme directory to use in WordPress
	 *
	 * @since WordCamp Post Type (0.1)
	 * @uses register_theme_directory
	 */
	function core_theme_directory () {
		register_theme_directory( WCPT_THEMES_DIR );

		/**
		 * Theme directory has been registered
		 */
		do_action( 'wcpt_register_theme_directory' );
	}

	/**
	 * activation ()
	 *
	 * Runs on WordCamp Post Type activation
	 *
	 * @since WordCamp Post Type (0.1)
	 */
	function activation () {
		register_uninstall_hook( __FILE__, array( $this, 'uninstall' ) );

		/**
		 * WordCamp Post Type has been activated
		 */
		do_action( 'wcpt_activation' );
	}

	/**
	 * deactivation ()
	 *
	 * Runs on WordCamp Post Type deactivation
	 *
	 * @since WordCamp Post Type (0.1)
	 */
	function deactivation () {
		do_action( 'wcpt_deactivation' );
	}

	/**
	 * uninstall ()
	 *
	 * Runs when uninstalling WordCamp Post Type
	 *
	 * @since WordCamp Post Type (0.1)
	 */
	function uninstall () {
		do_action( 'wcpt_uninstall' );
	}

	/**
	 * component_constants ()
	 *
	 * Default component constants that can be overridden or filtered
	 */
	function component_constants () {
		do_action( 'wcpt_constants' );
	}

	/**
	 * component_includes ()
	 *
	 * Include required files
	 *
	 */
	function component_includes () {
		do_action( 'wcpt_includes' );
	}

	/**
	 * component_loaded ()
	 *
	 * A WordCamp Post Type specific action to say that it has started its
	 * boot strapping sequence. It's attached to the existing WordPress
	 * action 'plugins_loaded' because that's when all plugins have loaded.
	 *
	 * @uses is_admin If in WordPress admin, load additional file
	 * @uses do_action()
	 */
	function component_loaded () {
		do_action( 'wcpt_loaded' );
	}

	/**
	 * component_init ()
	 *
	 * Initialize WordCamp Post Type as part of the WordPress initilization process
	 *
	 * @uses do_action Calls custom action to allow external enhancement
	 */
	function component_init () {
		do_action ( 'wcpt_init' );
	}

	/**
	 * component_post_type ()
	 *
	 * Setup the post types and taxonomies
	 */
	function component_post_types () {
		do_action ( 'wcpt_register_post_types' );
	}

	/**
	 * component_taxonomies ()
	 *
	 * Register the built in WordCamp Post Type taxonomies
	 *
	 * @since WordCamp Post Type (0.1)
	 *
	 * @uses register_taxonomy()
	 * @uses apply_filters()
	 */
	function component_taxonomies () {
		do_action ( 'wcpt_register_taxonomies' );
	}
}

endif; // class_exists check

// Load everything up
$wcpt_loader      = new WCPT_Loader();
$wordcamp_loader  = new WordCamp_Loader();
?>
