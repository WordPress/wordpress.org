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
	 * Returns the handbook objects.
	 *
	 * @return WPorg_Handbook[]
	 */
	public static function get_handbook_objects() {
		return self::$handbooks;
	}

	/**
	 * Returns a handbook of the given post type.
	 *
	 * @param string $post_type The handbook post type.
	 * @return WPorg_Handbook|false The handbook object, or false if no such
	 *                              handbook.
	 */
	public static function get_handbook( $post_type ) {
		$handbooks = self::get_handbook_objects();
		return $handbooks[ $post_type ] ?? false;
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
		$post_types = (array) apply_filters( 'handbook_post_types', [ 'handbook' ] );
		return array_map(
			function( $pt ) {
				$pt = sanitize_title( $pt );
				return ( in_array( $pt, [ 'handbook', 'page' ] ) || false !== strpos( $pt, '-handbook' ) ) ? $pt : $pt . '-handbook';
			},
			$post_types
		);
	}

	/**
	 * Resets memoized and cached variables.
	 *
	 * @param bool $delete_handbook_objects Optional. Delete associated handbook
	 *                                      objects? Default false.
	 */
	public static function reset( $delete_handbook_objects = false ) {
		if ( $delete_handbook_objects ) {
			foreach ( self::get_handbook_objects() as $obj ) {
				unset( $obj );
			}
		}

		self::$handbooks  = [];
	}

	/**
	 * Initializes handbooks.
	 */
	public static function init() {
		/**
		 * Fires before handbooks have been initialized.
		 */
		do_action( 'before_handbooks_init' );

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

		/**
		 * Fires after handbooks have been initialized.
		 */
		do_action( 'after_handbooks_init' );
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
