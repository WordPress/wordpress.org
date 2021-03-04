<?php
/**
 * Class to initialize handbooks.
 *
 * @package handbook
 */

class WPorg_Handbook_Init {

	/**
	 * Array of WPorg_Handbook objects.
	 *
	 * @var array
	 */
	protected static $handbooks = [];

	/**
	 * Returns the instantiated handbook objects.
	 */
	public static function get_handbook_objects() {
		return self::$handbooks;
	}

	/**
	 * Returns the post types of all handbooks.
	 *
	 * @return array
	 */
	public static function get_post_types() {
		/**
		 * Filters the handbook post types for creating handbooks.
		 *
		 * @param array $handbooks Array of handbook post types. Default 'handbook'.
		 */
		return (array) apply_filters( 'handbook_post_types', array( 'handbook' ) );
	}

	/**
	 * Initializes handbooks.
	 */
	public static function init() {
		$post_types = self::get_post_types();

		// Enable table of contents.
		new WPorg_Handbook_TOC( $post_types );

		// Instantiate each of the handbooks.
		self::$handbooks = [];
		foreach ( $post_types as $type ) {
			self::$handbooks[] = new WPorg_Handbook( $type );
		}

		// Enable glossary.
		WPorg_Handbook_Glossary::init();

		// Enqueue styles and scripts.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueues styles.
	 */
	public static function enqueue_styles() {
		wp_enqueue_style( 'wporg-handbook-css', plugins_url( 'stylesheets/callout-boxes.css', WPORG_HANDBOOK_PLUGIN_FILE ), [], '20200121' );
	}

	/**
	 * Enqueues scripts.
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script( 'wporg-handbook', plugins_url( 'scripts/handbook.js', WPORG_HANDBOOK_PLUGIN_FILE ), [ 'jquery' ], '20150930' );
	}

}

add_action( 'after_setup_theme', [ 'WPorg_Handbook_Init', 'init' ] );
