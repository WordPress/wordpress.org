<?php

class WCCSP_Settings {
	protected $settings;
	const REQUIRED_CAPABILITY = 'administrator';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_settings_pages' ) );
		add_action( 'init',                  array( $this, 'init' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices',         array( $this, 'render_admin_notices' ) );
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
			'body_background_color'      => '#666666',
			'container_background_color' => '#FFFFFF',
			'text_color'                 => '#000000',
			'image_id'                   => 0,
		);
			
		$settings = shortcode_atts(
			$defaults,
			get_option( 'wccsp_settings', array() )
		);

		return $settings;
	}

	/**
	 * Register and enqueue the JavaScript we need for the Settings screen
	 * 
	 * @param string $screen
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'settings_page_wccsp_settings' != $hook_suffix ) {
			return;
		}
		
		wp_register_script(
			'wccsp-settings',
			plugins_url( '/javascript/wccsp-settings.js', __DIR__ ),
			array( 'jquery', 'media-upload', 'media-views' ),
			WordCamp_Coming_Soon_Page::VERSION
		);

		wp_enqueue_media();
		wp_enqueue_script( 'wccsp-settings' );
	}

	/**
	 * Adds pages to the Admin Panel menu
	 */
	public function register_settings_pages() {
		add_submenu_page(
			'options-general.php',
			'Coming Soon',
			'Coming Soon',
			self::REQUIRED_CAPABILITY,
			'wccsp_settings',
			array( $this, 'markup_settings_page' )
		);
	}

	/**
	 * Creates the markup for the Settings page
	 */
	public function markup_settings_page() {
		if ( current_user_can( self::REQUIRED_CAPABILITY ) ) {
			require_once( dirname( __DIR__ ) . '/views/settings-screen.php' );
		} else {
			wp_die( 'Access denied.' );
		}
	}

	/**
	 * Registers settings sections, fields and settings
	 */
	public function register_settings() {
		add_settings_section(
			'wccsp_default',
			'', 
			array( $this, 'markup_section_headers' ),
			'wccsp_settings'
		);
		

		add_settings_field(
			'wccsp_enabled',
			'Enabled',
			array( $this, 'markup_fields' ),
			'wccsp_settings',
			'wccsp_default',
			array( 'label_for' => 'wccsp_enabled_true' )
		);

		add_settings_field(
			'wccsp_body_background_color',
			'Body Background Color',
			array( $this, 'markup_fields' ),
			'wccsp_settings',
			'wccsp_default',
			array( 'label_for' => 'wccsp_body_background_color' )
		);

		add_settings_field(
			'wccsp_container_background_color',
			'Container Background Color',
			array( $this, 'markup_fields' ),
			'wccsp_settings',
			'wccsp_default',
			array( 'label_for' => 'wccsp_container_background_color' )
		);

		add_settings_field(
			'wccsp_text_color',
			'Text Color',
			array( $this, 'markup_fields' ),
			'wccsp_settings',
			'wccsp_default',
			array( 'label_for' => 'wccsp_text_color' )
		);

		add_settings_field(
			'wccsp_image_id',
			'Image',
			array( $this, 'markup_fields' ),
			'wccsp_settings',
			'wccsp_default',
			array( 'label_for' => 'wccsp_image_id' )
		);
		

		register_setting(
			'wccsp_settings',
			'wccsp_settings',
			array( $this, 'validate_settings' )
		);
	}

	/**
	 * Adds the section introduction text to the Settings page
	 *
	 * @param array $section
	 */
	public function markup_section_headers( $section ) {
		require( dirname( __DIR__ ) . '/views/settings-section-headers.php' );
	}

	/**
	 * Delivers the markup for settings fields
	 *
	 * @param array $field
	 */
	public function markup_fields( $field ) {
		switch ( $field['label_for'] ) {
			case 'wccsp_image_id':
				$image = wp_get_attachment_image_src( $this->settings['image_id'], 'medium' );
			break;
		}

		require( dirname( __DIR__ ) . '/views/settings-fields.php' );
	}

	/**
	 * Validates submitted setting values before they get saved to the database.
	 *
	 * @param array $new_settings
	 * @return array
	 */
	public function validate_settings( $new_settings ) {
		$new_settings = shortcode_atts( $this->settings, $new_settings );

		if ( 'on' != $new_settings['enabled'] ) {
			$new_settings['enabled'] = 'off';
		}
		
		$new_settings['body_background_color']      = sanitize_text_field( $new_settings['body_background_color'] );
		$new_settings['container_background_color'] = sanitize_text_field( $new_settings['container_background_color'] );
		$new_settings['text_color']                 = sanitize_text_field( $new_settings['text_color'] );
		
		$new_settings['image_id'] = absint( $new_settings['image_id'] );
		
		return $new_settings;
	}

	/**
	 * Renders notices for the administrator when problems are detected
	 */
	public function render_admin_notices() {
		$current_screen = get_current_screen();
		
		if ( 'settings_page_wccsp_settings' != $current_screen->id ) {
			return;
		}

		$active_modules            = Jetpack::$instance->get_active_modules();
		$inactive_required_modules = array();
		$required_modules          = array(
			'subscriptions' => 'Subscriptions',
			'contact-form'  => 'Contact Form',
		);
		
		foreach ( $required_modules as $module_id => $module_name ) {
			if ( ! in_array( $module_id, $active_modules ) ) {
				$inactive_required_modules[] = $module_name;
			}
		}

		if ( $inactive_required_modules ) {
			require_once( dirname( __DIR__ ) . '/views/settings-admin-notices.php' );
		}
	}
} // end WCCSP_Settings
