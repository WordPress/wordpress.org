<?php
/**
 * Class to initialize handbooks.
 *
 * @package handbook
 */

class WPorg_Handbook_Init {

	/**
	 * Asociative array of WPorg_Handbook objects.
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
		return array_keys( self::$handbooks );
	}

	/**
	 * Returns the handbooks configurations.
	 *
	 * @param string $handbook Optional. A specific handbook to return config for.
	 *                         If none specified, then all are returned. Default ''.
	 * @return array|false If $handbook defined, then the config for that handbook
	 *                     if handbook exists, else false. If no $handbook specified,
	 *                     then an associative array of config for all handbooks,
	 *                     keyed by post type.
	 */
	public static function get_handbooks_config( $handbook = '' ) {
		$return = false;
		$handbooks = self::get_handbook_objects();

		// If no handbook specified, return configs for all handbooks.
		if ( ! $handbook ) {
			$return = [];
			foreach ( $handbooks as $type => $handbook_obj ) {
				$return[ $type ] = $handbook_obj->get_config();
			}
		}

		return ( $handbook && ! empty( $handbooks[ $handbook ] ) && is_a( $handbooks[ $handbook ], 'WPorg_Handbook' ) )
			? $handbooks[ $handbook ]->get_config()
			: $return;
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
		$config = [];
		$handbooks_config = [];

		/**
		 * Fires before handbooks have been initialized.
		 */
		do_action( 'before_handbooks_init' );

		/**
		 * Filters the handbook post types for creating handbooks.
		 *
		 * @deprecated Use {@see 'handbooks_config'} instead.
		 *
		 * @param array $handbooks Array of handbook post types. Default empty
		 *                         array, which later will be interpreted to
		 *                         be 'handbook'.
		 */
		$post_types = (array) apply_filters( 'handbook_post_types', [] );

		foreach ( $post_types as $post_type ) {
			$config[ $post_type ] = [];
		}

		/**
		 * Defines and configures all handbooks.
		 *
		 * @see WPorg_Handbook::get_default_handbook_config()
		 *
		 * @param array $config Associative array of handbooks and their
		 *                      configuration options. Keys should be the handbook
		 *                      post type (which will get appended '-handbook' if
		 *                      the post type isn't 'handbook' and doesn't already
		 *                      contain '-handbook'. See
		 *                      {@see WPorg_Handbook::get_default_handbook_config()}
		 *                      for list of per-handbook configuration options.
		 */
		$config = (array) apply_filters( 'handbooks_config', $config );

		// If no handbooks were configured, default to a basic handbook.
		if ( ! $config ) {
			$config = [ 'handbook' => [] ];
		}

		// Get default settings for a handbook.
		$defaults = WPorg_Handbook::get_default_handbook_config();

		// Determine each handbook's config.
		foreach ( $config as $key => $value ) {
			$key = sanitize_title( $key );
			$post_type = ( 'handbook' === $key || false !== strpos( $key, '-handbook' ) ) ? $key : $key . '-handbook';

			$handbooks_config[ $post_type ] = wp_parse_args( $value, $defaults );

			// Set slug if not explicitly set.
			if ( empty( $handbooks_config[ $post_type ]['slug'] ) ) {
				$handbooks_config[ $post_type ]['slug'] = $key;
			}
		}

		$post_types = array_keys( $handbooks_config );

		// Enable table of contents.
		new WPorg_Handbook_TOC( $post_types );

		// Instantiate each of the handbooks.
		self::$handbooks = [];
		foreach ( $handbooks_config as $type => $conf ) {
			self::$handbooks[ $type ] = new WPorg_Handbook( $type, $conf );
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
