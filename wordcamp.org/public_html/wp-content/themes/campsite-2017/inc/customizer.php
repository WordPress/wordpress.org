<?php
/**
 * CampSite 2017 Theme Customizer
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;
use WP_Customize_Manager;

add_action( 'customize_register',     __NAMESPACE__ . '\customize_register'   );
add_action( 'customize_preview_init', __NAMESPACE__ . '\customize_preview_js' );

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
	$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function customize_preview_js() {
	wp_enqueue_script(
		__NAMESPACE__ . '\customizer',
		get_template_directory_uri() . '/js/customizer.js',
		array( 'customize-preview' ),
		'20151215',
		true
	);
}
