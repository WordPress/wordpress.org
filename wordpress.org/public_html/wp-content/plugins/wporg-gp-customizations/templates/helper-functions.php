<?php
wp_register_style( 'wporg-translate', plugins_url( 'style.css', __FILE__ ), array( 'base' ), '20160601' );
gp_enqueue_style( 'wporg-translate' );

wp_register_style( 'chartist', plugins_url( 'css/chartist.min.css', __FILE__ ), array(), '0.9.5' );
wp_register_script( 'chartist', plugins_url( 'js/chartist.min.js', __FILE__ ), array(), '0.9.5' );


/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes An array of body classes.
 * @return array Filtered body classes.
 */
function wporg_gp_template_body_classes( $classes ) {
	$classes[] = 'no-js';
	return $classes;
}
add_filter( 'body_class', 'wporg_gp_template_body_classes' );

add_action( 'gp_tmpl_load_locations', function( $locations, $template, $args, $template_path ) {
	$core_templates = GP_PATH . 'gp-templates/';
	require_once $core_templates . 'helper-functions.php';
	$locations[] = $core_templates;
	return $locations;
}, 50, 4 );
