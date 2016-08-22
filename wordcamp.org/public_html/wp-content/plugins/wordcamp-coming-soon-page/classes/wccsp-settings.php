<?php

/*
 * todo
 * Now that using Customizer, this class isn't really needed and doesn't make sense. Its functions can be moved
 * into wordcamp-coming-soon-page.php and wccsp-customizer.php
 */

class WCCSP_Settings {
	protected $settings;
	const REQUIRED_CAPABILITY = 'administrator';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu',                   array( $this, 'register_settings_pages' ) );
		add_action( 'init',                         array( $this, 'init' ) );
		add_action( 'update_option_wccsp_settings', array( $this, 'clear_static_page_cache' ) );
	}

	/**
	 * Initializes variables
	 */
	public function init() {
		$this->settings = $this->get_settings();
	}

	/**
	 * Retrieves all of the settings from the database
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'enabled'                    => 'off',        // so that sites created before the plugin was deployed won't display the home page when the plugin is activated
			'body_background_color'      => '#0073aa',
			'image_id'                   => 0,
			'background_id'              => 0,
			'container_background_color' => '#FFFFFF', // deprecated
			'text_color'                 => '#000000', // deprecated
		);

		$settings = shortcode_atts(
			$defaults,
			get_option( 'wccsp_settings', array() )
		);

		return $settings;
	}

	/**
	 * Add a link to the Settings menu
	 *
	 * Even though this lives in the Customizer, having a link in the regular admin menus helps with
	 * discoverability.
	 */
	public function register_settings_pages() {
		$menu_slug = add_query_arg(
			array(
				'autofocus[section]' => 'wccsp_live_preview',
				'url'                => rawurlencode( add_query_arg( 'wccsp-preview', '', site_url() ) ),
			),
			'/customize.php'
		);

		add_submenu_page(
			'options-general.php',
			__( 'Coming Soon', 'wordcamporg' ),
			__( 'Coming Soon', 'wordcamporg' ),
			self::REQUIRED_CAPABILITY,
			$menu_slug
		);
	}

	/**
	 * Clear the static page cache
	 * Changing the settings will change the how the page looks, so the cache needs to be refreshed.
	 */
	public function clear_static_page_cache() {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * Renders notices for the administrator when problems are detected
	 */
	public function render_admin_notices() {
		$active_modules            = Jetpack::$instance->get_active_modules();
		$inactive_required_modules = array();
		$required_modules          = array(
			'subscriptions' => __( 'Subscriptions', 'wordcamporg' ),
			'contact-form'  => __( 'Contact Form',  'wordcamporg' ),
		);

		foreach ( $required_modules as $module_id => $module_name ) {
			if ( ! in_array( $module_id, $active_modules ) ) {
				$inactive_required_modules[] = $module_name;
			}
		}

		ob_start();

		if ( $inactive_required_modules ) {
			require_once( dirname( __DIR__ ) . '/views/settings-admin-notices.php' );
		}

		return ob_get_clean();
	}
} // end WCCSP_Settings
