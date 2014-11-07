<?php
/*
Plugin Name: jQuery UI CSS
Plugin URI:  http://wordcamp.org/
Description: Registers jQuery-UI's CSS so that all plugins can use it.
Version:     0.1
*/

/*
 * Register the stylesheets
 *
 * TODO: This can be removed when #18909-core is committed
 */
function juicss_register_styles( $hook ) {
	wp_register_style(
		'jquery-ui',
		plugins_url( 'jquery-ui.min.css', __FILE__ ),
		array(),
		'1.11.2'
	);

	// https://github.com/x-team/wp-jquery-ui-datepicker-skins
	wp_register_style(
		'wp-datepicker-skins',
		plugins_url( 'wp-datepicker-skins.css', __FILE__ ),
		array( 'jquery-ui' ),
		'1712f05a1c6a76ef0ac0b0a9bf79224e52e461ab'
	);
}
add_action( 'admin_enqueue_scripts', 'juicss_register_styles' );
