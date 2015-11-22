<?php
wp_register_style( 'wporg-translate', 'https://wordpress.org/translate/gp-templates-new/style.css', array( 'base' ), '20151111' );
gp_enqueue_style( 'wporg-translate' );

add_action( 'gp_tmpl_load_locations', function( $locations, $template, $args, $template_path ) {
	$core_templates = GP_PATH . 'gp-templates/';
	require_once $core_templates . 'helper-functions.php';
	$locations[] = $core_templates;
	return $locations;
}, 50, 4 );
