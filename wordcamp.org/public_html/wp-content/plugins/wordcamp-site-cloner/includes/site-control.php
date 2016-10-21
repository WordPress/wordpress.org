<?php

/**
 * Custom Customizer Control for Search WordCamp sites to clone
 */

namespace WordCamp\Site_Cloner;
defined( 'WPINC' ) or die();

class Site_Control extends \WP_Customize_Control {
	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );

		$this->capability = 'edit_theme_options';
		$this->section    = 'wcsc_sites';
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue() {
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'print_view_templates' ) );

		wp_enqueue_style(  'wordcamp-site-cloner' );
		wp_enqueue_script( 'wordcamp-site-cloner' );
	}

	/**
	 * Render the control's content
	 */
	public function render_content() {
		require_once( dirname( __DIR__ ) . '/templates/site-control.php' );
	}

	/**
	 * Render the control's Underscores templates
	 */
	public function print_view_templates() {
		require_once( dirname( __DIR__ ) . '/templates/site-option.php'  );
		require_once( dirname( __DIR__ ) . '/templates/site-filters.php' );
	}
}
