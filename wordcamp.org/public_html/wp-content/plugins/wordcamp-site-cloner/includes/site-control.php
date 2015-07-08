<?php

namespace WordCamp\Site_Cloner;

defined( 'WPINC' ) or die();

/**
 * Custom Customizer Control for a WordCamp site
 */
class Site_Control extends \WP_Customize_Control {
	public $site_id, $site_name, $screenshot_url, $theme_slug;
	public $settings = 'wcsc_source_site_id';
	public $section  = 'wcsc_sites';

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue() {
		wp_enqueue_style(  'wordcamp-site-cloner' );
		wp_enqueue_script( 'wordcamp-site-cloner' );
	}

	/**
	 * Render the control's content
	 */
	public function render_content() {
		$preview_url = add_query_arg(
			array(
				'theme'               => rawurlencode( $this->theme_slug ),
				'wcsc_source_site_id' => rawurlencode( $this->site_id ),
			),
			admin_url( 'customize.php' )
		);

		require( dirname( __DIR__ ) . '/templates/site-control.php' );
	}
}
